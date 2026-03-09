<?php

namespace App\Controllers\Api;

class OrdersController extends ApiBaseController
{
    public function index()
    {
        $rows = db_connect()->table('orders')->orderBy('id', 'DESC')->get(200)->getResultArray();
        return $this->ok($rows);
    }

    public function show(int $id)
    {
        $db = db_connect();
        $order = $db->table('orders')->where('id', $id)->get()->getRowArray();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }
        $items = $db->table('order_items')->where('order_id', $id)->get()->getResultArray();
        return $this->ok(['order' => $order, 'items' => $items]);
    }

    public function create()
    {
        $payload = $this->payload();
        $db = db_connect();

        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return $this->fail('customer_id is required.', 422);
        }

        $orderNo = trim((string) ($payload['order_no'] ?? ''));
        if ($orderNo === '') {
            $orderNo = 'SO-' . date('YmdHis');
        }

        $status = trim((string) ($payload['status'] ?? 'Draft'));
        $dueDate = trim((string) ($payload['due_date'] ?? date('Y-m-d')));
        $priority = (string) ($payload['priority'] ?? 'Normal');

        $db->transStart();
        $orderId = (int) $db->table('orders')->insert([
            'order_no' => $orderNo,
            'customer_id' => $customerId,
            'due_date' => $dueDate,
            'priority' => $priority,
            'status' => $status,
            'order_type' => (string) ($payload['order_type'] ?? 'Sales'),
            'expected_diamond_spec' => isset($payload['expected_diamond_spec']) ? json_encode($payload['expected_diamond_spec']) : null,
            'expected_stone_spec' => isset($payload['expected_stone_spec']) ? json_encode($payload['expected_stone_spec']) : null,
            'priority_level' => (int) ($payload['priority_level'] ?? 0),
            'order_notes' => (string) ($payload['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $items = (array) ($payload['items'] ?? []);
        $i = 0;
        foreach ($items as $item) {
            $i++;
            $db->table('order_items')->insert([
                'order_id' => $orderId,
                'design_id' => isset($item['design_id']) ? (int) $item['design_id'] : null,
                'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                'size_label' => (string) ($item['size'] ?? ''),
                'qty' => (int) ($item['qty'] ?? 1),
                'gold_purity_id' => isset($item['gold_purity_id']) ? (int) $item['gold_purity_id'] : null,
                'gold_required_gm' => (float) ($item['gold_budget_gm'] ?? 0),
                'diamond_required_cts' => (float) ($item['diamond_budget_cts'] ?? 0),
                'item_description' => (string) ($item['notes'] ?? ''),
            ]);
        }

        $db->table('order_status_history')->insert([
            'order_id' => $orderId,
            'from_status' => null,
            'to_status' => $status,
            'remarks' => 'Order created via API',
            'changed_by' => (int) (session('admin_id') ?: 0),
        ]);
        $db->transComplete();

        return $this->ok(['order_id' => $orderId, 'order_no' => $orderNo], 'Order created.', 201);
    }

    public function updateStatus(int $id)
    {
        $payload = $this->payload();
        $status = trim((string) ($payload['status'] ?? ''));
        if ($status === '') {
            return $this->fail('status is required.', 422);
        }

        $db = db_connect();
        $order = $db->table('orders')->where('id', $id)->get()->getRowArray();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }

        $db->table('orders')->where('id', $id)->update(['status' => $status]);
        $db->table('order_status_history')->insert([
            'order_id' => $id,
            'from_status' => $order['status'] ?? null,
            'to_status' => $status,
            'remarks' => (string) ($payload['remarks'] ?? 'Status updated via API'),
            'changed_by' => (int) (session('admin_id') ?: 0),
        ]);

        return $this->ok(['order_id' => $id, 'status' => $status], 'Order status updated.');
    }
}
