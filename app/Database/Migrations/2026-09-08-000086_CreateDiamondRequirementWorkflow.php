<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDiamondRequirementWorkflow extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('diamond_requirements')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'requirement_no' => ['type' => 'VARCHAR', 'constraint' => 40],
                'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'requested_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'requirement_note' => ['type' => 'TEXT', 'null' => true],
                'required_by' => ['type' => 'DATE', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending_approval'],
                'approved_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'approved_at' => ['type' => 'DATETIME', 'null' => true],
                'assigned_to' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'assigned_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'assigned_at' => ['type' => 'DATETIME', 'null' => true],
                'preparation_due_at' => ['type' => 'DATETIME', 'null' => true],
                'approval_note' => ['type' => 'TEXT', 'null' => true],
                'rejected_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'rejected_at' => ['type' => 'DATETIME', 'null' => true],
                'rejection_reason' => ['type' => 'TEXT', 'null' => true],
                'bag_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'ready_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'ready_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('requirement_no', 'uq_diamond_requirement_no');
            $this->forge->addKey(['order_id', 'status'], false, false, 'idx_diamond_requirement_order_status');
            $this->forge->addKey(['assigned_to', 'status'], false, false, 'idx_diamond_requirement_assignee_status');
            $this->forge->addKey('bag_id', false, false, 'idx_diamond_requirement_bag');
            $this->forge->createTable('diamond_requirements', true);
        }

        if ($this->db->tableExists('diamond_bags') && ! $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            $this->forge->addColumn('diamond_bags', [
                'requirement_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'order_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('diamond_bags') && $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            $this->forge->dropColumn('diamond_bags', 'requirement_id');
        }
        if ($this->db->tableExists('diamond_requirements')) {
            $this->forge->dropTable('diamond_requirements', true);
        }
    }
}
