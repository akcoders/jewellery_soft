<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderWorkRequests extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('order_work_requests')) {
            $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'unsigned' => true],
            'request_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'details' => ['type' => 'TEXT'],
            'requested_due_at' => ['type' => 'DATETIME', 'null' => true],
            'gold_quantity_gm' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'requested_by' => ['type' => 'INT', 'unsigned' => true],
            'reviewed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'review_note' => ['type' => 'TEXT', 'null' => true],
            'assigned_to' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'voucher_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['order_id', 'status']);
            $this->forge->addKey(['assigned_to', 'status']);
            $this->forge->createTable('order_work_requests', true);
        }

        // Existing assigned follow-ups must appear in My Tasks after deployment.
        if ($this->db->tableExists('order_followup_schedules') && $this->db->tableExists('mobile_tasks')
            && $this->db->fieldExists('reference_type', 'mobile_tasks')) {
            $rows = $this->db->table('order_followup_schedules s')
                ->select('s.id, s.assigned_to, s.due_at, s.created_by, o.order_no')
                ->join('orders o', 'o.id = s.order_id', 'inner')
                ->where('s.status', 'pending')->get()->getResultArray();
            foreach ($rows as $row) {
                $exists = $this->db->table('mobile_tasks')->where('reference_type', 'order_followup')
                    ->where('reference_id', (int) $row['id'])->countAllResults();
                if ($exists) continue;
                $this->db->table('mobile_tasks')->insert([
                    'admin_user_id' => (int) $row['assigned_to'],
                    'title' => 'Follow up order ' . (string) $row['order_no'],
                    'scheduled_at' => (string) $row['due_at'],
                    'status' => 'pending', 'is_done' => 0,
                    'counts_for_performance' => 1, 'score_delta' => 0,
                    'reference_type' => 'order_followup', 'reference_id' => (int) $row['id'],
                    'created_by' => (int) ($row['created_by'] ?? 0) ?: null,
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('order_work_requests', true);
    }
}
