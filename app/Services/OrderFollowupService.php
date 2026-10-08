<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Jewellery;
use RuntimeException;
use Throwable;

class OrderFollowupService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(int $orderId, array $payload, int $submittedBy, ?string $imageName = null, ?string $imagePath = null): array
    {
        $stage = trim((string) ($payload['stage'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $nextFollowup = trim((string) ($payload['next_followup_date'] ?? ''));
        if (! in_array($stage, config(Jewellery::class)->orderStatuses, true)) {
            throw new RuntimeException('Invalid follow-up stage.');
        }
        if ($description === '') {
            throw new RuntimeException('Follow-up description is required.');
        }
        $order = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! is_array($order)) {
            throw new RuntimeException('Order not found.');
        }
        if (in_array((string) ($order['status'] ?? ''), ['Cancelled', 'Completed', 'Complete', 'Ready', 'Packed', 'Delivered', 'Dispatched'], true)) {
            throw new RuntimeException('Follow-up is not allowed for this order status.');
        }
        if ((int) ($order['followup_assigned_to'] ?? 0) !== $submittedBy) {
            throw new RuntimeException('Only the assigned order follower can submit this follow-up.');
        }
        $terminal = in_array($stage, ['Ready', 'Packed', 'Dispatched', 'Completed', 'Cancelled'], true);
        if (! $terminal && $nextFollowup === '') {
            throw new RuntimeException('Next follow-up date is required while the order remains open.');
        }
        $nextAt = null;
        if (! $terminal && $nextFollowup !== '') {
            $timestamp = strtotime($nextFollowup);
            if ($timestamp === false) {
                throw new RuntimeException('Enter a valid next follow-up date and time.');
            }
            $nextAt = date('Y-m-d H:i:s', $timestamp);
        }
        $takenOn = trim((string) ($payload['followup_taken_on'] ?? ''));
        $takenTimestamp = $takenOn !== '' ? strtotime($takenOn) : false;
        $takenOn = $takenTimestamp === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $takenTimestamp);

        try {
            $this->db->transException(true)->transStart();
            $this->db->table('order_followups')->insert([
                'order_id' => $orderId,
                'stage' => $stage,
                'description' => $description,
                'next_followup_date' => $nextAt,
                'followup_taken_by' => $submittedBy,
                'followup_taken_on' => $takenOn,
                'image_name' => $imageName,
                'image_path' => $imagePath,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $followupId = (int) $this->db->insertID();
            if ($followupId <= 0) {
                throw new RuntimeException('Follow-up could not be saved.');
            }
            $oldStatus = (string) ($order['status'] ?? '');
            if ($oldStatus !== $stage) {
                $this->db->table('orders')->where('id', $orderId)->update([
                    'status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('order_items')->where('order_id', $orderId)->update([
                    'item_status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('order_status_history')->insert([
                    'order_id' => $orderId,
                    'from_status' => $oldStatus !== '' ? $oldStatus : null,
                    'to_status' => $stage,
                    'remarks' => 'Updated from follow-up: ' . $description,
                    'changed_by' => $submittedBy,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            (new StaffPerformanceService())->completeOrderFollowup(
                $orderId,
                $followupId,
                $submittedBy,
                $nextAt,
                $takenOn
            );
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        try {
            (new MobileNotificationEventService())->notifyFollowupAdded($orderId, $followupId);
        } catch (Throwable $e) {
            log_message('error', 'Approved follow-up notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return ['table' => 'order_followups', 'id' => $followupId, 'reference' => 'Order #' . $orderId, 'order_id' => $orderId];
    }
}
