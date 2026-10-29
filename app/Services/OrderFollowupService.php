<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
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
        $this->db->transException(true)->transStart();
        try {
            $created = $this->save($orderId, $payload, $submittedBy, $imageName, $imagePath, 'approved');
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        $this->notifyCreated((int) $created['order_id'], (int) $created['id']);
        $this->notifyOrderStageChanged($created);
        return $created;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createPendingApproval(int $orderId, array $payload, int $submittedBy, string $summary, ?string $imageName = null, ?string $imagePath = null): array
    {
        $this->db->transException(true)->transStart();
        try {
            $created = $this->save($orderId, $payload, $submittedBy, $imageName, $imagePath, 'pending');
            $payload['followup_id'] = (int) $created['id'];
            $payload['order_id'] = (int) $created['order_id'];
            $payload['order_no'] = (string) $created['order_no'];
            $payload['followup_scope'] = (string) $created['followup_scope'];
            if ((int) ($payload['order_id'] ?? 0) !== $orderId) {
                $summary = 'Order ' . (string) $created['order_no'] . ' follow-up: ' . (string) $created['stage'];
            }
            $approval = (new MobileApprovalService($this->db))->submit(
                'followup',
                $payload,
                $submittedBy,
                $summary,
                $imageName,
                $imagePath,
                'order_followups',
                (int) $created['id'],
                false
            );
            $this->db->table('order_followups')->where('id', (int) $created['id'])->update([
                'approval_request_id' => (int) ($approval['id'] ?? 0) ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        try {
            (new MobileNotificationEventService())->notifyApprovalRequested($approval);
        } catch (Throwable $e) {
            log_message('error', 'Approval request notification failed: {message}', ['message' => $e->getMessage()]);
        }
        try {
            (new AttraiIntegrationService())->notifyApprovalRequested($approval);
        } catch (Throwable $e) {
            log_message('error', 'ATTRAI follow-up approval notification failed: {message}', ['message' => $e->getMessage()]);
        }
        $this->notifyCreated((int) $created['order_id'], (int) $created['id']);

        return $created + ['approval_request' => $approval];
    }

    /** @return array<string,mixed> */
    public function reviewPending(int $followupId, bool $approved, int $reviewerId, string $note = '', bool $awardPoints = true): array
    {
        $this->db->transException(true)->transStart();
        try {
            $result = $this->reviewPendingWithinTransaction($followupId, $approved, $reviewerId, $note, $awardPoints);
            $this->db->transComplete();
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function reviewPendingWithinTransaction(int $followupId, bool $approved, int $reviewerId, string $note, bool $awardPoints): array
    {
        $followup = $this->db->table('order_followups')->where('id', $followupId)->get()->getRowArray();
        if (! is_array($followup)) {
            throw new RuntimeException('Pending follow-up was not found.');
        }
        if ((string) ($followup['approval_status'] ?? 'approved') !== 'pending') {
            throw new RuntimeException('Follow-up is no longer pending approval.');
        }

        if ($approved) {
            (new OrderWorkflowService($this->db))->assertFollowupStage((string) ($followup['stage'] ?? ''));
            $order = $this->lockedOrder((int) $followup['order_id']);
            if (! is_array($order)) {
                throw new RuntimeException('Order not found.');
            }
            $laterApprovedQuery = $this->db->table('order_followups')
                ->select('id')
                ->where('order_id', (int) $followup['order_id'])
                ->where('approval_status', 'approved')
                ->groupStart()
                    ->where('followup_taken_on >', (string) ($followup['followup_taken_on'] ?? ''))
                    ->orGroupStart()
                        ->where('followup_taken_on', (string) ($followup['followup_taken_on'] ?? ''))
                        ->where('id >', $followupId)
                    ->groupEnd()
                ->groupEnd();
            // A reviewer may have committed a newer approval while this
            // transaction waited for the order lock. Use a current read.
            $lock = in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true) ? ' FOR UPDATE' : '';
            $laterApproved = $this->db->query($laterApprovedQuery->getCompiledSelect() . $lock)->getRowArray();
            if (! is_array($laterApproved)) {
                $this->applyApprovedStage($order, $followup, (int) ($followup['followup_taken_by'] ?? 0));
            }
            $appliedStatus = (string) ($this->lockedOrder((int) $followup['order_id'])['status'] ?? '');
        }

        $status = $approved ? 'approved' : 'rejected';
        $this->db->table('order_followups')->where('id', $followupId)->update([
            'approval_status' => $status,
            'points_enabled' => $approved && ! $awardPoints ? 0 : 1,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => trim($note) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'table' => 'order_followups',
            'id' => $followupId,
            'reference' => 'Order #' . (int) $followup['order_id'],
            'order_id' => (int) $followup['order_id'],
            'approval_status' => $status,
            'from_status' => $approved ? (string) ($order['status'] ?? '') : '',
            'to_status' => $approved ? $appliedStatus : '',
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function save(int $orderId, array $payload, int $submittedBy, ?string $imageName, ?string $imagePath, string $approvalStatus): array
    {
        $scope = strtolower(trim((string) ($payload['followup_scope'] ?? 'order'))) === 'family' ? 'family' : 'order';
        $family = new OrderFamilyService($this->db);
        $root = $family->root($orderId);
        if (! is_array($root)) {
            throw new RuntimeException('Order not found.');
        }
        $isChildOrder = (int) ($root['id'] ?? 0) !== $orderId;
        if ($scope === 'family' || $isChildOrder) {
            if ($isChildOrder) {
                $scope = 'family';
            }
            $orderId = (int) $root['id'];
            $payload['order_id'] = $orderId;
            $payload['order_no'] = (string) ($root['order_no'] ?? ($payload['order_no'] ?? ''));
        }
        $stage = trim((string) ($payload['stage'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $nextFollowup = trim((string) ($payload['next_followup_date'] ?? ''));
        $workflow = new OrderWorkflowService($this->db);
        $workflow->assertFollowupStage($stage);
        if ($description === '') {
            throw new RuntimeException('Follow-up description is required.');
        }
        $order = $this->lockedOrder($orderId);
        if (! is_array($order)) {
            throw new RuntimeException('Order not found.');
        }
        if (! $workflow->canApplyFollowup($order)) {
            throw new RuntimeException('Follow-up is not allowed for this order status.');
        }
        if ((int) ($order['followup_assigned_to'] ?? 0) !== $submittedBy) {
            throw new RuntimeException('Only the assigned order follower can submit this follow-up.');
        }
        $familyReminderIds = [];
        if ($scope === 'family') {
            $familyIds = $family->ids($orderId);
            sort($familyIds, SORT_NUMERIC);
            foreach ($familyIds as $childId) {
                if ($childId === $orderId) continue;
                $child = $this->lockedOrder($childId);
                if (is_array($child) && $workflow->canApplyFollowup($child)) {
                    $familyReminderIds[] = $childId;
                }
            }
        }
        $terminal = $stage === 'Ready';
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
        $quality = (new FollowupQualityService($this->db))->inspect($description, $orderId, $submittedBy, $takenOn);

        $this->db->table('order_followups')->insert([
                'order_id' => $orderId,
                'followup_scope' => $scope,
                'stage' => $stage,
                'description' => $description,
                'next_followup_date' => $nextAt,
                'followup_taken_by' => $submittedBy,
                'followup_taken_on' => $takenOn,
                'image_name' => $imageName,
                'image_path' => $imagePath,
                'approval_status' => $approvalStatus,
                'points_enabled' => 1,
                'normalized_text' => $quality['normalized'],
                'normalized_fingerprint' => $quality['fingerprint'],
                'duplicate_followup_id' => $quality['matched_followup_id'],
                'duplicate_reason' => $quality['reason'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $followupId = (int) $this->db->insertID();
        if ($followupId <= 0) {
            throw new RuntimeException('Follow-up could not be saved.');
        }
        if ($approvalStatus === 'approved') {
            $this->applyApprovedStage($order, [
                'order_id' => $orderId,
                'followup_scope' => $scope,
                'stage' => $stage,
                'description' => $description,
            ], $submittedBy);
        }
        $taskOrderId = $scope === 'family'
            ? (int) (((new OrderFamilyService($this->db))->root($orderId)['id'] ?? $orderId))
            : $orderId;
        (new StaffPerformanceService())->completeOrderFollowup(
            $taskOrderId,
            $followupId,
            $submittedBy,
            $nextAt,
            $takenOn
        );
        if ($familyReminderIds !== []) {
            $this->db->table('orders')->whereIn('id', $familyReminderIds)->update([
                'followup_due_at' => $nextAt, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'table' => 'order_followups',
            'id' => $followupId,
            'reference' => 'Order #' . $orderId,
            'order_id' => $orderId,
            'order_no' => (string) ($order['order_no'] ?? ''),
            'stage' => $stage,
            'followup_scope' => $scope,
            'approval_status' => $approvalStatus,
            'from_status' => (string) ($order['status'] ?? ''),
            'to_status' => $approvalStatus === 'approved' ? $stage : '',
        ];
    }

    /** @param array<string,mixed> $order @param array<string,mixed> $followup */
    private function applyApprovedStage(array $order, array $followup, int $changedBy): void
    {
        $stage = (string) ($followup['stage'] ?? '');
        $workflow = new OrderWorkflowService($this->db);
        $workflow->assertFollowupStage($stage);
        $now = date('Y-m-d H:i:s');
        $orderId = (int) ($order['id'] ?? $followup['order_id'] ?? 0);
        $ids = (string) ($followup['followup_scope'] ?? 'order') === 'family'
            ? (new OrderFamilyService($this->db))->ids($orderId)
            : [$orderId];
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $targetId) {
            $target = $this->lockedOrder($targetId);
            if (! is_array($target) || ! $workflow->canApplyFollowup($target)
                || (string) ($target['status'] ?? '') === $stage) continue;
            $this->db->table('orders')->where('id', $targetId)->update(['status' => $stage, 'updated_at' => $now]);
            $this->db->table('order_items')->where('order_id', $targetId)->update(['item_status' => $stage, 'updated_at' => $now]);
            $this->db->table('order_status_history')->insert([
                'order_id' => $targetId,
                'from_status' => (string) ($target['status'] ?? '') ?: null,
                'to_status' => $stage,
                'remarks' => 'Updated from approved family follow-up: ' . (string) ($followup['description'] ?? ''),
                'changed_by' => $changedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Receiving updates this same row before committing. A locking read sees
     * its current status under MySQL repeatable-read and holds it through the
     * follow-up's stage, item and reminder writes.
     *
     * @return array<string,mixed>|null
     */
    private function lockedOrder(int $orderId): ?array
    {
        $builder = $this->db->table('orders')->where('id', $orderId);
        $lock = in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true) ? ' FOR UPDATE' : '';
        return $this->db->query($builder->getCompiledSelect() . $lock)->getRowArray();
    }

    private function notifyCreated(int $orderId, int $followupId): void
    {
        try {
            (new MobileNotificationEventService())->notifyFollowupAdded($orderId, $followupId);
        } catch (Throwable $e) {
            log_message('error', 'Follow-up notification failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    /** @param array<string,mixed> $result */
    public function notifyOrderStageChanged(array $result): void
    {
        $orderId = (int) ($result['order_id'] ?? 0);
        $from = trim((string) ($result['from_status'] ?? ''));
        $to = trim((string) ($result['to_status'] ?? ''));
        if ($orderId <= 0 || $to === '' || $from === $to) {
            return;
        }
        try {
            (new OrderWhatsAppService())->notifyOrderStatusChanged($orderId, $from, $to, 'Updated from approved follow-up.');
            if (in_array($to, ['Ready', 'Completed'], true)) {
                (new OrderWhatsAppService())->notifyOrderReady($orderId, $to);
            }
            (new AttraiIntegrationService())->notifyOrderStatusToFollower(
                $orderId,
                'Status changed from ' . ($from !== '' ? $from : '-') . ' to ' . $to . '.'
            );
        } catch (Throwable $e) {
            log_message('error', 'ATTRAI order follow-up update failed: {message}', ['message' => $e->getMessage()]);
        }
    }
}
