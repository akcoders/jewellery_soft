<?php

namespace App\Services;

use App\Models\DiamondBagItemModel;
use App\Models\DiamondBagModel;
use App\Models\DiamondRequirementModel;
use App\Models\InventoryLocationModel;
use RuntimeException;
use Throwable;

class DiamondRequirementService
{
    private const EPSILON = 0.0005;

    private $db;
    private DiamondRequirementModel $requirements;
    private DiamondBagModel $bags;
    private DiamondBagItemModel $bagItems;

    public function __construct()
    {
        $this->db = db_connect();
        $this->requirements = new DiamondRequirementModel();
        $this->bags = new DiamondBagModel();
        $this->bagItems = new DiamondBagItemModel();
    }

    public function ready(): bool
    {
        return $this->db->tableExists('diamond_requirements')
            && $this->db->tableExists('diamond_bags')
            && $this->db->fieldExists('requirement_id', 'diamond_bags');
    }

    /** @return list<array<string,mixed>> */
    public function forOrder(int $orderId): array
    {
        if (! $this->ready() || $orderId <= 0) {
            return [];
        }

        return $this->baseQuery()
            ->where('dr.order_id', $orderId)
            ->orderBy('dr.id', 'DESC')
            ->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    public function forAdmin(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return $this->baseQuery()
            ->orderBy("CASE dr.status WHEN 'pending_approval' THEN 0 WHEN 'assigned' THEN 1 WHEN 'bag_ready' THEN 2 WHEN 'issued' THEN 3 ELSE 4 END", 'ASC', false)
            ->orderBy('dr.id', 'DESC')
            ->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    public function forMobileUser(int $userId): array
    {
        if (! $this->ready() || $userId <= 0) {
            return [];
        }

        return $this->baseQuery()
            ->groupStart()
                ->where('dr.assigned_to', $userId)
                ->orWhere('dr.requested_by', $userId)
                ->orWhere('o.followup_assigned_to', $userId)
            ->groupEnd()
            ->orderBy("CASE dr.status WHEN 'assigned' THEN 0 WHEN 'pending_approval' THEN 1 WHEN 'bag_ready' THEN 2 WHEN 'issued' THEN 3 ELSE 4 END", 'ASC', false)
            ->orderBy('dr.id', 'DESC')
            ->get()->getResultArray();
    }

    /** @return array<string,mixed>|null */
    public function findDetailed(int $id): ?array
    {
        if (! $this->ready() || $id <= 0) {
            return null;
        }
        $row = $this->baseQuery()->where('dr.id', $id)->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    public function canRaise(int $orderId, int $userId, bool $canManage = false): bool
    {
        if (! $this->ready() || $orderId <= 0 || $userId <= 0 || ! $this->isDiamondOrder($orderId)) {
            return false;
        }
        $hasActive = $this->requirements->where('order_id', $orderId)
            ->whereIn('status', ['pending_approval', 'assigned', 'bag_ready'])
            ->countAllResults() > 0;
        if ($hasActive) {
            return false;
        }
        if ($canManage) {
            return true;
        }
        $order = $this->db->table('orders')->select('followup_assigned_to')->where('id', $orderId)->get()->getRowArray();
        return is_array($order) && (int) ($order['followup_assigned_to'] ?? 0) === $userId;
    }

    /** @return array<string,mixed> */
    public function raise(int $orderId, int $userId, string $note = '', ?string $requiredBy = null, bool $canManage = false): array
    {
        if (! $this->canRaise($orderId, $userId, $canManage)) {
            throw new RuntimeException('Only the assigned order follower can raise a requirement for a diamond order.');
        }
        $active = $this->requirements->where('order_id', $orderId)
            ->whereIn('status', ['pending_approval', 'assigned', 'bag_ready'])
            ->first();
        if (is_array($active)) {
            throw new RuntimeException('An active diamond requirement already exists for this order.');
        }

        $requiredBy = $this->validDate($requiredBy);
        $id = (int) $this->requirements->insert([
            'requirement_no' => $this->nextRequirementNumber(),
            'order_id' => $orderId,
            'requested_by' => $userId,
            'requirement_note' => trim($note) ?: null,
            'required_by' => $requiredBy,
            'status' => 'pending_approval',
        ], true);
        if ($id <= 0) {
            throw new RuntimeException('Could not raise the diamond requirement.');
        }

        return $this->findDetailed($id) ?? ['id' => $id];
    }

    /** @return array<string,mixed> */
    public function approveAndAssign(int $id, int $approvedBy, int $assignedTo, ?string $dueAt, string $note = ''): array
    {
        $row = $this->findDetailed($id);
        if (! is_array($row)) {
            throw new RuntimeException('Diamond requirement not found.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending_approval') {
            throw new RuntimeException('Only a pending requirement can be approved.');
        }
        $assignee = $this->db->table('admin_users')->select('id')->where('id', $assignedTo)->where('is_active', 1)->get()->getRowArray();
        if (! $assignee) {
            throw new RuntimeException('Select an active staff member for bag preparation.');
        }

        $due = $this->validDateTime($dueAt);
        $now = date('Y-m-d H:i:s');
        $this->requirements->update($id, [
            'status' => 'assigned',
            'approved_by' => $approvedBy,
            'approved_at' => $now,
            'assigned_to' => $assignedTo,
            'assigned_by' => $approvedBy,
            'assigned_at' => $now,
            'preparation_due_at' => $due,
            'approval_note' => trim($note) ?: null,
        ]);

        return $this->findDetailed($id) ?? ['id' => $id];
    }

    /** @return array<string,mixed> */
    public function reject(int $id, int $userId, string $reason): array
    {
        $row = $this->findDetailed($id);
        if (! is_array($row)) {
            throw new RuntimeException('Diamond requirement not found.');
        }
        if (! in_array((string) ($row['status'] ?? ''), ['pending_approval', 'assigned'], true)) {
            throw new RuntimeException('This requirement can no longer be rejected.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Rejection reason is required.');
        }
        $this->requirements->update($id, [
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => trim($reason),
        ]);
        return $this->findDetailed($id) ?? ['id' => $id];
    }

    /**
     * @param list<array<string,mixed>> $inputRows
     * @return array<string,mixed>
     */
    public function prepareBag(
        int $requirementId,
        int $preparedBy,
        int $locationId,
        string $preparedDate,
        array $inputRows,
        string $notes = '',
        ?string $imageName = null,
        ?string $imagePath = null
    ): array {
        $requirement = $this->findDetailed($requirementId);
        if (! is_array($requirement)) {
            throw new RuntimeException('Diamond requirement not found.');
        }
        if ((string) ($requirement['status'] ?? '') !== 'assigned') {
            throw new RuntimeException('Only an assigned requirement can be prepared as a bag.');
        }
        if ((int) ($requirement['assigned_to'] ?? 0) !== $preparedBy) {
            throw new RuntimeException('This bag preparation is assigned to another staff member.');
        }
        if ($this->validDate($preparedDate) === null) {
            throw new RuntimeException('A valid prepared date is required.');
        }
        $location = (new InventoryLocationModel())->where('is_active', 1)->find($locationId);
        if (! is_array($location)) {
            throw new RuntimeException('Select a valid inventory location.');
        }
        $rows = $this->normalizeBagRows($inputRows);
        $this->assertPackable($rows);
        $warehouse = (new AdminPostingService())->resolveWarehouseBinByLocation($locationId);

        try {
            $this->db->transException(true)->transStart();
            $current = $this->db->table('diamond_requirements')->where('id', $requirementId)->get()->getRowArray();
            if (! is_array($current) || (string) ($current['status'] ?? '') !== 'assigned' || (int) ($current['bag_id'] ?? 0) > 0) {
                throw new RuntimeException('This requirement was already processed. Refresh and try again.');
            }
            $this->db->table('diamond_requirements')
                ->where('id', $requirementId)
                ->where('status', 'assigned')
                ->where('bag_id IS NULL', null, false)
                ->update([
                    'status' => 'preparing',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('This requirement is already being prepared. Refresh and try again.');
            }

            $bagId = (int) $this->bags->insert([
                'bag_no' => $this->nextBagNumber(),
                'prepared_date' => $preparedDate,
                'order_id' => (int) $requirement['order_id'],
                'requirement_id' => $requirementId,
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'bin_id' => (int) $warehouse['bin_id'],
                'pcs_balance' => 0,
                'cts_balance' => 0,
                'notes' => trim($notes) ?: ('Prepared for ' . (string) ($requirement['requirement_no'] ?? ('requirement #' . $requirementId))),
                'audit_image_name' => trim((string) $imageName) ?: null,
                'audit_image_path' => trim((string) $imagePath) ?: null,
                'created_by' => $preparedBy,
            ], true);
            if ($bagId <= 0) {
                throw new RuntimeException('Could not create the diamond bag.');
            }

            $totalPcs = 0;
            $totalCts = 0.0;
            foreach ($rows as $row) {
                $bagItemId = (int) $this->bagItems->insert([
                    'bag_id' => $bagId,
                    'inventory_item_id' => $row['inventory_item_id'],
                    'shape_master_id' => $row['shape_master_id'],
                    'size_master_id' => $row['size_master_id'],
                    'diamond_type' => $row['diamond_type'],
                    'size' => $row['size'],
                    'color' => $row['color'],
                    'quality' => $row['quality'],
                    'pcs_total' => $row['pcs'],
                    'weight_cts_total' => $row['weight_cts'],
                    'pcs_available' => $row['pcs'],
                    'weight_cts_available' => $row['weight_cts'],
                ], true);
                $totalPcs += (int) $row['pcs'];
                $totalCts += (float) $row['weight_cts'];
                if ($bagItemId > 0 && $this->db->tableExists('diamond_bag_movements')) {
                    $this->db->table('diamond_bag_movements')->insert([
                        'movement_date' => $preparedDate,
                        'movement_type' => 'PREPARED',
                        'bag_id' => $bagId,
                        'bag_item_id' => $bagItemId,
                        'order_id' => (int) $requirement['order_id'],
                        'pcs' => (int) $row['pcs'],
                        'carat' => (float) $row['weight_cts'],
                        'notes' => 'Prepared from approved diamond requirement ' . (string) $requirement['requirement_no'],
                        'created_by' => $preparedBy,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            $this->bags->update($bagId, [
                'pcs_balance' => $totalPcs,
                'cts_balance' => round($totalCts, 3),
            ]);
            $this->requirements->update($requirementId, [
                'status' => 'bag_ready',
                'bag_id' => $bagId,
                'ready_by' => $preparedBy,
                'ready_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->findDetailed($requirementId) ?? ['id' => $requirementId];
    }

    /**
     * @return array{
     *   inventory_items:list<array<string,mixed>>,
     *   shapes:list<array<string,mixed>>,
     *   sizes:list<array<string,mixed>>,
     *   locations:list<array<string,mixed>>
     * }
     */
    public function bagItems(int $requirementId): array
    {
        $requirement = $this->findDetailed($requirementId);
        $bagId = (int) ($requirement['bag_id'] ?? 0);
        if ($bagId <= 0) {
            return [];
        }
        return $this->db->table('diamond_bag_items bi')
            ->select('bi.*, i.diamond_type, sm.name AS shape_name, sz.size_code, sz.size_label')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->where('bi.bag_id', $bagId)->orderBy('bi.id', 'ASC')->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    public function preparationLookups(): array
    {
        return [
            'inventory_items' => $this->db->table('items i')
                ->select('i.id, i.diamond_type, i.shape, i.color, i.clarity, COALESCE(s.carat_balance,0) AS carat_balance, COALESCE((SELECT SUM(bi.weight_cts_available) FROM diamond_bag_items bi WHERE bi.inventory_item_id = i.id),0) AS bagged_cts', false)
                ->join('stock s', 's.item_id = i.id', 'left')
                ->orderBy('i.diamond_type', 'ASC')->get()->getResultArray(),
            'shapes' => $this->db->table('diamond_shape_masters')->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray(),
            'sizes' => $this->db->table('diamond_size_masters')->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('size_label', 'ASC')->get()->getResultArray(),
            'locations' => (new InventoryLocationModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
        ];
    }

    private function baseQuery()
    {
        return $this->db->table('diamond_requirements dr')
            ->select('dr.*, o.order_no, o.order_name, o.order_category_id, o.assigned_karigar_id, o.followup_assigned_to, oc.name AS order_category_name, requester.name AS requester_name, assignee.name AS assignee_name, approver.name AS approver_name, ready_user.name AS ready_by_name, b.bag_no, b.pcs_balance, b.cts_balance')
            ->join('orders o', 'o.id = dr.order_id', 'inner')
            ->join('order_categories oc', 'oc.id = o.order_category_id', 'left')
            ->join('admin_users requester', 'requester.id = dr.requested_by', 'left')
            ->join('admin_users assignee', 'assignee.id = dr.assigned_to', 'left')
            ->join('admin_users approver', 'approver.id = dr.approved_by', 'left')
            ->join('admin_users ready_user', 'ready_user.id = dr.ready_by', 'left')
            ->join('diamond_bags b', 'b.id = dr.bag_id', 'left');
    }

    private function isDiamondOrder(int $orderId): bool
    {
        $order = $this->db->table('orders o')
            ->select('o.id, oc.name, oc.code')
            ->join('order_categories oc', 'oc.id = o.order_category_id', 'left')
            ->where('o.id', $orderId)->get()->getRowArray();
        if (! is_array($order)) {
            return false;
        }
        $category = strtolower(trim((string) ($order['name'] ?? '') . ' ' . (string) ($order['code'] ?? '')));
        if (str_contains($category, 'diamond') || str_contains($category, 'dia')) {
            return true;
        }
        if ($this->db->fieldExists('diamond_required_cts', 'order_items')
            && $this->db->table('order_items')->where('order_id', $orderId)->where('diamond_required_cts >', 0)->countAllResults() > 0) {
            return true;
        }
        if ($this->db->tableExists('design_masters') && $this->db->fieldExists('diamond_weight_cts', 'design_masters')) {
            return $this->db->table('order_items oi')
                ->join('design_masters dm', 'dm.id = oi.design_id', 'inner')
                ->where('oi.order_id', $orderId)
                ->where('dm.diamond_weight_cts >', 0)
                ->countAllResults() > 0;
        }
        return false;
    }

    /** @param list<array<string,mixed>> $inputRows @return list<array<string,mixed>> */
    private function normalizeBagRows(array $inputRows): array
    {
        $rows = [];
        foreach ($inputRows as $index => $input) {
            $itemId = (int) ($input['inventory_item_id'] ?? $input['item_id'] ?? 0);
            $shapeId = (int) ($input['shape_master_id'] ?? $input['shape_id'] ?? 0);
            $sizeId = (int) ($input['size_master_id'] ?? $input['size_id'] ?? 0);
            $pcs = (float) ($input['pcs'] ?? 0);
            $cts = round((float) ($input['weight_cts'] ?? $input['carat'] ?? $input['cts'] ?? 0), 3);
            if ($itemId <= 0 && $shapeId <= 0 && $sizeId <= 0 && $pcs <= 0 && $cts <= 0) {
                continue;
            }
            if ($itemId <= 0 || $shapeId <= 0 || $sizeId <= 0 || $pcs <= 0 || floor($pcs) !== $pcs || $cts <= 0) {
                throw new RuntimeException('Row ' . ($index + 1) . ': diamond item, shape, size, whole PCS and positive CTS are required.');
            }
            $item = $this->db->table('items')->where('id', $itemId)->get()->getRowArray();
            $shape = $this->db->table('diamond_shape_masters')->where('id', $shapeId)->where('is_active', 1)->get()->getRowArray();
            $size = $this->db->table('diamond_size_masters')->where('id', $sizeId)->where('shape_id', $shapeId)->where('is_active', 1)->get()->getRowArray();
            if (! $item || ! $shape || ! $size) {
                throw new RuntimeException('Row ' . ($index + 1) . ': selected diamond item, shape or shape-wise size is invalid.');
            }
            $rows[] = [
                'inventory_item_id' => $itemId,
                'shape_master_id' => $shapeId,
                'size_master_id' => $sizeId,
                'diamond_type' => trim((string) ($item['diamond_type'] ?? '')) ?: 'Diamond',
                'size' => (string) (($size['size_label'] ?? '') ?: ($size['size_code'] ?? '')),
                'color' => trim((string) ($item['color'] ?? '')) ?: '-',
                'quality' => trim((string) ($item['clarity'] ?? '')) ?: '-',
                'pcs' => (int) $pcs,
                'weight_cts' => $cts,
            ];
        }
        if ($rows === []) {
            throw new RuntimeException('Add at least one diamond size row to prepare the bag.');
        }
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    private function assertPackable(array $rows): void
    {
        $requested = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['inventory_item_id'];
            $requested[$itemId] = round((float) ($requested[$itemId] ?? 0) + (float) $row['weight_cts'], 3);
        }
        foreach ($requested as $itemId => $cts) {
            $stock = $this->db->table('stock')->select('carat_balance')->where('item_id', $itemId)->get()->getRowArray();
            $reserved = $this->db->table('diamond_bag_items')
                ->select('COALESCE(SUM(weight_cts_available),0) AS cts', false)
                ->where('inventory_item_id', $itemId)->get()->getRowArray();
            $available = round((float) ($stock['carat_balance'] ?? 0) - (float) ($reserved['cts'] ?? 0), 3);
            if ($cts > ($available + self::EPSILON)) {
                throw new RuntimeException('Bag quantity exceeds unbagged diamond stock. Available: ' . number_format(max(0, $available), 3) . ' cts.');
            }
        }
        (new DiamondChalniStockService($this->db))->assertPackable($rows);
    }

    private function nextRequirementNumber(): string
    {
        $prefix = 'DREQ-' . date('ymd') . '-';
        $rows = $this->requirements->select('requirement_no')->like('requirement_no', $prefix, 'after')->findAll();
        $max = 0;
        foreach ($rows as $row) {
            if (preg_match('/(\d+)$/', (string) ($row['requirement_no'] ?? ''), $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextBagNumber(): string
    {
        $prefix = 'DBAG-' . date('ymd') . '-';
        $rows = $this->bags->select('bag_no')->like('bag_no', $prefix, 'after')->findAll();
        $max = 0;
        foreach ($rows as $row) {
            if (preg_match('/(\d+)$/', (string) ($row['bag_no'] ?? ''), $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function validDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        if ($time === false) {
            throw new RuntimeException('Invalid required date.');
        }
        return date('Y-m-d', $time);
    }

    private function validDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        if ($time === false) {
            throw new RuntimeException('Invalid bag preparation due date/time.');
        }
        return date('Y-m-d H:i:s', $time);
    }
}
