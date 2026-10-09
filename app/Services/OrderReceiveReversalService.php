<?php

namespace App\Services;

use App\Services\StoneInventory\StockService as StoneInventoryStockService;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class OrderReceiveReversalService
{
    private PostingService $postingService;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->postingService = new PostingService($this->db);
    }

    /**
     * @return array{order_no:string,restored_status:string,reversed_movements:int}
     */
    public function reverse(int $orderId, int $actorId, string $reason, ?string $ipAddress = null): array
    {
        if ($orderId <= 0 || $actorId <= 0) {
            throw new RuntimeException('A valid order and logged-in audit user are required.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Enter a reversal reason of at least 5 characters.');
        }

        $order = $this->db->query(
            'SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL',
            [$orderId]
        )->getRowArray();
        if (! $order) {
            throw new RuntimeException('Order not found.');
        }
        if ((string) ($order['status'] ?? '') === 'Cancelled') {
            throw new RuntimeException('Cancelled orders cannot have their receiving reversed.');
        }
        if (! $this->db->tableExists('audit_logs')) {
            throw new RuntimeException('Receiving reversal audit storage is unavailable.');
        }

        $movements = $this->db->table('order_material_movements')
            ->where('order_id', $orderId)
            ->where('movement_type', 'receive')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        if ($movements === []) {
            throw new RuntimeException('This order has no active receiving transactions to reverse.');
        }
        $movementIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $movements
        )));

        $summaries = $this->db->table('order_receive_summaries')
            ->where('order_id', $orderId)
            ->get()->getResultArray();
        $details = $this->db->table('order_receive_details')
            ->whereIn('movement_id', $movementIds)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $backflushRows = $this->db->table('stone_inventory_issue_headers')
            ->whereIn('receive_movement_id', $movementIds)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $backflushIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $backflushRows
        )));
        $backflushLines = $backflushIds === []
            ? []
            : $this->db->table('stone_inventory_issue_lines')->whereIn('issue_id', $backflushIds)->get()->getResultArray();
        $fgItems = $this->db->table('fg_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();
        $fgItemIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $fgItems
        )));
        $orderItems = $this->db->table('order_items')->where('order_id', $orderId)->get()->getResultArray();

        $this->assertNoDownstreamTransactions($orderId, $backflushIds, $fgItems, $fgItemIds);

        $voucherIds = [];
        foreach ($summaries as $summary) {
            foreach (['account_voucher_id', 'stone_account_voucher_id', 'wastage_account_voucher_id'] as $field) {
                $voucherId = (int) ($summary[$field] ?? 0);
                if ($voucherId > 0) {
                    $voucherIds[] = $voucherId;
                }
            }
        }
        foreach ($backflushRows as $row) {
            $voucherId = (int) ($row['account_voucher_id'] ?? 0);
            if ($voucherId > 0) {
                $voucherIds[] = $voucherId;
            }
        }
        $legacyVoucherRows = $this->db->table('vouchers')
            ->select('id')
            ->where('order_id', $orderId)
            ->whereIn('voucher_type', ['JEWELLERY_RECEIVE', 'LABOUR_WASTAGE_CHARGE'])
            ->get()->getResultArray();
        foreach ($legacyVoucherRows as $row) {
            $voucherId = (int) ($row['id'] ?? 0);
            if ($voucherId > 0) {
                $voucherIds[] = $voucherId;
            }
        }
        $voucherIds = array_values(array_unique($voucherIds));
        $vouchers = $voucherIds === []
            ? []
            : $this->db->table('vouchers')->whereIn('id', $voucherIds)->get()->getResultArray();
        if (count($vouchers) !== count($voucherIds)) {
            throw new RuntimeException('One or more receiving accounting vouchers are missing.');
        }
        foreach ($vouchers as $voucher) {
            if ((int) ($voucher['is_reversal'] ?? 0) === 1) {
                throw new RuntimeException('A receiving summary points to a reversal voucher; manual review is required.');
            }
            $status = strcasecmp(trim((string) ($voucher['status'] ?? '')), 'Reversed') === 0
                ? 'Reversed'
                : trim((string) ($voucher['status'] ?? ''));
            if (! in_array($status, ['Posted', 'Reversed'], true)) {
                throw new RuntimeException('A receiving accounting voucher is not posted; manual review is required.');
            }
        }

        $voucherLines = $voucherIds === []
            ? []
            : $this->db->table('voucher_lines')->whereIn('voucher_id', $voucherIds)->get()->getResultArray();
        $detailIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $details
        )));
        $bagMovementRows = $detailIds !== [] && $this->db->tableExists('diamond_bag_movements')
            ? $this->db->table('diamond_bag_movements')->whereIn('receive_detail_id', $detailIds)->get()->getResultArray()
            : [];
        foreach ($bagMovementRows as $bagMovement) {
            if (strtoupper(trim((string) ($bagMovement['movement_type'] ?? ''))) !== 'STUDDED') {
                throw new RuntimeException('A receiving detail has a non-studding diamond bag movement; manual review is required.');
            }
        }
        $fgMovements = $fgItemIds !== [] && $this->db->tableExists('showroom_fg_movements')
            ? $this->db->table('showroom_fg_movements')->whereIn('fg_item_id', $fgItemIds)->get()->getResultArray()
            : [];
        $previousStatus = $this->previousOrderStatus($orderId);
        $snapshot = [
            'order' => $order,
            'previous_status' => $previousStatus,
            'order_items' => $orderItems,
            'receive_movements' => $movements,
            'receive_summaries' => $summaries,
            'receive_details' => $details,
            'stone_backflush_headers' => $backflushRows,
            'stone_backflush_lines' => $backflushLines,
            'finished_items' => $fgItems,
            'finished_item_movements' => $fgMovements,
            'diamond_bag_movements' => $bagMovementRows,
            'vouchers' => $vouchers,
            'voucher_lines' => $voucherLines,
        ];

        $this->db->transException(true)->transBegin();
        try {
            $lockedOrder = $this->db->query(
                'SELECT status FROM orders WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
                [$orderId]
            )->getRowArray();
            $lockedMovements = $this->db->table('order_material_movements')
                ->select('id')
                ->where('order_id', $orderId)
                ->where('movement_type', 'receive')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
            $lockedMovementIds = array_values(array_filter(array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                $lockedMovements
            )));
            if (! $lockedOrder || (string) ($lockedOrder['status'] ?? '') === 'Cancelled' || $lockedMovementIds !== $movementIds) {
                throw new RuntimeException('Order receiving changed before reversal could start. Refresh the order and try again.');
            }
            $lockedBackflushRows = $this->db->table('stone_inventory_issue_headers')
                ->select('id')
                ->whereIn('receive_movement_id', $movementIds)
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
            $lockedBackflushIds = array_values(array_filter(array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                $lockedBackflushRows
            )));
            $lockedFgItems = $this->db->query(
                'SELECT * FROM fg_items WHERE order_id = ? ORDER BY id ASC FOR UPDATE',
                [$orderId]
            )->getResultArray();
            $lockedFgItemIds = array_values(array_filter(array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                $lockedFgItems
            )));
            if ($lockedBackflushIds !== $backflushIds || $lockedFgItemIds !== $fgItemIds) {
                throw new RuntimeException('Receiving inventory changed before reversal could start. Refresh the order and try again.');
            }
            $this->assertNoDownstreamTransactions($orderId, $lockedBackflushIds, $lockedFgItems, $lockedFgItemIds);

            foreach ($backflushRows as $backflush) {
                $issueId = (int) $backflush['id'];
                (new StoneInventoryStockService($this->db))->reverseIssue($issueId);
                $this->reverseVoucherIfPosted((int) ($backflush['account_voucher_id'] ?? 0), $reason, $actorId);
                $this->db->table('stone_inventory_issue_lines')->where('issue_id', $issueId)->delete();
                $this->db->table('stone_inventory_issue_headers')->where('id', $issueId)->delete();
            }

            foreach ($voucherIds as $voucherId) {
                $this->reverseVoucherIfPosted($voucherId, $reason, $actorId);
            }

            $detailIds = array_values(array_filter(array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                $details
            )));
            if ($detailIds !== [] && $this->db->tableExists('diamond_bag_movements')) {
                $this->db->table('diamond_bag_movements')
                    ->whereIn('receive_detail_id', $detailIds)
                    ->where('movement_type', 'STUDDED')
                    ->delete();
            }

            if ($fgItemIds !== []) {
                if ($this->db->tableExists('showroom_fg_movements')) {
                    $this->db->table('showroom_fg_movements')->whereIn('fg_item_id', $fgItemIds)->delete();
                }
                $this->db->table('fg_items')->whereIn('id', $fgItemIds)->delete();
            }
            $this->db->table('order_receive_details')->whereIn('movement_id', $movementIds)->delete();
            $this->db->table('order_receive_summaries')->where('order_id', $orderId)->delete();
            foreach ($movements as $movement) {
                $this->db->table('order_material_movements')->where('id', (int) $movement['id'])->update([
                    'movement_type' => 'receive_reversed',
                    'notes' => trim((string) ($movement['notes'] ?? '') . ' | Receiving reversed by admin #' . $actorId . ': ' . $reason),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->db->table('orders')->where('id', $orderId)->update([
                'status' => $previousStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('order_items')->where('order_id', $orderId)->update([
                'item_status' => $previousStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('order_status_history')->insert([
                'order_id' => $orderId,
                'from_status' => (string) ($order['status'] ?? ''),
                'to_status' => $previousStatus,
                'remarks' => 'Finished-jewellery receiving reversed by admin #' . $actorId . '. Reason: ' . $reason,
                'changed_by' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('audit_logs')->insert([
                'entity_type' => 'order_receive',
                'entity_id' => $orderId,
                'action' => 'REVERSE',
                'before_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'reason' => $reason,
                    'restored_status' => $previousStatus,
                    'reversed_movement_ids' => $movementIds,
                    'reversed_voucher_ids' => $voucherIds,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'created_by' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if (! $this->db->transStatus()) {
                throw new RuntimeException('Receiving reversal could not be completed.');
            }
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Receiving reversal could not be committed.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'order_no' => (string) ($order['order_no'] ?? ('#' . $orderId)),
            'restored_status' => $previousStatus,
            'reversed_movements' => count($movementIds),
        ];
    }

    private function reverseVoucherIfPosted(int $voucherId, string $reason, int $actorId): void
    {
        if ($voucherId <= 0) {
            return;
        }
        $voucher = $this->db->table('vouchers')->where('id', $voucherId)->get()->getRowArray();
        if (! $voucher) {
            throw new RuntimeException('A receiving accounting voucher is missing.');
        }
        if (strcasecmp(trim((string) ($voucher['status'] ?? '')), 'Reversed') === 0) {
            return;
        }
        if (strcasecmp(trim((string) ($voucher['status'] ?? '')), 'Posted') !== 0) {
            throw new RuntimeException('A receiving accounting voucher is no longer posted; manual review is required.');
        }
        $this->postingService->reverseVoucher(
            $voucherId,
            'Order receiving reversed: ' . $reason,
            $actorId,
            true,
            true
        );
    }

    private function previousOrderStatus(int $orderId): string
    {
        $history = $this->db->table('order_status_history')
            ->where('order_id', $orderId)
            ->where('to_status', 'Completed')
            ->like('remarks', 'Completed after manual finished-jewellery receiving', 'after')
            ->orderBy('id', 'DESC')
            ->get()->getRowArray();
        $status = trim((string) ($history['from_status'] ?? ''));
        if ($status === '' || in_array($status, ['Completed', 'Cancelled'], true)) {
            return 'In Production';
        }
        return $status;
    }

    /**
     * @param list<int> $backflushIds
     * @param list<array<string,mixed>> $fgItems
     * @param list<int> $fgItemIds
     */
    private function assertNoDownstreamTransactions(int $orderId, array $backflushIds, array $fgItems, array $fgItemIds): void
    {
        foreach ([
            ['packing_lists', 'order_id', 'packing list'],
            ['delivery_challans', 'order_id', 'delivery challan'],
            ['invoices', 'order_id', 'customer invoice'],
            ['showroom_sales', 'order_id', 'showroom sale'],
            ['labour_bills', 'order_id', 'labour bill'],
            ['debit_notes', 'order_id', 'debit note'],
            ['credit_notes', 'order_id', 'credit note'],
        ] as [$table, $field, $label]) {
            if ($this->countByValue($table, $field, $orderId) > 0) {
                throw new RuntimeException('Receiving cannot be reversed because this order has a linked ' . $label . '.');
            }
        }

        if ($backflushIds !== [] && $this->countWhereIn('stone_inventory_return_headers', 'issue_id', $backflushIds) > 0) {
            throw new RuntimeException('A stone return is linked to receiving. Remove or reverse it first.');
        }

        if ($fgItems === []) {
            return;
        }
        if ($this->countWhereIn('invoice_items', 'fg_item_id', $fgItemIds) > 0
            || $this->countWhereIn('showroom_sale_items', 'fg_item_id', $fgItemIds) > 0
            || $this->countWhereIn('packing_list_items', 'fg_item_id', $fgItemIds) > 0) {
            throw new RuntimeException('Receiving cannot be reversed because the finished jewellery has linked sales, invoices or packing records.');
        }
        if ($this->countWhereIn('qc_checks', 'fg_item_id', $fgItemIds) > 0
            || $this->countWhereIn('production_ready_items', 'fg_item_id', $fgItemIds) > 0) {
            throw new RuntimeException('Receiving cannot be reversed because the finished jewellery has linked QC or production records.');
        }
        if ($this->countWhereIn('showroom_reservations', 'fg_item_id', $fgItemIds) > 0
            || $this->countByValue('showroom_reservations', 'order_id', $orderId) > 0) {
            throw new RuntimeException('Receiving cannot be reversed while finished jewellery is reserved.');
        }

        foreach ($fgItems as $fg) {
            $status = strtoupper(trim((string) ($fg['status'] ?? 'AVAILABLE')));
            $stockStatus = strtoupper(trim((string) ($fg['showroom_stock_status'] ?? 'FG_STORE')));
            if (! in_array($status, ['', 'AVAILABLE'], true)
                || ! in_array($stockStatus, ['', 'FG_STORE'], true)
                || (int) ($fg['showroom_id'] ?? 0) > 0
                || (int) ($fg['showroom_counter_id'] ?? 0) > 0) {
                throw new RuntimeException('Receiving cannot be reversed because finished jewellery has moved from the FG store.');
            }
        }

        if ($this->db->tableExists('showroom_fg_movements')
            && $this->db->fieldExists('fg_item_id', 'showroom_fg_movements')
            && $this->db->fieldExists('movement_type', 'showroom_fg_movements')) {
            $rows = $this->db->table('showroom_fg_movements')
                ->select('movement_type')
                ->whereIn('fg_item_id', $fgItemIds)
                ->get()->getResultArray();
            foreach ($rows as $row) {
                if (strtoupper(trim((string) ($row['movement_type'] ?? ''))) !== 'ORDER_COMPLETED_TO_FG') {
                    throw new RuntimeException('Receiving cannot be reversed because finished jewellery has a downstream inventory movement.');
                }
            }
        }
    }

    private function countByValue(string $table, string $field, int $value): int
    {
        if (! $this->db->tableExists($table) || ! $this->db->fieldExists($field, $table)) {
            return 0;
        }
        return $this->db->table($table)->where($field, $value)->countAllResults();
    }

    /** @param list<int> $values */
    private function countWhereIn(string $table, string $field, array $values): int
    {
        if ($values === [] || ! $this->db->tableExists($table) || ! $this->db->fieldExists($field, $table)) {
            return 0;
        }
        return $this->db->table($table)->whereIn($field, $values)->countAllResults();
    }
}
