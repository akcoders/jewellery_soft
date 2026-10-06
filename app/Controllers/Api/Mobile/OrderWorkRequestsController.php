<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DiamondBagService;
use App\Services\RbacService;
use App\Services\MobileNotificationEventService;
use App\Services\MobilePushService;
use App\Services\StaffPerformanceService;
use App\Services\WorkflowTaskService;
use Throwable;

class OrderWorkRequestsController extends MobileBaseController
{
    private const TYPES = ['order_delay', 'gold_requirement', 'diamond_requirement', 'follower_change'];

    public function index()
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $db = db_connect();
        $userId = (int) $this->mobileAdmin['id'];
        $admin = (new RbacService())->userCan($userId, 'orders.assign');
        $builder = $db->table('order_work_requests r')
            ->select('r.*, o.order_no, o.assigned_karigar_id, requester.name AS requester_name, assignee.name AS assignee_name')
            ->join('orders o', 'o.id = r.order_id', 'inner')
            ->join('admin_users requester', 'requester.id = r.requested_by', 'left')
            ->join('admin_users assignee', 'assignee.id = r.assigned_to', 'left');
        if (! $admin) {
            $builder->where('r.requested_by', $userId);
        }
        $orderId = (int) ($this->request->getGet('order_id') ?? 0);
        if ($orderId > 0) $builder->where('r.order_id', $orderId);
        return $this->ok($builder->orderBy('r.id', 'DESC')->limit(100)->get()->getResultArray());
    }

    public function create(int $orderId)
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $db = db_connect();
        $order = $db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) return $this->fail('Order not found.', 404);
        if ((int) ($order['followup_assigned_to'] ?? 0) !== (int) $this->mobileAdmin['id']) {
            return $this->fail('Only this order\'s follower can raise a request.', 403);
        }
        if (in_array((string) $order['status'], ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Complete', 'Completed', 'Cancelled'], true)) {
            return $this->fail('Requests are available only for open orders.', 422);
        }
        $payload = $this->payload();
        $type = trim((string) ($payload['request_type'] ?? ''));
        $details = trim((string) ($payload['details'] ?? ''));
        if (! in_array($type, self::TYPES, true) || $details === '') {
            return $this->fail('Select a request type and enter details.', 422);
        }
        if ($type === 'diamond_requirement' && ! (new DiamondBagService($db))->supportsOrder($orderId)) {
            return $this->fail('Diamond bags can be requested only for Diamond or Jadau orders.', 422);
        }
        if ($db->table('order_work_requests')->where('order_id', $orderId)
            ->where('request_type', $type)->where('status', 'pending')->countAllResults() > 0) {
            return $this->fail('This order already has a pending request of that type.', 422);
        }
        $dueAt = null;
        if ($type === 'order_delay') {
            $date = trim((string) ($payload['requested_due_at'] ?? ''));
            $timestamp = strtotime($date);
            if ($date === '' || $timestamp === false || $timestamp <= time()) {
                return $this->fail('Select a future requested delivery date.', 422);
            }
            $dueAt = date('Y-m-d H:i:s', $timestamp);
        }
        $gold = $type === 'gold_requirement' ? (float) ($payload['gold_quantity_gm'] ?? 0) : 0;
        if ($type === 'gold_requirement' && ($gold <= 0 || $gold > 100000)) {
            return $this->fail('Enter a valid gold requirement in grams.', 422);
        }
        $id = $db->table('order_work_requests')->insert([
            'order_id' => $orderId,
            'request_type' => $type,
            'details' => $details,
            'requested_due_at' => $dueAt,
            'gold_quantity_gm' => $type === 'gold_requirement' ? round($gold, 3) : null,
            'status' => 'pending',
            'requested_by' => (int) $this->mobileAdmin['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) ? $db->insertID() : 0;
        if (! $id) return $this->fail('Could not create request.', 500);
        try {
            (new MobileNotificationEventService())->notifyOrderWorkRequestRaised((int) $id);
        } catch (Throwable $e) {
            log_message('error', 'Order work request notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $this->ok(['id' => (int) $id], 'Request sent for admin approval.', 201);
    }

    public function review(int $id)
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $adminId = (int) $this->mobileAdmin['id'];
        if (! (new RbacService())->userCan($adminId, 'orders.assign')) {
            return $this->fail('Only an authorised admin can review requests.', 403);
        }
        $db = db_connect();
        $request = $db->table('order_work_requests')->where('id', $id)->get()->getRowArray();
        if (! $request) return $this->fail('Request not found.', 404);
        if ((string) $request['status'] !== 'pending') return $this->fail('Request has already been reviewed.', 422);
        $payload = $this->payload();
        $decision = trim((string) ($payload['decision'] ?? ''));
        if (! in_array($decision, ['approve', 'reject'], true)) return $this->fail('Choose approve or reject.', 422);
        $note = trim((string) ($payload['review_note'] ?? ''));
        if ($decision === 'reject' && $note === '') return $this->fail('Enter a rejection reason.', 422);

        $staff = new StaffPerformanceService();
        $assignedTo = (int) ($payload['assigned_to'] ?? 0);
        $followupDueAt = null;
        if ($decision === 'approve' && $request['request_type'] === 'follower_change') {
            $stamp = strtotime(trim((string) ($payload['followup_due_at'] ?? '')));
            if (! $staff->isStaffUser($assignedTo) || $stamp === false || $stamp <= time()) {
                return $this->fail('Select an active staff follower and future follow-up time.', 422);
            }
            $followupDueAt = date('Y-m-d H:i:s', $stamp);
        }
        $isMaterialRequest = in_array(
            (string) $request['request_type'],
            ['gold_requirement', 'diamond_requirement'],
            true
        );
        if ($decision === 'approve' && $isMaterialRequest && ! $staff->isStaffUser($assignedTo)) {
            return $this->fail('Assign this material task to an active staff user.', 422);
        }
        $taskDueAt = null;
        if ($decision === 'approve' && $isMaterialRequest) {
            $stamp = strtotime(trim((string) ($payload['task_due_at'] ?? '')));
            if ($stamp === false || $stamp <= time()) return $this->fail('Select a future task deadline.', 422);
            $taskDueAt = date('Y-m-d H:i:s', $stamp);
        }

        try {
            $db->transException(true)->transStart();
            $db->table('order_work_requests')->where('id', $id)->where('status', 'pending')->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_note' => $note ?: null,
                'assigned_to' => $decision === 'approve' && $assignedTo > 0 ? $assignedTo : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($db->affectedRows() !== 1) throw new \RuntimeException('Request has already been reviewed.');
            $order = $db->table('orders')->where('id', (int) $request['order_id'])->get()->getRowArray();
            if (! $order || in_array((string) $order['status'], ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Complete', 'Completed', 'Cancelled'], true)) {
                throw new \RuntimeException('Order is no longer open.');
            }
            if ($decision === 'approve') {
                if ($request['request_type'] === 'order_delay') {
                    $newDue = substr((string) $request['requested_due_at'], 0, 10);
                    if ($newDue <= (string) ($order['due_date'] ?? '')) throw new \RuntimeException('Requested date must be after the current delivery date.');
                    $db->table('orders')->where('id', (int) $order['id'])->update(['due_date' => $newDue, 'updated_at' => date('Y-m-d H:i:s')]);
                    $db->table('job_cards')->where('order_id', (int) $order['id'])->update(['due_date' => $newDue, 'updated_at' => date('Y-m-d H:i:s')]);
                } elseif ($request['request_type'] === 'follower_change') {
                    $staff->syncOrderAssignment((int) $order['id'], $assignedTo, $followupDueAt, $adminId);
                } else {
                    if ($request['request_type'] === 'gold_requirement'
                        && (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
                        throw new \RuntimeException('Assign a karigar to the order first.');
                    }
                    $diamond = $request['request_type'] === 'diamond_requirement';
                    (new WorkflowTaskService())->assign(
                        $diamond ? 'order_diamond_bag' : 'order_gold_request',
                        $id,
                        $assignedTo,
                        ($diamond ? 'Create diamond bag for order ' : 'Issue gold for order ') . $order['order_no'],
                        $taskDueAt,
                        $adminId,
                        ($diamond ? '' : (string) $request['gold_quantity_gm'] . ' gm · ') . (string) $request['details']
                    );
                }
            }
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 422);
        }
        if ($decision === 'approve' && $request['request_type'] === 'follower_change') {
            try {
                (new MobileNotificationEventService())->notifyFollowerAssigned((int) $request['order_id']);
            } catch (Throwable $e) {
                log_message('error', 'Follower request approval notification failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        if ($decision === 'approve' && $isMaterialRequest) {
            $diamond = $request['request_type'] === 'diamond_requirement';
            $referenceType = $diamond ? 'order_diamond_bag' : 'order_gold_request';
            $task = $db->table('mobile_tasks')->select('id')->where('reference_type', $referenceType)->where('reference_id', $id)->get()->getRowArray();
            if ($task) {
                try {
                    (new MobilePushService())->queueForAdmin($assignedTo, [
                        'type' => 'task_assigned', 'reference_table' => 'mobile_tasks', 'reference_id' => (int) $task['id'],
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
        return $this->ok(['id' => $id], 'Request ' . ($decision === 'approve' ? 'approved' : 'rejected') . '.');
    }
}
