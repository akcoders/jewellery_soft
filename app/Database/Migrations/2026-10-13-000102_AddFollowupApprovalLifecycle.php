<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFollowupApprovalLifecycle extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('order_followups')) {
            return;
        }

        $fields = [];
        $definitions = [
            'approval_status' => [
                'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'approved',
                'after' => 'image_path',
            ],
            'approval_request_id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
                'after' => 'approval_status',
            ],
            'reviewed_by' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                'after' => 'approval_request_id',
            ],
            'reviewed_at' => [
                'type' => 'DATETIME', 'null' => true, 'after' => 'reviewed_by',
            ],
            'review_note' => [
                'type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'reviewed_at',
            ],
        ];
        foreach ($definitions as $name => $definition) {
            if (! $this->db->fieldExists($name, 'order_followups')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('order_followups', $fields);
        }

        $indexes = $this->db->getIndexData('order_followups');
        if (! isset($indexes['idx_followup_approval_status'])) {
            $this->db->query('ALTER TABLE `order_followups` ADD INDEX `idx_followup_approval_status` (`approval_status`, `followup_taken_by`, `followup_taken_on`)');
        }
        if (! isset($indexes['idx_followup_approval_request'])) {
            $this->db->query('ALTER TABLE `order_followups` ADD INDEX `idx_followup_approval_request` (`approval_request_id`)');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('order_followups')) {
            return;
        }
        $indexes = $this->db->getIndexData('order_followups');
        foreach (['idx_followup_approval_status', 'idx_followup_approval_request'] as $index) {
            if (isset($indexes[$index])) {
                $this->forge->dropKey('order_followups', $index);
            }
        }
        foreach (['review_note', 'reviewed_at', 'reviewed_by', 'approval_request_id', 'approval_status'] as $column) {
            if ($this->db->fieldExists($column, 'order_followups')) {
                $this->forge->dropColumn('order_followups', $column);
            }
        }
    }
}
