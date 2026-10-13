<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFollowupQualityScoring extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('order_followups')) return;
        $columns = [
            'normalized_text' => ['type' => 'TEXT', 'null' => true],
            'normalized_fingerprint' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'duplicate_followup_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'duplicate_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'duplicate_override' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
        ];
        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'order_followups')) $this->forge->addColumn('order_followups', [$name => $definition]);
        }
        $indexes = $this->db->getIndexData('order_followups');
        if (! isset($indexes['idx_followup_duplicate_lookup'])) {
            $this->db->query('ALTER TABLE `order_followups` ADD INDEX `idx_followup_duplicate_lookup` (`order_id`, `followup_taken_by`, `followup_taken_on`)');
        }
        if (! $this->db->tableExists('followup_duplicate_override_audits')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'followup_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'previous_override' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'new_override' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'reason' => ['type' => 'VARCHAR', 'constraint' => 500],
                'changed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('followup_id');
            $this->forge->createTable('followup_duplicate_override_audits', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('followup_duplicate_override_audits', true);
        if ($this->db->tableExists('order_followups')) {
            $indexes = $this->db->getIndexData('order_followups');
            if (isset($indexes['idx_followup_duplicate_lookup'])) $this->forge->dropKey('order_followups', 'idx_followup_duplicate_lookup');
            foreach (['normalized_text', 'normalized_fingerprint', 'duplicate_followup_id', 'duplicate_reason', 'duplicate_override'] as $column) {
                if ($this->db->fieldExists($column, 'order_followups')) $this->forge->dropColumn('order_followups', $column);
            }
        }
    }
}
