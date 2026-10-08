<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmployeeMobileControlsAndOrderRatings extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admin_users')) {
            $fields = [];
            $definitions = [
                'followup_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'issuement_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'delivery_challan_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'followup_gallery_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ];
            foreach ($definitions as $name => $definition) {
                if (! $this->db->fieldExists($name, 'admin_users')) {
                    $fields[$name] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('admin_users', $fields);
            }
        }

        if ($this->db->tableExists('mobile_approval_requests')) {
            $fields = [];
            if (! $this->db->fieldExists('subject_table', 'mobile_approval_requests')) {
                $fields['subject_table'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true];
            }
            if (! $this->db->fieldExists('subject_id', 'mobile_approval_requests')) {
                $fields['subject_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
            }
            if ($fields !== []) {
                $this->forge->addColumn('mobile_approval_requests', $fields);
            }
        }

        if ($this->db->tableExists('orders')) {
            $fields = [];
            $definitions = [
                'completion_rating' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => true],
                'rating_comment' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'rated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'rated_at' => ['type' => 'DATETIME', 'null' => true],
            ];
            foreach ($definitions as $name => $definition) {
                if (! $this->db->fieldExists($name, 'orders')) {
                    $fields[$name] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('orders', $fields);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('orders')) {
            foreach (['completion_rating', 'rating_comment', 'rated_by', 'rated_at'] as $field) {
                if ($this->db->fieldExists($field, 'orders')) {
                    $this->forge->dropColumn('orders', $field);
                }
            }
        }
        if ($this->db->tableExists('mobile_approval_requests')) {
            foreach (['subject_table', 'subject_id'] as $field) {
                if ($this->db->fieldExists($field, 'mobile_approval_requests')) {
                    $this->forge->dropColumn('mobile_approval_requests', $field);
                }
            }
        }
        if ($this->db->tableExists('admin_users')) {
            foreach ([
                'followup_requires_approval',
                'issuement_requires_approval',
                'delivery_challan_requires_approval',
                'followup_gallery_enabled',
            ] as $field) {
                if ($this->db->fieldExists($field, 'admin_users')) {
                    $this->forge->dropColumn('admin_users', $field);
                }
            }
        }
    }
}
