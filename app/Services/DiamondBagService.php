<?php

namespace App\Services;

use App\Models\DiamondBagItemModel;
use App\Models\DiamondBagModel;
use App\Models\InventoryLocationModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Creates order-linked diamond bags without the retired requirement workflow. */
class DiamondBagService
{
    private const EPSILON = 0.0005;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function supportsOrder(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $order = $this->db->table('orders o')
            ->select('o.*, oc.name AS order_category_name, oc.code AS order_category_code')
            ->join('order_categories oc', 'oc.id = o.order_category_id', 'left')
            ->where('o.id', $orderId)
            ->get()->getRowArray();
        if (! is_array($order)) {
            return false;
        }

        $category = strtolower(trim(implode(' ', [
            (string) ($order['material_category'] ?? ''),
            (string) ($order['order_category_name'] ?? ''),
            (string) ($order['order_category_code'] ?? ''),
        ])));
        if (str_contains($category, 'diamond')
            || str_contains($category, 'jadau')
            || preg_match('/\bdia\b/', $category) === 1) {
            return true;
        }

        if ($this->db->fieldExists('diamond_required_cts', 'order_items')
            && $this->db->table('order_items')->where('order_id', $orderId)
                ->where('diamond_required_cts >', 0)->countAllResults() > 0) {
            return true;
        }

        return $this->db->tableExists('design_masters')
            && $this->db->fieldExists('diamond_weight_cts', 'design_masters')
            && $this->db->table('order_items oi')
                ->join('design_masters dm', 'dm.id = oi.design_id', 'inner')
                ->where('oi.order_id', $orderId)
                ->where('dm.diamond_weight_cts >', 0)
                ->countAllResults() > 0;
    }

    public function canCreateForOrder(
        int $orderId,
        int $userId,
        bool $canManage,
        int $workRequestId = 0
    ): bool {
        if ($userId <= 0 || ! $this->supportsOrder($orderId)) {
            return false;
        }
        $order = $this->db->table('orders')->select('status, followup_assigned_to')
            ->where('id', $orderId)->get()->getRowArray();
        if (! is_array($order) || $this->isClosedStatus((string) ($order['status'] ?? ''))) {
            return false;
        }
        if ($canManage || (int) ($order['followup_assigned_to'] ?? 0) === $userId) {
            return true;
        }
        if ($workRequestId <= 0 || ! $this->db->tableExists('order_work_requests')) {
            return false;
        }
        return $this->db->table('order_work_requests')
            ->where('id', $workRequestId)
            ->where('order_id', $orderId)
            ->where('request_type', 'diamond_requirement')
            ->where('status', 'approved')
            ->where('assigned_to', $userId)
            ->countAllResults() > 0;
    }

    /** @return list<array<string,mixed>> */
    public function availableOrders(int $userId, bool $canManage): array
    {
        $builder = $this->db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.status, o.material_category, o.followup_assigned_to, oc.name AS order_category_name, oc.code AS order_category_code')
            ->join('order_categories oc', 'oc.id = o.order_category_id', 'left')
            ->whereNotIn('o.status', ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Completed', 'Complete', 'Cancelled']);
        if (! $canManage) {
            $builder->groupStart()
                ->where('o.followup_assigned_to', $userId);
            if ($this->db->tableExists('order_work_requests')) {
                $builder->orWhereIn('o.id', static function ($subquery) use ($userId) {
                    return $subquery->select('order_id')->from('order_work_requests')
                        ->where('request_type', 'diamond_requirement')
                        ->where('status', 'approved')
                        ->where('assigned_to', $userId);
                });
            }
            $builder->groupEnd();
        }

        $orders = $builder->orderBy('o.id', 'DESC')->limit(1000)->get()->getResultArray();
        return array_values(array_filter(
            $orders,
            fn(array $order): bool => $this->supportsOrder((int) ($order['id'] ?? 0))
        ));
    }

    /**
     * @return array{
     *   inventory_items:list<array<string,mixed>>,
     *   shapes:list<array<string,mixed>>,
     *   sizes:list<array<string,mixed>>,
     *   chalni_groups:list<array<string,mixed>>,
     *   locations:list<array<string,mixed>>
     * }
     */
    public function lookups(): array
    {
        return [
            'inventory_items' => $this->db->table('items i')
                ->select('i.id, i.diamond_type, i.shape, i.color, i.clarity, COALESCE(s.carat_balance,0) AS carat_balance, COALESCE((SELECT SUM(bi.weight_cts_available) FROM diamond_bag_items bi WHERE bi.inventory_item_id = i.id),0) AS bagged_cts', false)
                ->join('stock s', 's.item_id = i.id', 'left')
                ->orderBy('i.diamond_type', 'ASC')->get()->getResultArray(),
            'shapes' => $this->db->table('diamond_shape_masters')->where('is_active', 1)
                ->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray(),
            'sizes' => $this->db->table('diamond_size_masters sz')
                ->select('sz.*, gs.group_id')
                ->join('diamond_chalni_group_sizes gs', 'gs.size_id = sz.id', 'left')
                ->where('sz.is_active', 1)->orderBy('sz.sort_order', 'ASC')
                ->orderBy('sz.size_label', 'ASC')->get()->getResultArray(),
            'chalni_groups' => $this->db->table('diamond_chalni_groups')
                ->where('is_active', 1)->orderBy('sort_order', 'ASC')
                ->orderBy('name', 'ASC')->get()->getResultArray(),
            'locations' => (new InventoryLocationModel())->where('is_active', 1)
                ->orderBy('name', 'ASC')->findAll(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $inputRows
     * @return array<string,mixed>
     */
    public function create(
        int $orderId,
        int $preparedBy,
        int $locationId,
        string $preparedDate,
        array $inputRows,
        string $notes = '',
        ?string $imageName = null,
        ?string $imagePath = null,
        int $workRequestId = 0
    ): array {
        if (! $this->supportsOrder($orderId)) {
            throw new RuntimeException('Diamond bags are available only for Diamond or Jadau orders.');
        }
        if ($this->validDate($preparedDate) === null) {
            throw new RuntimeException('A valid prepared date is required.');
        }
        $location = (new InventoryLocationModel())->where('is_active', 1)->find($locationId);
        if (! is_array($location)) {
            throw new RuntimeException('Select a valid inventory location.');
        }
        $rows = $this->normalizeRows($inputRows);
        $this->assertPackable($rows);
        $warehouse = (new AdminPostingService())->resolveWarehouseBinByLocation($locationId);
        $bags = new DiamondBagModel();
        $bagItems = new DiamondBagItemModel();

        try {
            $this->db->transException(true)->transStart();
            $workRequest = null;
            if ($workRequestId > 0) {
                $workRequest = $this->db->query(
                    'SELECT * FROM order_work_requests WHERE id = ? FOR UPDATE',
                    [$workRequestId]
                )->getRowArray();
                if (! is_array($workRequest)
                    || (int) ($workRequest['order_id'] ?? 0) !== $orderId
                    || (string) ($workRequest['request_type'] ?? '') !== 'diamond_requirement'
                    || (string) ($workRequest['status'] ?? '') !== 'approved'
                    || (int) ($workRequest['assigned_to'] ?? 0) !== $preparedBy) {
                    throw new RuntimeException('This diamond bag task is unavailable or assigned to another staff member.');
                }
            }

            $bagNo = $this->nextBagNumber($bags);
            $bagId = (int) $bags->insert([
                'bag_no' => $bagNo,
                'prepared_date' => $preparedDate,
                'order_id' => $orderId,
                'requirement_id' => null,
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'bin_id' => (int) $warehouse['bin_id'],
                'pcs_balance' => 0,
                'cts_balance' => 0,
                'notes' => trim($notes) ?: null,
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
                $bagItemId = (int) $bagItems->insert([
                    'bag_id' => $bagId,
                    'inventory_item_id' => $row['inventory_item_id'],
                    'shape_master_id' => $row['shape_master_id'],
                    'size_master_id' => $row['size_master_id'],
                    'chalni_group_id' => $row['chalni_group_id'],
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
                        'order_id' => $orderId,
                        'pcs' => (int) $row['pcs'],
                        'carat' => (float) $row['weight_cts'],
                        'notes' => 'Prepared directly for order #' . $orderId,
                        'created_by' => $preparedBy,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            $bags->update($bagId, [
                'pcs_balance' => $totalPcs,
                'cts_balance' => round($totalCts, 3),
            ]);

            if ($workRequestId > 0) {
                $now = date('Y-m-d H:i:s');
                $this->db->table('order_work_requests')->where('id', $workRequestId)
                    ->where('status', 'approved')->update([
                        'status' => 'fulfilled',
                        'completed_at' => $now,
                        'voucher_no' => $bagNo,
                        'updated_at' => $now,
                    ]);
                if ($this->db->affectedRows() !== 1) {
                    throw new RuntimeException('This diamond bag task was already completed.');
                }
                (new WorkflowTaskService($this->db))->complete(
                    'order_diamond_bag',
                    $workRequestId,
                    $preparedBy
                );
            }
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->db->table('diamond_bags')->where('id', $bagId)->get()->getRowArray()
            ?? ['id' => $bagId, 'bag_no' => $bagNo];
    }

    /** @param list<array<string,mixed>> $inputRows @return list<array<string,mixed>> */
    private function normalizeRows(array $inputRows): array
    {
        $rows = [];
        foreach ($inputRows as $index => $input) {
            $itemId = (int) ($input['inventory_item_id'] ?? $input['item_id'] ?? 0);
            $shapeId = (int) ($input['shape_master_id'] ?? $input['shape_id'] ?? 0);
            $sizeId = (int) ($input['size_master_id'] ?? $input['size_id'] ?? 0);
            $groupId = (int) ($input['chalni_group_id'] ?? $input['group_id'] ?? 0);
            $pcs = (float) ($input['pcs'] ?? 0);
            $cts = round((float) ($input['weight_cts'] ?? $input['carat'] ?? $input['cts'] ?? 0), 3);
            if ($itemId <= 0 && $shapeId <= 0 && $groupId <= 0 && $sizeId <= 0 && $pcs <= 0 && $cts <= 0) {
                continue;
            }
            if ($itemId <= 0 || $shapeId <= 0 || $groupId <= 0 || $pcs <= 0 || floor($pcs) !== $pcs || $cts <= 0) {
                throw new RuntimeException('Row ' . ($index + 1) . ': diamond item, shape, chalni group, whole PCS and positive CTS are required.');
            }
            $item = $this->db->table('items')->where('id', $itemId)->get()->getRowArray();
            $shape = $this->db->table('diamond_shape_masters')->where('id', $shapeId)
                ->where('is_active', 1)->get()->getRowArray();
            $group = $this->db->table('diamond_chalni_groups')->where('id', $groupId)
                ->where('is_active', 1)->get()->getRowArray();
            $size = $sizeId > 0 ? $this->db->table('diamond_size_masters sz')
                ->select('sz.*')->join('diamond_chalni_group_sizes gs', 'gs.size_id = sz.id', 'inner')
                ->where('sz.id', $sizeId)->where('sz.shape_id', $shapeId)
                ->where('gs.group_id', $groupId)->where('sz.is_active', 1)->get()->getRowArray() : null;
            if (! $item || ! $shape || ! $group || ($sizeId > 0 && ! $size)) {
                throw new RuntimeException('Row ' . ($index + 1) . ': selected diamond item, shape, group or exact size is invalid.');
            }
            $rows[] = [
                'inventory_item_id' => $itemId,
                'shape_master_id' => $shapeId,
                'size_master_id' => $sizeId > 0 ? $sizeId : null,
                'chalni_group_id' => $groupId,
                'diamond_type' => trim((string) ($item['diamond_type'] ?? '')) ?: 'Diamond',
                'size' => (string) (($size['size_label'] ?? '') ?: ($group['name'] ?? $group['range_label'] ?? '')),
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
            $requested[$itemId] = round(
                (float) ($requested[$itemId] ?? 0) + (float) $row['weight_cts'],
                3
            );
        }
        foreach ($requested as $itemId => $cts) {
            $stock = $this->db->table('stock')->select('carat_balance')
                ->where('item_id', $itemId)->get()->getRowArray();
            $reserved = $this->db->table('diamond_bag_items')
                ->select('COALESCE(SUM(weight_cts_available),0) AS cts', false)
                ->where('inventory_item_id', $itemId)->get()->getRowArray();
            $available = round(
                (float) ($stock['carat_balance'] ?? 0) - (float) ($reserved['cts'] ?? 0),
                3
            );
            if ($cts > ($available + self::EPSILON)) {
                throw new RuntimeException('Bag quantity exceeds unbagged diamond stock. Available: ' . number_format(max(0, $available), 3) . ' cts.');
            }
        }
        (new DiamondChalniStockService($this->db))->assertPackable($rows);
    }

    private function nextBagNumber(DiamondBagModel $bags): string
    {
        $prefix = 'DBAG-' . date('ymd') . '-';
        $rows = $bags->select('bag_no')->like('bag_no', $prefix, 'after')->findAll();
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
            return null;
        }
        return date('Y-m-d', $time);
    }

    private function isClosedStatus(string $status): bool
    {
        return in_array($status, [
            'Ready', 'Packed', 'Dispatched', 'Delivered', 'Completed', 'Complete', 'Cancelled',
        ], true);
    }
}
