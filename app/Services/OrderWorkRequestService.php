<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class OrderWorkRequestService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId, ?int $orderId = null): array
    {
        $canManage = (new RbacService())->userCan($userId, 'orders.assign');
        $builder = $this->db->table('order_work_requests r')
            ->select('r.*, o.order_no, o.status AS order_status, o.due_date, o.assigned_karigar_id, requester.name AS requester_name, assignee.name AS assignee_name, reviewer.name AS reviewed_by_name')
            ->join('orders o', 'o.id = r.order_id', 'inner')
            ->join('admin_users requester', 'requester.id = r.requested_by', 'left')
            ->join('admin_users assignee', 'assignee.id = r.assigned_to', 'left')
            ->join('admin_users reviewer', 'reviewer.id = r.reviewed_by', 'left');
        if (! $canManage) {
            $builder->where('r.requested_by', $userId);
        }
        if (($orderId ?? 0) > 0) {
            $builder->where('r.order_id', $orderId);
        }
        return $builder
            ->orderBy("CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('r.id', 'DESC')
            ->limit(500)
            ->get()->getResultArray();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function review(int $id, int $reviewerId, array $payload): array
    {
        if (! (new RbacService())->userCan($reviewerId, 'orders.assign')) {
            throw new RuntimeException('Only an authorised admin can review requests.', 403);
        }
        $request = $this->db->table('order_work_requests')->where('id', $id)->get()->getRowArray();
        if (! is_array($request)) {
            throw new RuntimeException('Request not found.', 404);
        }
        if ((string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Request has already been reviewed.');
        }

        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('Choose approve or reject.');
        }
        $note = trim((string) ($payload['review_note'] ?? ''));
        if ($decision === 'reject' && $note === '') {
            throw new RuntimeException('Enter a rejection reason.');
        }

        $approvedDueDate = null;
        if ($decision === 'approve' && (string) $request['request_type'] === 'order_delay') {
            if ($note === '') {
                throw new RuntimeException('Enter an admin note for the updated order due date.');
            }
            $dueInput = trim((string) ($payload['approved_due_at'] ?? ''));
            $dueTimestamp = strtotime($dueInput);
            if ($dueInput === '' || $dueTimestamp === false) {
                throw new RuntimeException('Select a valid updated order due date.');
            }
            $approvedDueDate = date('Y-m-d', $dueTimestamp);
            if ($approvedDueDate <= date('Y-m-d')) {
                throw new RuntimeException('Updated order due date must be a future date.');
            }
        }

        $staff = new StaffPerformanceService();
        $assignedTo = (int) ($payload['assigned_to'] ?? 0);
        $followupDueAt = null;
        if ($decision === 'approve' && (string) $request['request_type'] === 'follower_change') {
            $stamp = strtotime(trim((string) ($payload['followup_due_at'] ?? '')));
            if (! $staff->isStaffUser($assignedTo) || $stamp === false || $stamp <= time()) {
                throw new RuntimeException('Select an active staff follower and future follow-up time.');
            }
            $followupDueAt = date('Y-m-d H:i:s', $stamp);
        }

        $isMaterialRequest = in_array(
            (string) $request['request_type'],
            ['gold_requirement', 'diamond_requirement'],
            true
        );
        if ($decision === 'approve' && $isMaterialRequest && ! $staff->isStaffUser($assignedTo)) {
            throw new RuntimeException('Assign this material task to an active staff user.');
        }
        $taskDueAt = null;
        if ($decision === 'approve' && $isMaterialRequest) {
            $stamp = strtotime(trim((string) ($payload['task_due_at'] ?? '')));
            if ($stamp === false || $stamp <= time()) {
                throw new RuntimeException('Select a future task deadline.');
            }
            $taskDueAt = date('Y-m-d H:i:s', $stamp);
        }

        try {
            $this->db->transException(true)->transStart();
            $this->db->table('order_work_requests')->where('id', $id)->where('status', 'pending')->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_note' => $note ?: null,
                'assigned_to' => $decision === 'approve' && $assignedTo > 0 ? $assignedTo : null,
                'approved_due_at' => $approvedDueDate !== null ? $approvedDueDate . ' 00:00:00' : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Request has already been reviewed.');
            }
            $order = $this->db->table('orders')->where('id', (int) $request['order_id'])->get()->getRowArray();
            if (! is_array($order) || in_array((string) ($order['status'] ?? ''), ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Complete', 'Completed', 'Cancelled'], true)) {
                throw new RuntimeException('Order is no longer open.');
            }
            if ($decision === 'approve') {
                if ((string) $request['request_type'] === 'order_delay') {
                    $newDue = (string) $approvedDueDate;
                    if ($newDue <= (string) ($order['due_date'] ?? '')) {
                        throw new RuntimeException('Updated due date must be after the current delivery date.');
                    }
                    $this->db->table('orders')->where('id', (int) $order['id'])->update([
                        'due_date' => $newDue,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->db->table('job_cards')->where('order_id', (int) $order['id'])->update([
                        'due_date' => $newDue,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } elseif ((string) $request['request_type'] === 'follower_change') {
                    $staff->syncOrderAssignment((int) $order['id'], $assignedTo, $followupDueAt, $reviewerId);
                } else {
                    if ((string) $request['request_type'] === 'gold_requirement'
                        && (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
                        throw new RuntimeException('Assign a karigar to the order first.');
                    }
                    $diamond = (string) $request['request_type'] === 'diamond_requirement';
                    (new WorkflowTaskService())->assign(
                        $diamond ? 'order_diamond_bag' : 'order_gold_request',
                        $id,
                        $assignedTo,
                        ($diamond ? 'Create diamond bag for order ' : 'Issue gold for order ') . $order['order_no'],
                        $taskDueAt,
                        $reviewerId,
                        ($diamond ? '' : (string) $request['gold_quantity_gm'] . ' gm · ') . (string) $request['details']
                    );
                }
            }
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if ($decision === 'approve' && (string) $request['request_type'] === 'follower_change') {
            try {
                (new MobileNotificationEventService())->notifyFollowerAssigned((int) $request['order_id']);
            } catch (Throwable $e) {
                log_message('error', 'Follower request approval notification failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        if ($decision === 'approve' && $isMaterialRequest) {
            $diamond = (string) $request['request_type'] === 'diamond_requirement';
            $referenceType = $diamond ? 'order_diamond_bag' : 'order_gold_request';
            $task = $this->db->table('mobile_tasks')->select('id')
                ->where('reference_type', $referenceType)->where('reference_id', $id)->get()->getRowArray();
            if (is_array($task)) {
                try {
                    (new MobilePushService())->queueForAdmin($assignedTo, [
                        'type' => 'task_assigned',
                        'reference_table' => 'mobile_tasks',
                        'reference_id' => (int) $task['id'],
                        'dedupe_key' => ($diamond ? 'diamond-bag-task:' : 'gold-request-task:') . $id,
                        'title' => $diamond ? 'Diamond bag assigned' : 'Gold issuement assigned',
                        'message' => ($diamond ? 'Create diamond bag for order #' : 'Issue gold for order #') . (int) $request['order_id'] . ' by ' . $taskDueAt,
                        'payload' => ['screen' => 'tasks', 'task_id' => (int) $task['id']],
                    ]);
                } catch (Throwable $e) {
                    log_message('error', 'Material task notification failed: {message}', ['message' => $e->getMessage()]);
                }
            }
        }

        return [
            'id' => $id,
            'decision' => $decision,
            'request_type' => (string) $request['request_type'],
            'order_id' => (int) $request['order_id'],
            'approved_due_at' => $approvedDueDate,
        ];
    }
}
