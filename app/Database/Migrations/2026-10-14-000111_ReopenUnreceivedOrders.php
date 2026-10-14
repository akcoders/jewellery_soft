<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ReopenUnreceivedOrders extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('orders')
            || ! $this->db->tableExists('order_receive_summaries')
            || ! $this->db->tableExists('order_material_movements')) {
            throw new \RuntimeException('Cannot reopen completed orders without the orders and receiving tables.');
        }

        $orders = $this->db->table('orders o')
            ->select('o.id, o.status')
            ->join('order_receive_summaries rs', 'rs.order_id = o.id', 'left')
            ->join(
                'order_material_movements rm',
                "rm.order_id = o.id AND rm.movement_type = 'receive'",
                'left',
                false
            )
            ->whereIn('o.status', ['Completed', 'Packed', 'Dispatched'])
            ->where('rs.id', null)
            ->where('rm.id', null)
            ->get()
            ->getResultArray();
        $orderIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $orders
        ), static fn(int $id): bool => $id > 0)));

        if ($orderIds === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transException(true)->transStart();

        $updates = [
            'status' => 'Ready',
            'updated_at' => $now,
        ];
        if ($this->db->fieldExists('receiving_status', 'orders')) {
            $updates['receiving_status'] = 'pending';
        }
        if ($this->db->fieldExists('received_at', 'orders')) {
            $updates['received_at'] = null;
        }
        if ($this->db->fieldExists('received_by', 'orders')) {
            $updates['received_by'] = null;
        }
        $this->db->table('orders')->whereIn('id', $orderIds)->update($updates);

        if ($this->db->tableExists('order_items')) {
            $this->db->table('order_items')
                ->whereIn('order_id', $orderIds)
                ->whereIn('item_status', ['Completed', 'Packed', 'Dispatched'])
                ->update(['item_status' => 'Ready', 'updated_at' => $now]);
        }

        if ($this->db->tableExists('order_status_history')) {
            $history = [];
            foreach ($orders as $row) {
                $orderId = (int) ($row['id'] ?? 0);
                $history[] = [
                    'order_id' => $orderId,
                    'from_status' => (string) ($row['status'] ?? ''),
                    'to_status' => 'Ready',
                    'remarks' => 'Reopened because finished jewellery receiving was not recorded.',
                    'changed_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $this->db->table('order_status_history')->insertBatch($history);
        }

        $this->db->transComplete();
    }

    public function down(): void
    {
        // Reverting could overwrite order status changes made after this migration.
    }
}
