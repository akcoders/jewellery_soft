<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddArattaiWebhookAutomation extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('company_settings')) {
            $fields = [];
            if (! $this->db->fieldExists('attrai_webhook_id', 'company_settings')) {
                $fields['attrai_webhook_id'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true];
            }
            if (! $this->db->fieldExists('attrai_webhook_status', 'company_settings')) {
                $fields['attrai_webhook_status'] = ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true];
            }
            if ($fields !== []) {
                $this->forge->addColumn('company_settings', $fields);
            }
        }

        if (! $this->db->tableExists('arattai_webhook_events')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'event_id' => ['type' => 'VARCHAR', 'constraint' => 190],
                'event_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'arattai_user_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'business_number_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'processing_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'received'],
                'result_message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'received_at' => ['type' => 'DATETIME', 'null' => false],
                'processed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('event_id', 'uq_arattai_webhook_event_id');
            $this->forge->addKey(['event_type', 'received_at'], false, false, 'idx_arattai_webhook_type_received');
            $this->forge->createTable('arattai_webhook_events');
        }

        if (! $this->db->tableExists('arattai_users')) {
            $this->forge->addField([
                'arattai_user_id' => ['type' => 'VARCHAR', 'constraint' => 80],
                'name' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'last_message_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => false],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('arattai_user_id');
            $this->forge->addKey('last_seen_at');
            $this->forge->createTable('arattai_users');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('arattai_webhook_events', true);
        $this->forge->dropTable('arattai_users', true);
        if ($this->db->tableExists('company_settings')) {
            foreach (['attrai_webhook_id', 'attrai_webhook_status'] as $field) {
                if ($this->db->fieldExists($field, 'company_settings')) {
                    $this->forge->dropColumn('company_settings', $field);
                }
            }
        }
    }
}
