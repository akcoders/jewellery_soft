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
     * Completed production can still need material before finished jewellery is received.
     *
     * @return list<array{id:int,order_no:string,order_name:string,status:string}>
     */
    public function orderOptions(): array
    {
        $builder = $this->db->table('orders')
            ->select('id, order_no, order_name, status')
            ->where('status !=', 'Cancelled')
            ->orderBy('id', 'DESC')
            ->limit(1000);
        if ($this->db->fieldExists('deleted_at', 'orders')) {
            $builder->where('deleted_at', null);
        }
        (new OrderReceivingService($this->db))->excludeRecordedReceipts($builder, 'orders.id');
        $orders = $builder->get()->getResultArray();

        return array_map(static fn(array $order): array => [
            'id' => (int) ($order['id'] ?? 0),
            'order_no' => (string) ($order['order_no'] ?? ''),
            'order_name' => (string) ($order['order_name'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
        ], $orders);
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
            && (string) ($order['status'] ?? '') === 'Cancelled') {
            return 'Diamond cannot be issued against a ' . strtolower((string) $order['status']) . ' order.';
        }

        return null;
    }
}
