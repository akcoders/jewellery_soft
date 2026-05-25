<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWhatsappSenderQueueTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('whatsapp_sender_queue')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'message_no' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
                'unique' => true,
            ],
            'event_key' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'source_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'customer_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'sender_number' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
                'null' => true,
            ],
            'recipient_number' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
            ],
            'recipient_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'message_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'text',
            ],
            'template_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'message_text' => [
                'type' => 'TEXT',
            ],
            'media_url' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'payload_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'request_payload' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'response_payload' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'http_status_code' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'provider_message_id' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'pending',
            ],
            'attempts' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'max_attempts' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 3,
            ],
            'scheduled_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'failed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_attempt_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('scheduled_at');
        $this->forge->addKey('event_key');
        $this->forge->addKey(['source_type', 'source_id']);
        $this->forge->addKey('order_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('recipient_number');
        $this->forge->addKey('provider_message_id');
        $this->forge->createTable('whatsapp_sender_queue', true);
    }

    public function down()
    {
        if ($this->db->tableExists('whatsapp_sender_queue')) {
            $this->forge->dropTable('whatsapp_sender_queue', true);
        }
    }
}
