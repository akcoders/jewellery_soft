<?php

namespace App\Services\StoneInventory;

use CodeIgniter\Database\BaseConnection;
use App\Services\OrderReceivingService;

/**
 * Stone issues are order-wise: every stone issue header carries the order it
 * was issued for (stone_inventory_issue_headers.order_id). This service keeps
 * the order picker and validation identical across admin, combined material
 * issue and the mobile API.
 */
class StoneIssueOrderService
{
    /** Completion alone does not close issuance; recorded receiving does. */
    private const CLOSED_STATUSES = ['Cancelled'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Orders that stone can be issued against. The currently linked order is
     * always included so an existing issue can still be edited.
     *
     * @return list<array{id:int,order_no:string,order_name:string,customer_name:string,status:string}>
     */
    public function orderOptions(int $includeOrderId = 0): array
    {
        $builder = $this->db->table('orders o')
            ->select('o.id, o.order_no, o.status, c.name as customer_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->orderBy('o.id', 'DESC');
        if ($this->db->fieldExists('order_name', 'orders')) {
            $builder->select('o.order_name');
        }
        if ($this->db->fieldExists('deleted_at', 'orders')) {
            $builder->where('o.deleted_at', null);
        }
        $includedOrder = $includeOrderId > 0
            ? (clone $builder)->where('o.id', $includeOrderId)->get()->getRowArray()
            : null;
        $builder->whereNotIn('o.status', self::CLOSED_STATUSES);
        (new OrderReceivingService($this->db))->excludeRecordedReceipts($builder, 'o.id');
        if ($includedOrder !== null) {
            $builder->where('o.id !=', $includeOrderId);
        }
        $orders = $builder->limit($includedOrder !== null ? 999 : 1000)->get()->getResultArray();
        if ($includedOrder !== null) {
            // Reserve a place for the existing allocation even when it is older
            // than every eligible order in a full picker.
            $orders[] = $includedOrder;
            usort($orders, static fn(array $left, array $right): int => (int) $right['id'] <=> (int) $left['id']);
        }

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'order_no' => (string) ($row['order_no'] ?? ''),
            'order_name' => (string) ($row['order_name'] ?? ''),
            'customer_name' => (string) ($row['customer_name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
        ], $orders);
    }

    /**
     * Stone issued against one order, newest first, with line totals.
     *
     * @return list<array<string,mixed>>
     */
    public function issuesForOrder(int $orderId): array
    {
        if ($orderId <= 0 || ! $this->db->tableExists('stone_inventory_issue_headers')) {
            return [];
        }

        $rows = $this->db->table('stone_inventory_issue_headers ih')
            ->select('ih.id, ih.voucher_no, ih.issue_date, ih.purpose, k.name as karigar_name, COUNT(il.id) as line_count, COALESCE(SUM(il.pcs), 0) as total_pcs, COALESCE(SUM(il.qty), 0) as total_qty, COALESCE(SUM(il.line_value), 0) as total_value', false)
            ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.order_id', $orderId)
            ->groupBy('ih.id')
            ->orderBy('ih.issue_date', 'DESC')
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'voucher_no' => (string) ($row['voucher_no'] ?? ''),
            'issue_date' => (string) ($row['issue_date'] ?? ''),
            'purpose' => (string) ($row['purpose'] ?? ''),
            'karigar_name' => (string) ($row['karigar_name'] ?? ''),
            'line_count' => (int) ($row['line_count'] ?? 0),
            'total_pcs' => round((float) ($row['total_pcs'] ?? 0), 3),
            'total_qty' => round((float) ($row['total_qty'] ?? 0), 3),
            'total_value' => round((float) ($row['total_value'] ?? 0), 2),
        ], $rows);
    }

    /** Human label used in pickers, vouchers and lists. */
    public static function label(array $order): string
    {
        $parts = [(string) ($order['order_no'] ?? '')];
        foreach (['order_name', 'customer_name'] as $key) {
            if (trim((string) ($order[$key] ?? '')) !== '') {
                $parts[] = trim((string) $order[$key]);
            }
        }

        return implode(' · ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }

    /**
     * Returns an error message, or null when the order may receive stone.
     * $currentOrderId lets an edit keep its existing (possibly closed) order.
     */
    public function validate(int $orderId, int $currentOrderId = 0): ?string
    {
        if ($orderId <= 0) {
            return 'Select the order this stone is issued for.';
        }
        $builder = $this->db->table('orders')->select('id, status')->where('id', $orderId);
        if ($this->db->fieldExists('deleted_at', 'orders')) {
            $builder->where('deleted_at', null);
        }
        $order = $builder->get()->getRowArray();
        if (! $order) {
            return 'Selected order for the stone issue was not found.';
        }
        if ($orderId !== $currentOrderId && (new OrderReceivingService($this->db))->hasRecordedReceipt($orderId)) {
            return 'Ornament already received; diamond or stone issuance for this order is not possible.';
        }
        if ($orderId !== $currentOrderId && in_array((string) ($order['status'] ?? ''), self::CLOSED_STATUSES, true)) {
            return 'Stone cannot be issued against a ' . strtolower((string) $order['status']) . ' order.';
        }

        return null;
    }
}
