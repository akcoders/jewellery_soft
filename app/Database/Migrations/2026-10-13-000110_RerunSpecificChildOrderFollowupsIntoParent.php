<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RerunSpecificChildOrderFollowupsIntoParent extends Migration
{
    private const PARENT_ORDER_NO = 'CU26100011105-EAR-NS-S42E';
    private const CHILD_ORDER_NO = 'CU26100011105-EAR-NS-54MX';

    public function up(): void
    {
        if (! $this->db->tableExists('orders')) {
            throw new \RuntimeException('Cannot merge follow-ups: the orders table does not exist.');
        }

        if (! $this->db->fieldExists('parent_order_id', 'orders')) {
            $this->forge->addColumn('orders', [
                'parent_order_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'order_no',
                ],
            ]);
        }
        $indexes = $this->db->getIndexData('orders');
        if (! isset($indexes['idx_orders_parent_order'])) {
            $this->db->query('ALTER TABLE `orders` ADD INDEX `idx_orders_parent_order` (`parent_order_id`)');
        }

        $parent = $this->db->table('orders')->where('order_no', self::PARENT_ORDER_NO)->get()->getRowArray();
        $child = $this->db->table('orders')->where('order_no', self::CHILD_ORDER_NO)->get()->getRowArray();
        if (! is_array($parent) || ! is_array($child)) {
            log_message(
                'warning',
                sprintf(
                    'Order follow-up merge was not applied: parent order %s %s found; child order %s %s found.',
                    self::PARENT_ORDER_NO,
                    is_array($parent) ? 'was' : 'was not',
                    self::CHILD_ORDER_NO,
                    is_array($child) ? 'was' : 'was not'
                )
            );
            return;
        }
        if ((int) $parent['id'] === (int) $child['id']) {
            throw new \RuntimeException('Cannot merge follow-ups: parent and child orders resolve to the same record.');
        }

        $parentId = (int) $parent['id'];
        $childId = (int) $child['id'];
        $now = date('Y-m-d H:i:s');

        $this->db->transException(true)->transStart();

        $followupIds = $this->ids('order_followups', 'order_id', $childId);
        $scheduleIds = $this->ids('order_followup_schedules', 'order_id', $childId);
        $taskIds = [];
        if ($scheduleIds !== [] && $this->hasFields('mobile_tasks', ['id', 'reference_type', 'reference_id'])) {
            $taskIds = array_map(
                static fn(array $row): int => (int) $row['id'],
                $this->db->table('mobile_tasks')->select('id')
                    ->where('reference_type', 'order_followup')
                    ->whereIn('reference_id', $scheduleIds)->get()->getResultArray()
            );
        }
        $approvalIds = $this->followupApprovalIds($childId, $followupIds);

        if ($this->hasFields('mobile_push_notifications', ['reference_table', 'reference_id'])) {
            foreach ([
                'order_followups' => $followupIds,
                'mobile_approval_requests' => $approvalIds,
                'mobile_tasks' => $taskIds,
            ] as $table => $ids) {
                if ($ids !== []) {
                    $this->db->table('mobile_push_notifications')
                        ->where('reference_table', $table)->whereIn('reference_id', $ids)->delete();
                }
            }
            if ($this->db->fieldExists('type', 'mobile_push_notifications')) {
                $this->db->table('mobile_push_notifications')
                    ->where('reference_table', 'orders')->where('reference_id', $childId)
                    ->whereIn('type', ['followup_added', 'followup_assignment', 'followup_due', 'followup_delay'])
                    ->delete();
            }
        }

        if ($followupIds !== [] && $this->hasFields('followup_duplicate_override_audits', ['followup_id'])) {
            $this->db->table('followup_duplicate_override_audits')->whereIn('followup_id', $followupIds)->delete();
        }
        if ($followupIds !== [] && $this->hasFields('order_followups', [
            'duplicate_followup_id',
            'duplicate_reason',
            'duplicate_override',
            'updated_at',
        ])) {
            $this->db->table('order_followups')->whereIn('duplicate_followup_id', $followupIds)->update([
                'duplicate_followup_id' => null,
                'duplicate_reason' => null,
                'duplicate_override' => null,
                'updated_at' => $now,
            ]);
        }
        if ($taskIds !== [] && $this->db->tableExists('mobile_tasks')) {
            $this->db->table('mobile_tasks')->whereIn('id', $taskIds)->delete();
        }
        if ($scheduleIds !== [] && $this->db->tableExists('order_followup_schedules')) {
            $this->db->table('order_followup_schedules')->whereIn('id', $scheduleIds)->delete();
        }
        if ($approvalIds !== [] && $this->db->tableExists('mobile_approval_requests')) {
            $this->db->table('mobile_approval_requests')->whereIn('id', $approvalIds)->delete();
        }
        if ($followupIds !== [] && $this->db->tableExists('order_followups')) {
            $this->db->table('order_followups')->whereIn('id', $followupIds)->delete();
        }

        $this->db->table('orders')->where('id', $childId)->update([
            'parent_order_id' => $parentId,
            'followup_assigned_to' => $parent['followup_assigned_to'] ?? null,
            'followup_due_at' => $parent['followup_due_at'] ?? null,
            'updated_at' => $now,
        ]);

        $this->db->transComplete();
    }

    public function down(): void
    {
        // Removed child follow-up and approval history cannot be reconstructed safely.
    }

    /** @return list<int> */
    private function ids(string $table, string $column, int $value): array
    {
        if (! $this->hasFields($table, ['id', $column])) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn(array $row): int => (int) $row['id'],
            $this->db->table($table)->select('id')->where($column, $value)->get()->getResultArray()
        )));
    }

    /** @param list<int> $followupIds @return list<int> */
    private function followupApprovalIds(int $childId, array $followupIds): array
    {
        if (! $this->hasFields('mobile_approval_requests', [
            'id',
            'request_type',
            'subject_table',
            'subject_id',
            'result_table',
            'result_id',
            'payload_json',
        ])) {
            return [];
        }
        $rows = $this->db->table('mobile_approval_requests')
            ->select('id, subject_table, subject_id, result_table, result_id, payload_json')
            ->where('request_type', 'followup')->get()->getResultArray();
        $followupLookup = array_fill_keys($followupIds, true);
        $ids = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $linkedFollowupId = (int) ($payload['followup_id'] ?? 0);
            $matches = (int) ($payload['order_id'] ?? 0) === $childId
                || ((string) ($row['subject_table'] ?? '') === 'order_followups'
                    && isset($followupLookup[(int) ($row['subject_id'] ?? 0)]))
                || ((string) ($row['result_table'] ?? '') === 'order_followups'
                    && isset($followupLookup[(int) ($row['result_id'] ?? 0)]))
                || isset($followupLookup[$linkedFollowupId]);
            if ($matches) {
                $ids[] = (int) $row['id'];
            }
        }
        return array_values(array_filter(array_unique($ids)));
    }

    /** @param list<string> $fields */
    private function hasFields(string $table, array $fields): bool
    {
        if (! $this->db->tableExists($table)) {
            return false;
        }
        foreach ($fields as $field) {
            if (! $this->db->fieldExists($field, $table)) {
                return false;
            }
        }
        return true;
    }
}
