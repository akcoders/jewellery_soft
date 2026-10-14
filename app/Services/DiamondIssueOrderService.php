<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DiamondIssueOrderService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array{id:int,order_no:string,order_name:string}>
     */
    public function orderOptions(): array
    {
        $orders = $this->db->table('orders')
            ->select('id, order_no, order_name')
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->orderBy('id', 'DESC')
            ->limit(1000)
            ->get()
            ->getResultArray();
        $receivedIds = array_fill_keys(
            (new OrderReceivingService($this->db))->recordedReceiptOrderIds(
                array_map(static fn(array $order): int => (int) ($order['id'] ?? 0), $orders)
            ),
            true
        );

        return array_values(array_filter(
            array_map(static fn(array $order): array => [
                'id' => (int) ($order['id'] ?? 0),
                'order_no' => (string) ($order['order_no'] ?? ''),
                'order_name' => (string) ($order['order_name'] ?? ''),
            ], $orders),
            static fn(array $order): bool => ! isset($receivedIds[$order['id']])
        ));
    }

    public function validate(int $orderId, int $currentOrderId = 0): ?string
    {
        if ($orderId <= 0) {
            return null;
        }

        $builder = $this->db->table('orders')
            ->select('id, status')
            ->where('id', $orderId);
        if ($this->db->fieldExists('deleted_at', 'orders')) {
            $builder->where('deleted_at', null);
        }
        $order = $builder->get()->getRowArray();
        if (! $order) {
            return 'Selected diamond order allocation does not exist.';
        }

        if ($orderId !== $currentOrderId
            && (new OrderReceivingService($this->db))->hasRecordedReceipt($orderId)) {
            return 'Ornament already received; diamond or stone issuance for this order is not possible.';
        }
        if ($orderId !== $currentOrderId
            && in_array((string) ($order['status'] ?? ''), ['Cancelled', 'Completed'], true)) {
            return 'Diamond cannot be issued against a ' . strtolower((string) $order['status']) . ' order.';
        }

        return null;
    }
}
