<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkDiamondRequirementTasks extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('mobile_tasks')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('reference_type', 'mobile_tasks')) {
            $fields['reference_type'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'score_delta',
            ];
        }
        if (! $this->db->fieldExists('reference_id', 'mobile_tasks')) {
            $fields['reference_id'] = [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'reference_type',
            ];
        }
        if ($fields !== []) {
            $this->forge->addColumn('mobile_tasks', $fields);
        }

        $this->backfillAssignedRequirementTasks();
    }

    public function down()
    {
        if (! $this->db->tableExists('mobile_tasks')) {
            return;
        }
        foreach (['reference_id', 'reference_type'] as $field) {
            if ($this->db->fieldExists($field, 'mobile_tasks')) {
                $this->forge->dropColumn('mobile_tasks', $field);
            }
        }
    }

    private function backfillAssignedRequirementTasks(): void
    {
        if (! $this->db->tableExists('diamond_requirements')
            || ! $this->db->tableExists('orders')
            || ! $this->db->fieldExists('reference_type', 'mobile_tasks')
            || ! $this->db->fieldExists('reference_id', 'mobile_tasks')) {
            return;
        }

        $requirements = $this->db->table('diamond_requirements dr')
            ->select('dr.*, o.order_no')
            ->join('orders o', 'o.id = dr.order_id', 'left')
            ->whereIn('dr.status', ['assigned', 'preparing'])
            ->where('dr.assigned_to IS NOT NULL', null, false)
            ->get()->getResultArray();
        $now = date('Y-m-d H:i:s');
        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);
            $assignedTo = (int) ($requirement['assigned_to'] ?? 0);
            if ($requirementId <= 0 || $assignedTo <= 0) {
                continue;
            }
            $exists = $this->db->table('mobile_tasks')
                ->where('reference_type', 'diamond_requirement')
                ->where('reference_id', $requirementId)
                ->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $dueAt = trim((string) ($requirement['preparation_due_at'] ?? ''));
            if ($dueAt === '') {
                $requiredBy = trim((string) ($requirement['required_by'] ?? ''));
                $dueAt = $requiredBy !== ''
                    ? date('Y-m-d', strtotime($requiredBy)) . ' 18:00:00'
                    : date('Y-m-d 18:00:00', strtotime('+1 day'));
                $this->db->table('diamond_requirements')->where('id', $requirementId)->update([
                    'preparation_due_at' => $dueAt,
                    'updated_at' => $now,
                ]);
            }

            $requirementNo = trim((string) ($requirement['requirement_no'] ?? '')) ?: ('#' . $requirementId);
            $orderNo = trim((string) ($requirement['order_no'] ?? '')) ?: ('#' . (int) ($requirement['order_id'] ?? 0));
            $instruction = trim((string) ($requirement['requirement_note'] ?? ''));
            $this->db->table('mobile_tasks')->insert([
                'admin_user_id' => $assignedTo,
                'title' => 'Prepare Diamond Bag ' . $requirementNo,
                'note' => 'Order ' . $orderNo . ($instruction !== '' ? ' · ' . $instruction : ''),
                'priority' => strtotime($dueAt) <= strtotime('+1 day') ? 'urgent' : 'high',
                'scheduled_at' => $dueAt,
                'status' => 'pending',
                'is_done' => 0,
                'counts_for_performance' => 1,
                'score_delta' => 0,
                'reference_type' => 'diamond_requirement',
                'reference_id' => $requirementId,
                'created_by' => (int) ($requirement['assigned_by'] ?? 0) ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
