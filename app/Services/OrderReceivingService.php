<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;
use RuntimeException;
use Throwable;

class OrderReceivingService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return array<string,mixed> */
    public function formData(int $orderId): array
    {
        $order = $this->order($orderId);
        $this->assertReadyForReceiving($order);
        $summary = $this->receiptSummary($orderId);
        $diamondBagLines = (new DiamondBagTraceService($this->db))->receiptOptions(
            (int) ($order['assigned_karigar_id'] ?? 0),
            $orderId
        );
        return [
            'order' => $order,
            'already_received' => $this->hasRecordedReceipt($orderId),
            'receipt' => $summary,
            'diamond_bag_lines' => $diamondBagLines,
            'locations' => $this->db->table('inventory_locations')
                ->select('id, name, code')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'gold_purities' => $this->db->table('gold_purities')
                ->select('id, purity_code, purity_percent, color_name')->where('is_active', 1)
                ->orderBy('purity_percent', 'DESC')->get()->getResultArray(),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function receive(int $orderId, array $payload, int $createdBy): array
    {
        if ($createdBy <= 0) {
            throw new RuntimeException('A logged-in user is required for receiving.');
        }
        $order = $this->order($orderId);
        $this->assertReadyForReceiving($order);
        if ((string) ($order['status'] ?? '') === 'Cancelled') {
            throw new RuntimeException('Cancelled orders cannot be received.');
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            throw new RuntimeException('Assign a karigar before receiving finished jewellery.');
        }
        if ($this->hasRecordedReceipt($orderId)) {
            throw new RuntimeException('This order has already been received.');
        }

        $locationId = (int) ($payload['location_id'] ?? 0);
        $location = $this->db->table('inventory_locations')->where('id', $locationId)
            ->where('is_active', 1)->get()->getRowArray();
        if (! $location) {
            throw new RuntimeException('Select a valid receiving location.');
        }
        $purityId = (int) ($payload['gold_purity_id'] ?? 0);
        $purity = $this->db->table('gold_purities')->where('id', $purityId)
            ->where('is_active', 1)->get()->getRowArray();
        if (! $purity) {
            throw new RuntimeException('Select a valid ornament purity.');
        }

        $gross = round((float) ($payload['gross_weight_gm'] ?? 0), 3);
        $goldRate = round((float) ($payload['gold_rate_per_gm'] ?? 0), 2);
        $labourRate = round(max(0, (float) ($payload['labour_rate_per_gm'] ?? 0)), 2);
        $wastagePercent = round(max(0, (float) ($payload['wastage_percent'] ?? 0)), 3);
        $wastageType = strtolower(trim((string) ($payload['wastage_type'] ?? 'normal')));
        if (! in_array($wastageType, ['normal', 'direct'], true)) {
            throw new RuntimeException('Select a valid wastage type.');
        }
        $purityPercent = round((float) ($purity['purity_percent'] ?? 0), 3);
        if ($gross <= 0 || $goldRate <= 0) {
            throw new RuntimeException('Gross weight and gold rate must be greater than zero.');
        }
        if ($purityPercent <= 0 || $purityPercent > 100 || $wastagePercent > 100) {
            throw new RuntimeException('Purity or wastage percentage is invalid.');
        }
        if ($wastageType === 'direct' && $purityPercent + $wastagePercent > 100) {
            throw new RuntimeException('Direct wastage plus ornament purity cannot exceed 100%.');
        }

        $diamonds = $this->diamondRows(
            $payload['diamonds'] ?? [],
            $orderId,
            (int) $order['assigned_karigar_id']
        );
        $stones = $this->componentRows($payload['stones'] ?? [], 'stone');
        $others = $this->componentRows($payload['others'] ?? [], 'other');
        $diamondCts = $this->sum($diamonds, 'weight_cts');
        $diamondPcs = $this->sum($diamonds, 'pcs');
        $stoneCts = $this->sum($stones, 'weight_cts');
        $otherWeight = $this->sum($others, 'weight_gm');
        $diamondWeightGm = round($diamondCts * 0.2, 3);
        $stoneWeightGm = round($stoneCts * 0.2, 3);
        $netGold = round($gross - $diamondWeightGm - $stoneWeightGm - $otherWeight, 3);
        if ($netGold <= 0) {
            throw new RuntimeException('Net gold weight must be greater than zero. Check component weights.');
        }
        $pureGold = round($netGold * $purityPercent / 100, 3);
        $wastage = KarigarMaterialAccountingService::calculateLabourWastage(
            $netGold,
            $purityPercent,
            $wastagePercent,
            $wastageType
        );
        $goldAmount = round($netGold * $goldRate, 2);
        $labourAmount = round($netGold * $labourRate, 2);
        $diamondAmount = $this->sum($diamonds, 'line_total', 2);
        $stoneAmount = $this->sum($stones, 'line_total', 2);
        $otherAmount = $this->sum($others, 'line_total', 2);
        $totalValuation = round($goldAmount + $labourAmount + $diamondAmount + $stoneAmount + $otherAmount, 2);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $calculation = sprintf(
            'PWA receiving: Gross %.3f gm, Net %.3f gm, Pure %.3f gm, Wastage %s %.3f%%, Effective purity %.3f%%, Total pure deduction %.3f gm, Diamond %.3f cts, Stone %.3f cts',
            $gross,
            $netGold,
            $pureGold,
            ucfirst($wastageType),
            $wastagePercent,
            (float) $wastage['effective_purity_percent'],
            (float) $wastage['total_pure_weight_gm'],
            $diamondCts,
            $stoneCts
        );
        $notes = $notes === '' ? $calculation : $notes . ' | ' . $calculation;
        $savedPhoto = null;

        try {
            $this->db->transException(true)->transStart();
            $savedPhoto = $this->saveFinishPhoto((string) ($payload['finish_photo_base64'] ?? ''), $orderId, $createdBy);
            $now = date('Y-m-d H:i:s');
            $this->db->table('order_material_movements')->insert([
                'order_id' => $orderId,
                'movement_type' => 'receive',
                'gold_gm' => $netGold,
                'diamond_cts' => $diamondCts,
                'gold_purity_id' => $purityId,
                'karigar_id' => (int) $order['assigned_karigar_id'],
                'location_id' => $locationId,
                'gross_weight_gm' => $gross,
                'other_weight_gm' => round($stoneWeightGm + $otherWeight, 3),
                'diamond_weight_gm' => $diamondWeightGm,
                'net_gold_weight_gm' => $netGold,
                'pure_gold_weight_gm' => $pureGold,
                'notes' => $notes,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $movementId = (int) $this->db->insertID();
            if ($movementId <= 0) {
                throw new RuntimeException('Receiving movement could not be created.');
            }

            $accounting = new KarigarMaterialAccountingService($this->db);
            $accountVoucherId = $accounting->postFinishedJewelleryReceipt(
                $orderId,
                (int) $order['assigned_karigar_id'],
                $locationId,
                $pureGold,
                $diamondPcs,
                $diamondCts,
                $notes,
                $createdBy,
                null,
                $stoneCts,
                true
            );
            $wastageVoucherId = $accounting->postLabourWastageCharge(
                $orderId,
                (int) $order['assigned_karigar_id'],
                (float) $wastage['pure_weight_gm'],
                $wastagePercent,
                'Labour wastage for order ' . (string) ($order['order_no'] ?? ('#' . $orderId)),
                $createdBy,
                null,
                true
            );

            $this->db->table('order_receive_summaries')->insert([
                'movement_id' => $movementId,
                'account_voucher_id' => $accountVoucherId,
                'stone_account_voucher_id' => $stoneCts > 0 ? $accountVoucherId : null,
                'wastage_account_voucher_id' => $wastageVoucherId,
                'order_id' => $orderId,
                'gold_purity_id' => $purityId,
                'purity_percent' => $purityPercent,
                'effective_purity_percent' => (float) $wastage['effective_purity_percent'],
                'gross_weight_gm' => $gross,
                'net_gold_weight_gm' => $netGold,
                'pure_gold_weight_gm' => $pureGold,
                'diamond_weight_cts' => $diamondCts,
                'diamond_weight_gm' => $diamondWeightGm,
                'stone_weight_cts' => $stoneCts,
                'stone_weight_gm' => $stoneWeightGm,
                'other_weight_gm' => $otherWeight,
                'diamond_amount' => $diamondAmount,
                'stone_amount' => $stoneAmount,
                'other_amount' => $otherAmount,
                'gold_amount' => $goldAmount,
                'labour_rate_per_gm' => $labourRate,
                'labour_amount' => $labourAmount,
                'wastage_percent' => $wastagePercent,
                'wastage_type' => $wastageType,
                'wastage_weight_gm' => (float) $wastage['ornament_weight_gm'],
                'pure_wastage_weight_gm' => (float) $wastage['pure_weight_gm'],
                'total_valuation' => $totalValuation,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertDetails($movementId, $orderId, $diamonds, 'diamond', $createdBy);
            $this->insertDetails($movementId, $orderId, $stones, 'stone', $createdBy);
            $this->insertDetails($movementId, $orderId, $others, 'other', $createdBy);

            $orderUpdate = ['status' => 'Completed', 'updated_at' => $now];
            if ($this->db->fieldExists('receiving_status', 'orders')) {
                $orderUpdate += ['receiving_status' => 'received', 'received_at' => $now, 'received_by' => $createdBy];
            }
            $this->db->table('orders')->where('id', $orderId)->update($orderUpdate);
            $this->db->table('order_items')->where('order_id', $orderId)->update([
                'item_status' => 'Completed',
                'updated_at' => $now,
            ]);
            $this->db->table('order_status_history')->insert([
                'order_id' => $orderId,
                'from_status' => (string) ($order['status'] ?? ''),
                'to_status' => 'Completed',
                'remarks' => 'Finished jewellery received through PWA. Receiving is independently recorded.',
                'changed_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            (new FinishedJewelleryService($this->db))->createForCompletedOrder($orderId, $createdBy);
            (new StaffPerformanceService())->closeOrderFollowupSchedules($orderId);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            if ($savedPhoto !== null && is_file($savedPhoto)) {
                @unlink($savedPhoto);
            }
            throw $e;
        }

        try {
            (new OrderWhatsAppService())->notifyOrderStatusChanged(
                $orderId,
                (string) ($order['status'] ?? ''),
                'Completed',
                'Finished jewellery received.'
            );
            (new OrderWhatsAppService())->notifyOrderReady($orderId, 'Completed');
        } catch (Throwable $e) {
            log_message('error', 'ATTRAI receiving update failed: {message}', ['message' => $e->getMessage()]);
        }
        try {
            (new AttraiIntegrationService())->notifyPointChange(
                $createdBy,
                StaffPerformanceService::TRANSACTION_POINTS,
                'Order receiving completed',
                (string) ($order['order_no'] ?? ('Order #' . $orderId)),
                $orderId
            );
        } catch (Throwable $e) {
            log_message('error', 'Arattai receiving points notification failed: {message}', ['message' => $e->getMessage()]);
        }
        try {
            (new AttraiIntegrationService())->requestOrderRating($orderId);
        } catch (Throwable $e) {
            log_message('error', 'Arattai order rating request failed: {message}', ['message' => $e->getMessage()]);
        }

        return [
            'order_id' => $orderId,
            'order_no' => (string) ($order['order_no'] ?? ''),
            'receiving_status' => 'received',
            'status' => 'Completed',
            'receipt' => $this->receiptSummary($orderId),
        ];
    }

    /** @return array<string,mixed> */
    public function receiptSummary(int $orderId): array
    {
        if (! $this->db->tableExists('order_receive_summaries')) {
            return [];
        }
        return $this->db->table('order_receive_summaries rs')
            ->select('rs.*, gp.purity_code, gp.color_name, au.name AS received_by_name')
            ->join('gold_purities gp', 'gp.id = rs.gold_purity_id', 'left')
            ->join('admin_users au', 'au.id = rs.created_by', 'left')
            ->where('rs.order_id', $orderId)->orderBy('rs.id', 'DESC')->get()->getRowArray() ?? [];
    }

    /** Remove received orders before a picker applies its result limit. */
    public function excludeRecordedReceipts(BaseBuilder $orders, string $orderIdColumn): void
    {
        foreach (['order_receive_summaries' => null, 'order_material_movements' => 'receive'] as $table => $movementType) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            $orders->whereNotIn($orderIdColumn, static function (BaseBuilder $receipts) use ($table, $movementType): BaseBuilder {
                $receipts->select('order_id')->from($table)->where('order_id !=', null);
                if ($movementType !== null) {
                    $receipts->where('movement_type', $movementType);
                }
                return $receipts;
            });
        }
    }

    public function hasRecordedReceipt(int $orderId): bool
    {
        return in_array($orderId, $this->recordedReceiptOrderIds([$orderId]), true);
    }

    /** @param list<int> $orderIds @return list<int> */
    public function recordedReceiptOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(
            array_map('intval', $orderIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($orderIds === []) {
            return [];
        }

        $receivedIds = [];
        if ($this->db->tableExists('order_receive_summaries')) {
            $receivedIds = array_map(
                static fn(array $row): int => (int) ($row['order_id'] ?? 0),
                $this->db->table('order_receive_summaries')
                    ->select('order_id')
                    ->whereIn('order_id', $orderIds)
                    ->groupBy('order_id')
                    ->get()
                    ->getResultArray()
            );
        }
        if ($this->db->tableExists('order_material_movements')) {
            $receivedIds = array_merge(
                $receivedIds,
                array_map(
                    static fn(array $row): int => (int) ($row['order_id'] ?? 0),
                    $this->db->table('order_material_movements')
                        ->select('order_id')
                        ->where('movement_type', 'receive')
                        ->whereIn('order_id', $orderIds)
                        ->groupBy('order_id')
                        ->get()
                        ->getResultArray()
                )
            );
        }

        return array_values(array_unique(array_filter($receivedIds, static fn(int $id): bool => $id > 0)));
    }

    /** @param array<string,mixed> $order */
    private function assertReadyForReceiving(array $order): void
    {
        if ((string) ($order['status'] ?? '') !== 'Ready') {
            throw new RuntimeException('Finished jewellery can be received only when the order is Ready.');
        }
    }

    /** @return array<string,mixed> */
    private function order(int $orderId): array
    {
        $order = $this->db->table('orders o')
            ->select('o.*, k.name AS karigar_name, c.name AS customer_name')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->where('o.id', $orderId)->where('o.deleted_at', null)->get()->getRowArray();
        if (! $order) {
            throw new RuntimeException('Order not found.');
        }

        return $order;
    }

    /** @return list<array<string,mixed>> */
    private function componentRows(mixed $raw, string $type): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach (array_values($raw) as $index => $input) {
            if (! is_array($input)) {
                continue;
            }
            $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 120);
            $pcs = round(max(0, (float) ($input['pcs'] ?? 0)), 3);
            $weightCts = round(max(0, (float) ($input['weight_cts'] ?? 0)), 3);
            $weightGm = round(max(0, (float) ($input['weight_gm'] ?? 0)), 3);
            $rate = round(max(0, (float) ($input['rate'] ?? 0)), 2);
            if ($name === '' && $pcs <= 0 && $weightCts <= 0 && $weightGm <= 0 && $rate <= 0) {
                continue;
            }
            if ($type !== 'other' && $weightCts <= 0) {
                throw new RuntimeException(ucfirst($type) . ' line ' . ($index + 1) . ' requires positive carat weight.');
            }
            if ($type === 'other' && $weightGm <= 0) {
                throw new RuntimeException('Other component line ' . ($index + 1) . ' requires positive gram weight.');
            }
            $rows[] = [
                'name' => $name !== '' ? $name : ucfirst($type),
                'pcs' => $pcs,
                'weight_cts' => $weightCts,
                'weight_gm' => $weightGm,
                'rate' => $rate,
                'line_total' => round(($type === 'other' ? $weightGm : $weightCts) * $rate, 2),
            ];
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function diamondRows(mixed $raw, int $orderId, int $karigarId): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $options = (new DiamondBagTraceService($this->db))->receiptOptions($karigarId, $orderId);
        $byIssueLine = [];
        foreach ($options as $option) {
            $byIssueLine[(int) ($option['issue_line_id'] ?? 0)] = $option;
        }

        $rows = [];
        $selected = [];
        foreach (array_values($raw) as $index => $input) {
            if (! is_array($input)) {
                continue;
            }
            $issueLineIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($input['issue_line_ids'] ?? [])),
                static fn(int $id): bool => $id > 0
            )));
            if ($issueLineIds === []) {
                $hasManualQuantity = (float) ($input['pcs'] ?? 0) > 0 || (float) ($input['weight_cts'] ?? 0) > 0;
                if ($hasManualQuantity || trim((string) ($input['name'] ?? '')) !== '') {
                    throw new RuntimeException('Diamond line ' . ($index + 1) . ' must use this order\'s issued bag lines.');
                }
                continue;
            }

            $description = mb_substr(trim((string) ($input['name'] ?? '')), 0, 120);
            $enteredRate = round(max(0, (float) ($input['rate'] ?? 0)), 2);
            foreach ($issueLineIds as $issueLineId) {
                if (isset($selected[$issueLineId])) {
                    throw new RuntimeException('A diamond bag line can be selected only once.');
                }
                $option = $byIssueLine[$issueLineId] ?? null;
                if (! is_array($option)) {
                    throw new RuntimeException('Selected diamond bag line is not available for this order.');
                }
                $selected[$issueLineId] = true;
                $pcs = round((float) ($option['available_pcs'] ?? 0), 3);
                $weightCts = round((float) ($option['available_cts'] ?? 0), 3);
                $rate = $enteredRate > 0
                    ? $enteredRate
                    : round(max(0, (float) ($option['rate_per_carat'] ?? 0)), 2);
                if ($weightCts <= 0) {
                    throw new RuntimeException('Selected diamond bag line has no available carat weight.');
                }
                $rows[] = [
                    'name' => $description !== '' ? $description : mb_substr((string) ($option['label'] ?? 'Diamond'), 0, 120),
                    'pcs' => $pcs,
                    'weight_cts' => $weightCts,
                    'weight_gm' => 0.0,
                    'rate' => $rate,
                    'line_total' => round($weightCts * $rate, 2),
                    'diamond_issue_line_id' => $issueLineId,
                    'diamond_bag_id' => (int) ($option['bag_id'] ?? 0),
                    'diamond_bag_item_id' => (int) ($option['bag_item_id'] ?? 0),
                ];
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    private function insertDetails(int $movementId, int $orderId, array $rows, string $type, int $createdBy): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $detail = [
                'movement_id' => $movementId,
                'order_id' => $orderId,
                'component_type' => $type,
                'component_name' => (string) $row['name'],
                'pcs' => (float) $row['pcs'],
                'weight_cts' => (float) $row['weight_cts'],
                'weight_gm' => (float) $row['weight_gm'],
                'rate' => (float) $row['rate'],
                'line_total' => (float) $row['line_total'],
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($type === 'diamond' && $this->db->fieldExists('diamond_issue_line_id', 'order_receive_details')) {
                $detail += [
                    'diamond_issue_line_id' => (int) ($row['diamond_issue_line_id'] ?? 0) ?: null,
                    'diamond_bag_id' => (int) ($row['diamond_bag_id'] ?? 0) ?: null,
                    'diamond_bag_item_id' => (int) ($row['diamond_bag_item_id'] ?? 0) ?: null,
                ];
            }
            $this->db->table('order_receive_details')->insert($detail);
            $receiveDetailId = (int) $this->db->insertID();
            if ($type === 'diamond' && $receiveDetailId > 0) {
                (new DiamondBagTraceService($this->db))->recordStudding($receiveDetailId);
            }
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private function sum(array $rows, string $field, int $precision = 3): float
    {
        return round(array_sum(array_map(static fn(array $row): float => (float) ($row[$field] ?? 0), $rows)), $precision);
    }

    private function saveFinishPhoto(string $input, int $orderId, int $createdBy): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $extension = 'jpg';
        if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $input, $match) === 1) {
            $extension = strtolower((string) $match[1]);
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            $input = substr($input, strpos($input, ',') + 1);
        }
        $binary = base64_decode(str_replace(' ', '+', $input), true);
        if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
            throw new RuntimeException('Finish photo must be a valid image up to 8 MB.');
        }
        $directory = FCPATH . 'uploads/orders';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Finish photo directory is unavailable.');
        }
        $name = 'receive_' . $orderId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $absolute = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($absolute, $binary) === false) {
            throw new RuntimeException('Finish photo could not be saved.');
        }
        $this->db->table('order_attachments')->insert([
            'order_id' => $orderId,
            'order_item_id' => null,
            'file_type' => 'finish_photo',
            'file_name' => $name,
            'file_path' => 'uploads/orders/' . $name,
            'uploaded_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $absolute;
    }
}
