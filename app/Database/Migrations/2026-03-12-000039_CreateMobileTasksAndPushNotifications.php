<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMobileTasksAndPushNotifications extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('mobile_tasks')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'admin_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 160,
                    'null' => false,
                ],
                'note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'scheduled_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'pending',
                    'null' => false,
                ],
                'is_done' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'null' => false,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
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
            $this->forge->addKey('id', true);
            $this->forge->addKey('admin_user_id');
            $this->forge->addKey('scheduled_at');
            $this->forge->addKey('status');
            $this->forge->createTable('mobile_tasks', true);
        }

        if (! $this->db->tableExists('mobile_push_notifications')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'admin_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'external_user_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 190,
                    'null' => true,
                ],
                'type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => false,
                ],
                'reference_table' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
                'reference_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 160,
                    'null' => false,
                ],
                'message' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'payload_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'scheduled_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'sent_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'onesignal_message_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'pending',
                    'null' => false,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'response_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
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
            $this->forge->addKey('id', true);
            $this->forge->addKey('admin_user_id');
            $this->forge->addKey('scheduled_at');
            $this->forge->addKey('status');
            $this->forge->addKey(['reference_table', 'reference_id']);
            $this->forge->createTable('mobile_push_notifications', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('mobile_push_notifications')) {
            $this->forge->dropTable('mobile_push_notifications', true);
        }

        if ($this->db->tableExists('mobile_tasks')) {
            $this->forge->dropTable('mobile_tasks', true);
        }
    }
}
