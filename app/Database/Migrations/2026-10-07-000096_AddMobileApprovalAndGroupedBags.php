<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMobileApprovalAndGroupedBags extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('diamond_bag_items')
            && ! $this->db->fieldExists('chalni_group_id', 'diamond_bag_items')) {
            $this->forge->addColumn('diamond_bag_items', [
                'chalni_group_id' => [
                    'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                    'after' => 'size_master_id',
                ],
            ]);
        }

        if (! $this->db->tableExists('mobile_approval_requests')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'request_type' => ['type' => 'VARCHAR', 'constraint' => 40],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'summary' => ['type' => 'VARCHAR', 'constraint' => 255],
                'payload_json' => ['type' => 'LONGTEXT'],
                'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'attachment_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'requested_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'reviewed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
                'review_note' => ['type' => 'TEXT', 'null' => true],
                'result_table' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'result_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'result_reference' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['status', 'request_type'], false, false, 'idx_mobile_approval_queue');
            $this->forge->addKey(['requested_by', 'status'], false, false, 'idx_mobile_approval_requester');
            $this->forge->createTable('mobile_approval_requests', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('mobile_approval_requests')) {
            $this->forge->dropTable('mobile_approval_requests', true);
        }
        if ($this->db->tableExists('diamond_bag_items')
            && $this->db->fieldExists('chalni_group_id', 'diamond_bag_items')) {
            $this->forge->dropColumn('diamond_bag_items', 'chalni_group_id');
        }
    }
}
