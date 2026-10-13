<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class OrderFamilyService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function root(int $orderId): ?array
    {
        $order = $this->db->table('orders')->where('id', $orderId)->where('deleted_at', null)->get()->getRowArray();
        if (! is_array($order)) return null;
        $parentId = (int) ($order['parent_order_id'] ?? 0);
        if ($parentId <= 0) return $order;
        $parent = $this->db->table('orders')->where('id', $parentId)->where('deleted_at', null)->get()->getRowArray();
        return is_array($parent) ? $parent : $order;
    }

    public function selectableParent(int $parentId, int $excludeId = 0): ?array
    {
        if ($parentId <= 0 || $parentId === $excludeId) return null;
        $row = $this->db->table('orders')->where('id', $parentId)->where('parent_order_id', null)
            ->where('deleted_at', null)->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    public function orders(int $orderId): array
    {
        $root = $this->root($orderId);
        if (! is_array($root)) return [];
        return $this->db->table('orders')
            ->select('id, order_no, order_name, parent_order_id, status, followup_assigned_to, followup_due_at')
            ->groupStart()->where('id', (int) $root['id'])->orWhere('parent_order_id', (int) $root['id'])->groupEnd()
            ->where('deleted_at', null)->orderBy('parent_order_id', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function ids(int $orderId): array
    {
        return array_values(array_map(static fn(array $row): int => (int) $row['id'], $this->orders($orderId)));
    }

    public function options(): array
    {
        return $this->db->table('orders o')->select('o.id, o.order_no, o.order_name, c.name customer_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')->where('o.parent_order_id', null)
            ->where('o.deleted_at', null)->whereNotIn('o.status', ['Cancelled', 'Completed', 'Complete', 'Delivered'])
            ->orderBy('o.id', 'DESC')->limit(500)->get()->getResultArray();
    }

    public function history(int $orderId): array
    {
        $ids = $this->ids($orderId);
        if ($ids === []) return [];
        $rows = $this->db->table('order_followups ofu')
            ->select('ofu.*, o.order_no source_order_no, o.order_name source_order_name, au.name followup_taken_by_name, reviewer.name reviewed_by_name')
            ->join('orders o', 'o.id = ofu.order_id', 'inner')
            ->join('admin_users au', 'au.id = ofu.followup_taken_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ofu.reviewed_by', 'left')
            ->whereIn('ofu.order_id', $ids)->orderBy('ofu.id', 'DESC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $path = trim((string) ($row['image_path'] ?? ''));
            $row['image_url'] = $path !== '' ? base_url($path) : null;
            $row['is_inherited'] = (int) ($row['order_id'] ?? 0) !== $orderId;
        }
        unset($row);
        return $rows;
    }

    public function syncFollower(int $orderId, ?int $followerId, ?string $dueAt, ?int $changedBy): int
    {
        $root = $this->root($orderId);
        if (! is_array($root)) throw new RuntimeException('Order not found.');
        $rootId = (int) $root['id'];
        (new StaffPerformanceService())->syncOrderAssignment($rootId, $followerId, $dueAt, $changedBy);
        $childIds = array_values(array_filter($this->ids($rootId), static fn(int $id): bool => $id !== $rootId));
        if ($childIds !== []) {
            foreach ($childIds as $childId) {
                (new StaffPerformanceService())->syncOrderAssignment($childId, null, null, $changedBy);
            }
            $this->db->table('orders')->whereIn('id', $childIds)->update([
                'followup_assigned_to' => $followerId, 'followup_due_at' => $dueAt, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $rootId;
    }
}
