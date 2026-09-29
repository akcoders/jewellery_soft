<?php

namespace App\Services;

use App\Services\StoneInventory\StockService as StoneInventoryStockService;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Permanently removes an order while keeping shared/legal records safe.
 *
 * Material issue/return vouchers are intentionally detached instead of deleted:
 * they may contain stock for multiple orders and are authoritative inventory records.
 */
class OrderDeletionService
{
    private PostingService $postingService;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->postingService = new PostingService($this->db);
    }

    /**
     * @return array{order_no:string,records_deleted:int,files_deleted:int,files_retained:int,audit_id:int}
     */
    public function delete(int $orderId, int $actorId, string $reason, ?string $ipAddress = null): array
    {
        if ($orderId <= 0 || $actorId <= 0) {
            throw new RuntimeException('A valid order and logged-in audit user are required.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Enter a deletion reason of at least 5 characters.');
        }

        $order = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) {
            throw new RuntimeException('Order not found or it has already been deleted.');
        }

        $jobCardIds = $this->idsWhere('job_cards', 'order_id', $orderId);
        $movementIds = $this->idsWhere('order_material_movements', 'order_id', $orderId);
        $followupIds = $this->idsWhere('order_followups', 'order_id', $orderId);
        $requirementIds = $this->idsWhere('diamond_requirements', 'order_id', $orderId);
        $requirementTaskIds = [];
        if ($requirementIds !== [] && $this->tableHasFields('mobile_tasks', ['id', 'reference_type', 'reference_id'])) {
            $taskRows = $this->db->table('mobile_tasks')->select('id')
                ->where('reference_type', 'diamond_requirement')
                ->whereIn('reference_id', $requirementIds)->get()->getResultArray();
            $requirementTaskIds = array_map(static fn(array $row): int => (int) $row['id'], $taskRows);
        }
        $packingListIds = $this->idsWhere('packing_lists', 'order_id', $orderId);
        $fgRows = $this->rowsWhere('fg_items', 'order_id', $orderId);
        $fgItemIds = array_map(static fn(array $row): int => (int) $row['id'], $fgRows);

        $this->assertNoProtectedDocuments($orderId, $jobCardIds, $packingListIds, $fgRows, $fgItemIds);

        $filePaths = $this->collectOrderFilePaths($orderId);
        $counts = [];
        $auditId = 0;

        $this->db->transException(true)->transStart();
        try {
            $backflushRows = $this->receiveBackflushRows($movementIds);
            $voucherIds = $this->collectVoucherIds($orderId, $jobCardIds, $backflushRows);

            foreach ($backflushRows as $backflush) {
                $issueId = (int) $backflush['id'];
                (new StoneInventoryStockService($this->db))->reverseIssue($issueId);
                $this->deleteByValue('stone_inventory_issue_lines', 'issue_id', $issueId, $counts);
                $this->deleteByValue('stone_inventory_issue_headers', 'id', $issueId, $counts);
            }

            $this->reverseAndRemoveVouchers($voucherIds, $actorId, $counts);

            // Keep shared material vouchers and bags, but remove the deleted order allocation.
            $this->setNullByValue('issue_lines', 'allocation_order_id', $orderId);
            $this->setNullByValue('return_lines', 'allocation_order_id', $orderId);
            $this->setNullByValue('diamond_bag_movements', 'order_id', $orderId);
            $this->setNullByValue('diamond_issues', 'order_id', $orderId);
            foreach ([
                'gold_inventory_issue_headers', 'gold_inventory_return_headers',
                'stone_inventory_issue_headers', 'stone_inventory_return_headers',
                'issue_headers', 'return_headers', 'inventory_transactions',
            ] as $table) {
                $this->setNullByValue($table, 'order_id', $orderId);
            }

            if ($requirementIds !== []) {
                $this->setNullWhereIn('diamond_bags', 'requirement_id', $requirementIds);
            }
            $this->setNullByValue('diamond_bags', 'order_id', $orderId);
            $this->detachDesignSources($orderId);
            $this->detachHistoricalDiamondBlocks($orderId);

            $this->deleteNotificationReferences([
                'orders' => [$orderId],
                'order_followups' => $followupIds,
                'diamond_requirements' => $requirementIds,
                'mobile_tasks' => $requirementTaskIds,
                'job_cards' => $jobCardIds,
                'order_material_movements' => $movementIds,
                'fg_items' => $fgItemIds,
            ], $counts);

            $this->deleteByValue('whatsapp_sender_queue', 'order_id', $orderId, $counts);
            $this->deleteByValue('whatsapp_message_logs', 'order_id', $orderId, $counts);

            $this->deleteWhereIn('job_card_operations', 'job_card_id', $jobCardIds, $counts);
            $this->deleteWhereIn('job_card_timeline', 'job_card_id', $jobCardIds, $counts);
            $this->deleteWhereIn('job_card_items', 'job_card_id', $jobCardIds, $counts);
            $this->deleteWhereIn('job_card_stages', 'job_card_id', $jobCardIds, $counts);

            $this->deleteWhereIn('packing_list_items', 'packing_list_id', $packingListIds, $counts);
            $this->deleteWhereIn('showroom_fg_movements', 'fg_item_id', $fgItemIds, $counts);
            $this->deleteWhereIn('qc_checks', 'fg_item_id', $fgItemIds, $counts);
            $this->deleteWhereIn('mobile_tasks', 'id', $requirementTaskIds, $counts);

            // These are order-owned operational records. Their accounting effects were
            // reversed above before the source rows are removed.
            foreach ([
                'order_followup_schedules',
                'order_followups',
                'order_diamond_size_details',
                'diamond_requirements',
                'order_receive_details',
                'order_receive_summaries',
                'delivery_challans',
                'order_material_movements',
                'order_attachments',
                'order_status_history',
                'stone_issues',
                'gold_ledger_entries',
                'diamond_ledger_entries',
                'stone_ledger_entries',
                'karigar_payment_ledgers',
                'production_ready_items',
            ] as $table) {
                $this->deleteByValue($table, 'order_id', $orderId, $counts);
            }

            $this->deleteWhereIn('fg_items', 'id', $fgItemIds, $counts);
            $this->deleteWhereIn('packing_lists', 'id', $packingListIds, $counts);
            $this->deleteWhereIn('job_cards', 'id', $jobCardIds, $counts);
            $this->deleteByValue('order_items', 'order_id', $orderId, $counts);

            // Remove stale direct ledger rows not attached to one of the cleaned vouchers.
            $this->deleteByValue('ledger_entries', 'order_id', $orderId, $counts);

            $recordsDeletedBeforeOrder = array_sum($counts);
            if ($this->db->tableExists('order_deletion_audits')) {
                $this->db->table('order_deletion_audits')->insert([
                    'order_id' => $orderId,
                    'order_no' => (string) ($order['order_no'] ?? ('#' . $orderId)),
                    'order_status' => (string) ($order['status'] ?? '') ?: null,
                    'customer_id' => (int) ($order['customer_id'] ?? 0) ?: null,
                    'reason' => $reason,
                    'summary_json' => json_encode([
                        'records_deleted' => $recordsDeletedBeforeOrder + 1,
                        'table_counts' => $counts,
                        'material_transactions' => 'detached',
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'deleted_by' => $actorId,
                    'ip_address' => $ipAddress,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $auditId = (int) $this->db->insertID();
            }

            $this->deleteByValue('orders', 'id', $orderId, $counts);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $filesDeleted = 0;
        $filesRetained = 0;
        foreach ($filePaths as $filePath) {
            if ($this->isPathReferenced($filePath)) {
                $filesRetained++;
                continue;
            }
            if ($this->deleteSafeUploadFile($filePath)) {
                $filesDeleted++;
            } else {
                $filesRetained++;
            }
        }

        $summary = [
            'records_deleted' => array_sum($counts),
            'table_counts' => $counts,
            'files_deleted' => $filesDeleted,
            'files_retained' => $filesRetained,
            'material_transactions' => 'detached',
        ];
        if ($auditId > 0) {
            $this->db->table('order_deletion_audits')->where('id', $auditId)->update([
                'summary_json' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        return [
            'order_no' => (string) ($order['order_no'] ?? ('#' . $orderId)),
            'records_deleted' => array_sum($counts),
            'files_deleted' => $filesDeleted,
            'files_retained' => $filesRetained,
            'audit_id' => $auditId,
        ];
    }

    /** @param list<int> $jobCardIds @param list<int> $packingListIds @param list<array<string,mixed>> $fgRows @param list<int> $fgItemIds */
    private function assertNoProtectedDocuments(int $orderId, array $jobCardIds, array $packingListIds, array $fgRows, array $fgItemIds): void
    {
        $blockers = [];
        foreach ([
            ['invoices', 'order_id', 'customer invoice'],
            ['showroom_sale_items', 'order_id', 'sale bill'],
            ['labour_bills', 'order_id', 'labour bill'],
            ['labour_bill_items', 'order_id', 'labour bill'],
            ['debit_notes', 'order_id', 'debit note'],
            ['credit_notes', 'order_id', 'credit note'],
        ] as [$table, $field, $label]) {
            if ($this->countByValue($table, $field, $orderId) > 0) {
                $blockers[] = $label;
            }
        }
        if ($this->countWhereIn('invoices', 'packing_list_id', $packingListIds) > 0
            || $this->countWhereIn('showroom_sales', 'packing_list_id', $packingListIds) > 0) {
            $blockers[] = 'invoice/sale packing list';
        }
        if ($this->countWhereIn('invoice_items', 'fg_item_id', $fgItemIds) > 0
            || $this->countWhereIn('showroom_sale_items', 'fg_item_id', $fgItemIds) > 0) {
            $blockers[] = 'sold jewellery item';
        }
        if ($this->countWhereIn('showroom_reservations', 'fg_item_id', $fgItemIds) > 0
            || $this->countByValue('showroom_reservations', 'order_id', $orderId) > 0
            || $this->countByValue('fg_items', 'reserved_order_id', $orderId) > 0) {
            $blockers[] = 'jewellery reservation';
        }
        if ($this->countWhereIn('ledger_entries', 'job_card_id', $jobCardIds) > 0
            && $this->countWhereIn('vouchers', 'job_card_id', $jobCardIds) === 0) {
            $blockers[] = 'unbalanced job-card ledger';
        }

        foreach ($fgRows as $fg) {
            $status = strtoupper(trim((string) ($fg['status'] ?? 'AVAILABLE')));
            $stockStatus = strtoupper(trim((string) ($fg['showroom_stock_status'] ?? 'FG_STORE')));
            if (! in_array($status, ['', 'AVAILABLE'], true)
                || ! in_array($stockStatus, ['', 'FG_STORE'], true)
                || (int) ($fg['showroom_id'] ?? 0) > 0
                || (int) ($fg['showroom_counter_id'] ?? 0) > 0) {
                $blockers[] = 'moved/reserved jewellery inventory';
                break;
            }
        }
        if ($fgItemIds !== [] && $this->tableHasFields('showroom_fg_movements', ['fg_item_id', 'movement_type'])) {
            $movements = $this->db->table('showroom_fg_movements')
                ->select('movement_type')
                ->whereIn('fg_item_id', $fgItemIds)
                ->get()
                ->getResultArray();
            foreach ($movements as $movement) {
                if (strtoupper(trim((string) ($movement['movement_type'] ?? ''))) !== 'ORDER_COMPLETED_TO_FG') {
                    $blockers[] = 'jewellery inventory movement';
                    break;
                }
            }
        }

        $blockers = array_values(array_unique($blockers));
        if ($blockers !== []) {
            throw new RuntimeException(
                'Order cannot be deleted because it is linked to: ' . implode(', ', $blockers)
                . '. Remove or reverse those protected documents first.'
            );
        }
    }

    /** @param list<int> $movementIds @return list<array<string,mixed>> */
    private function receiveBackflushRows(array $movementIds): array
    {
        if ($movementIds === [] || ! $this->tableHasFields('stone_inventory_issue_headers', ['id', 'receive_movement_id'])) {
            return [];
        }
        $rows = $this->db->table('stone_inventory_issue_headers')
            ->whereIn('receive_movement_id', $movementIds)
            ->get()
            ->getResultArray();
        $issueIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
        if ($this->countWhereIn('stone_inventory_return_headers', 'issue_id', $issueIds) > 0) {
            throw new RuntimeException('Order receiving has a stone return. Delete that return before deleting the order.');
        }
        return $rows;
    }

    /** @param list<int> $jobCardIds @param list<array<string,mixed>> $backflushRows @return list<int> */
    private function collectVoucherIds(int $orderId, array $jobCardIds, array $backflushRows): array
    {
        $ids = $this->idsWhere('vouchers', 'order_id', $orderId);
        if ($jobCardIds !== [] && $this->tableHasFields('vouchers', ['id', 'job_card_id'])) {
            $ids = array_merge($ids, $this->idsWhereIn('vouchers', 'job_card_id', $jobCardIds));
        }
        if ($this->db->tableExists('order_receive_summaries')) {
            foreach (['account_voucher_id', 'stone_account_voucher_id', 'wastage_account_voucher_id'] as $field) {
                if (! $this->db->fieldExists($field, 'order_receive_summaries')) {
                    continue;
                }
                $rows = $this->db->table('order_receive_summaries')
                    ->select($field)
                    ->where('order_id', $orderId)
                    ->where($field . ' IS NOT NULL', null, false)
                    ->get()
                    ->getResultArray();
                foreach ($rows as $row) {
                    $ids[] = (int) ($row[$field] ?? 0);
                }
            }
        }
        foreach ($backflushRows as $row) {
            $ids[] = (int) ($row['account_voucher_id'] ?? 0);
        }
        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    }

    /** @param list<int> $voucherIds @param array<string,int> $counts */
    private function reverseAndRemoveVouchers(array $voucherIds, int $actorId, array &$counts): void
    {
        if ($voucherIds === [] || ! $this->db->tableExists('vouchers')) {
            return;
        }

        $rows = $this->db->table('vouchers')->whereIn('id', $voucherIds)->get()->getResultArray();
        foreach ($rows as $voucher) {
            $status = trim((string) ($voucher['status'] ?? ''));
            if ((int) ($voucher['is_reversal'] ?? 0) === 1
                || strcasecmp($status, 'Reversed') === 0
                || strcasecmp($status, 'Posted') !== 0) {
                continue;
            }
            $result = $this->postingService->reverseVoucher(
                (int) $voucher['id'],
                'Order permanently deleted',
                $actorId,
                true,
                true
            );
            $voucherIds[] = (int) ($result['reversal_voucher_id'] ?? 0);
        }
        $voucherIds = array_values(array_unique(array_filter($voucherIds, static fn(int $id): bool => $id > 0)));

        if ($this->db->tableExists('voucher_reversals')) {
            $pairs = $this->db->table('voucher_reversals')
                ->groupStart()
                    ->whereIn('original_voucher_id', $voucherIds)
                    ->orWhereIn('reversal_voucher_id', $voucherIds)
                ->groupEnd()
                ->get()
                ->getResultArray();
            foreach ($pairs as $pair) {
                $voucherIds[] = (int) ($pair['original_voucher_id'] ?? 0);
                $voucherIds[] = (int) ($pair['reversal_voucher_id'] ?? 0);
            }
            $voucherIds = array_values(array_unique(array_filter($voucherIds, static fn(int $id): bool => $id > 0)));
            $this->deleteWhereIn('voucher_reversals', 'original_voucher_id', $voucherIds, $counts);
            $this->deleteWhereIn('voucher_reversals', 'reversal_voucher_id', $voucherIds, $counts);
        }

        $this->deleteWhereIn('ledger_entries', 'voucher_id', $voucherIds, $counts);
        $this->deleteWhereIn('voucher_lines', 'voucher_id', $voucherIds, $counts);
        if ($this->tableHasFields('audit_logs', ['entity_type', 'entity_id'])) {
            $builder = $this->db->table('audit_logs')->where('entity_type', 'voucher')->whereIn('entity_id', $voucherIds);
            $count = $builder->countAllResults();
            if ($count > 0) {
                $this->db->table('audit_logs')->where('entity_type', 'voucher')->whereIn('entity_id', $voucherIds)->delete();
                $counts['audit_logs'] = ($counts['audit_logs'] ?? 0) + $count;
            }
        }
        $this->deleteWhereIn('vouchers', 'id', $voucherIds, $counts);
    }

    /** @return list<string> */
    private function collectOrderFilePaths(int $orderId): array
    {
        $paths = [];
        foreach ([
            ['order_attachments', 'order_id', 'file_path'],
            ['order_followups', 'order_id', 'image_path'],
            ['production_ready_items', 'order_id', 'image_path'],
            ['fg_items', 'order_id', 'source_image_path'],
        ] as [$table, $orderField, $pathField]) {
            if (! $this->tableHasFields($table, [$orderField, $pathField])) {
                continue;
            }
            $rows = $this->db->table($table)
                ->select($pathField)
                ->where($orderField, $orderId)
                ->where($pathField . ' IS NOT NULL', null, false)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $path = trim((string) ($row[$pathField] ?? ''));
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }
        return array_values(array_unique($paths));
    }

    /** @param array<string,list<int>> $references @param array<string,int> $counts */
    private function deleteNotificationReferences(array $references, array &$counts): void
    {
        if (! $this->tableHasFields('mobile_push_notifications', ['reference_table', 'reference_id'])) {
            return;
        }
        foreach ($references as $table => $ids) {
            $ids = array_values(array_filter(array_unique(array_map('intval', $ids)), static fn(int $id): bool => $id > 0));
            if ($ids === []) {
                continue;
            }
            $count = $this->db->table('mobile_push_notifications')
                ->where('reference_table', $table)
                ->whereIn('reference_id', $ids)
                ->countAllResults();
            if ($count > 0) {
                $this->db->table('mobile_push_notifications')
                    ->where('reference_table', $table)
                    ->whereIn('reference_id', $ids)
                    ->delete();
                $counts['mobile_push_notifications'] = ($counts['mobile_push_notifications'] ?? 0) + $count;
            }
        }
    }

    private function detachDesignSources(int $orderId): void
    {
        if (! $this->db->tableExists('design_masters') || ! $this->db->fieldExists('source_order_id', 'design_masters')) {
            return;
        }
        $data = ['source_order_id' => null];
        if ($this->db->fieldExists('source_order_item_id', 'design_masters')) {
            $data['source_order_item_id'] = null;
        }
        if ($this->db->fieldExists('updated_at', 'design_masters')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->db->table('design_masters')->where('source_order_id', $orderId)->update($data);
    }

    private function detachHistoricalDiamondBlocks(int $orderId): void
    {
        if (! $this->tableHasFields('historical_diamond_size_blocks', ['order_id', 'status', 'reason'])) {
            return;
        }
        $this->db->table('historical_diamond_size_blocks')->where('order_id', $orderId)->update([
            'order_id' => null,
            'status' => 'unmatched',
            'reason' => 'Previously mapped order was permanently deleted.',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function isPathReferenced(string $path): bool
    {
        $normalized = trim(str_replace('\\', '/', rawurldecode($path)));
        $relative = ltrim($normalized, '/');
        $variants = [$path, $normalized, $relative, '/' . $relative];
        if (str_starts_with($relative, 'public/')) {
            $withoutPublic = ltrim(substr($relative, 7), '/');
            $variants[] = $withoutPublic;
            $variants[] = '/' . $withoutPublic;
        } elseif ($relative !== '') {
            $variants[] = 'public/' . $relative;
            $variants[] = '/public/' . $relative;
        }
        $variants = array_values(array_unique(array_filter($variants, static fn(string $value): bool => $value !== '')));
        foreach ([
            ['order_attachments', 'file_path'],
            ['order_followups', 'image_path'],
            ['production_ready_items', 'image_path'],
            ['design_masters', 'image_path'],
            ['fg_items', 'source_image_path'],
            ['showroom_sale_items', 'image_path'],
            ['mobile_tasks', 'proof_path'],
            ['gold_inventory_purchase_headers', 'attachment_path'],
            ['gold_inventory_issue_headers', 'attachment_path'],
            ['gold_inventory_return_headers', 'attachment_path'],
            ['diamond_inventory_purchase_headers', 'attachment_path'],
            ['issue_headers', 'attachment_path'],
            ['return_headers', 'attachment_path'],
            ['stone_inventory_purchase_headers', 'attachment_path'],
            ['stone_inventory_issue_headers', 'attachment_path'],
            ['stone_inventory_return_headers', 'attachment_path'],
        ] as [$table, $field]) {
            if ($this->tableHasFields($table, [$field])
                && $this->db->table($table)->whereIn($field, $variants)->countAllResults() > 0) {
                return true;
            }
        }
        return false;
    }

    private function deleteSafeUploadFile(string $path): bool
    {
        $path = trim(rawurldecode($path));
        if ($path === '' || str_contains($path, "\0") || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            return false;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, 7);
        }
        $candidate = str_starts_with($relative, 'writable/')
            ? ROOTPATH . $relative
            : FCPATH . $relative;
        $realFile = realpath($candidate);
        if ($realFile === false || ! is_file($realFile)) {
            return false;
        }

        $allowedRoots = array_filter([
            realpath(FCPATH . 'uploads'),
            realpath(WRITEPATH . 'uploads'),
        ]);
        foreach ($allowedRoots as $allowedRoot) {
            if ($realFile === $allowedRoot || str_starts_with($realFile, $allowedRoot . DIRECTORY_SEPARATOR)) {
                return @unlink($realFile);
            }
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    private function rowsWhere(string $table, string $field, int $value): array
    {
        if (! $this->tableHasFields($table, ['id', $field])) {
            return [];
        }
        return $this->db->table($table)->where($field, $value)->get()->getResultArray();
    }

    /** @return list<int> */
    private function idsWhere(string $table, string $field, int $value): array
    {
        return array_map(static fn(array $row): int => (int) $row['id'], $this->rowsWhere($table, $field, $value));
    }

    /** @param list<int> $values @return list<int> */
    private function idsWhereIn(string $table, string $field, array $values): array
    {
        if ($values === [] || ! $this->tableHasFields($table, ['id', $field])) {
            return [];
        }
        $rows = $this->db->table($table)->select('id')->whereIn($field, $values)->get()->getResultArray();
        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    private function countByValue(string $table, string $field, int $value): int
    {
        if (! $this->tableHasFields($table, [$field])) {
            return 0;
        }
        return (int) $this->db->table($table)->where($field, $value)->countAllResults();
    }

    /** @param list<int> $values */
    private function countWhereIn(string $table, string $field, array $values): int
    {
        if ($values === [] || ! $this->tableHasFields($table, [$field])) {
            return 0;
        }
        return (int) $this->db->table($table)->whereIn($field, $values)->countAllResults();
    }

    /** @param array<string,int> $counts */
    private function deleteByValue(string $table, string $field, int $value, array &$counts): void
    {
        if (! $this->tableHasFields($table, [$field])) {
            return;
        }
        $count = $this->countByValue($table, $field, $value);
        if ($count <= 0) {
            return;
        }
        $this->db->table($table)->where($field, $value)->delete();
        $counts[$table] = ($counts[$table] ?? 0) + $count;
    }

    /** @param list<int> $values @param array<string,int> $counts */
    private function deleteWhereIn(string $table, string $field, array $values, array &$counts): void
    {
        if ($values === [] || ! $this->tableHasFields($table, [$field])) {
            return;
        }
        $count = $this->countWhereIn($table, $field, $values);
        if ($count <= 0) {
            return;
        }
        $this->db->table($table)->whereIn($field, $values)->delete();
        $counts[$table] = ($counts[$table] ?? 0) + $count;
    }

    private function setNullByValue(string $table, string $field, int $value): void
    {
        if ($this->tableHasFields($table, [$field])) {
            $this->db->table($table)->where($field, $value)->update([$field => null]);
        }
    }

    /** @param list<int> $values */
    private function setNullWhereIn(string $table, string $field, array $values): void
    {
        if ($values !== [] && $this->tableHasFields($table, [$field])) {
            $this->db->table($table)->whereIn($field, $values)->update([$field => null]);
        }
    }

    /** @param list<string> $fields */
    private function tableHasFields(string $table, array $fields): bool
    {
        if (! $this->db->tableExists($table)) {
            return false;
        }
        foreach ($fields as $field) {
            if (! $this->db->fieldExists($field, $table)) {
                return false;
            }
        }
        return true;
    }
}
