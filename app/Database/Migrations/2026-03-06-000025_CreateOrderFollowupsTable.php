<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderFollowupsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('order_followups')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'stage' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'next_followup_date' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'followup_taken_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'followup_taken_on' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'image_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'image_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('stage');
        $this->forge->addKey('next_followup_date');
        $this->forge->addKey('followup_taken_on');
        $this->forge->createTable('order_followups', true);
    }

    public function down()
    {
        $this->forge->dropTable('order_followups', true);
    }
}

