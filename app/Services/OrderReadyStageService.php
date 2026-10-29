<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Correct a selected unreceived order without creating a receiving entry. */
class OrderReadyStageService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function orderOptions(string $query = ''): array
    {
        $builder = $this->db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.status, c.name AS customer_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->whereNotIn('o.status', ['Cancelled', 'Canceled']);
        if ($this->db->fieldExists('deleted_at', 'orders')) {
            $builder->where('o.deleted_at', null);
        }
        (new OrderReceivingService($this->db))->excludeRecordedReceipts($builder, 'o.id');
        $query = trim($query);
        if ($query !== '') {
            $builder->groupStart()->like('o.order_no', $query)
                ->orLike('o.order_name', $query)->orLike('c.name', $query)->groupEnd();
        }
        return $builder->orderBy('o.id', 'DESC')->limit(1000)->get()->getResultArray();
    }

    /** @return array{order_id:int,order_no:string,previous_status:string,changed:bool} */
    public function markReady(int $orderId, int $actorId): array
    {
        if ($orderId <= 0 || $actorId <= 0) {
            throw new RuntimeException('Select an order and sign in as an authorised admin.');
        }
        $this->db->transBegin();
        try {
            $builder = $this->db->table('orders')->where('id', $orderId);
            if ($this->db->fieldExists('deleted_at', 'orders')) {
                $builder->where('deleted_at', null);
            }
            // Serialize this correction with receiving's final order update.
            $lock = in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true) ? ' FOR UPDATE' : '';
            $order = $this->db->query($builder->getCompiledSelect() . $lock)->getRowArray();
            if (! is_array($order)) {
                throw new RuntimeException('Order not found.');
            }
            if (in_array((string) $order['status'], ['Cancelled', 'Canceled'], true)) {
                throw new RuntimeException('Cancelled orders cannot be moved to Ready.');
            }
            if ((new OrderReceivingService($this->db))->hasRecordedReceipt($orderId)) {
                throw new RuntimeException('This order has already been received and cannot be moved to Ready.');
            }

            $previous = (string) ($order['status'] ?? '');
            $now = date('Y-m-d H:i:s');
            $updates = [
                'status' => 'Ready', 'followup_due_at' => null, 'updated_at' => $now,
            ];
            foreach (['receiving_status' => 'pending', 'received_at' => null, 'received_by' => null] as $field => $value) {
                if ($this->db->fieldExists($field, 'orders')) {
                    $updates[$field] = $value;
                }
            }
            $this->db->table('orders')->where('id', $orderId)->update($updates);
            $this->db->table('order_items')->where('order_id', $orderId)->update([
                'item_status' => 'Ready', 'updated_at' => $now,
            ]);
            (new StaffPerformanceService())->closeOrderFollowupSchedules($orderId, $this->db);
            if ($previous !== 'Ready') {
                $this->db->table('order_status_history')->insert([
                    'order_id' => $orderId,
                    'from_status' => $previous ?: null,
                    'to_status' => 'Ready',
                    'remarks' => 'Moved to Ready using the order stage correction page. Receiving is still pending.',
                    'changed_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Unable to save the Ready stage.');
            }
            $this->db->transCommit();
            return [
                'order_id' => $orderId,
                'order_no' => (string) ($order['order_no'] ?? ('#' . $orderId)),
                'previous_status' => $previous,
                'changed' => $previous !== 'Ready',
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
