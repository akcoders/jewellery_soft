<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWhatsappToCompanySettings extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_settings')) {
            $fields = [];

            $this->addFieldIfMissing($fields, 'whatsapp_enabled', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'null' => false,
                'after' => 'onesignal_sender_id',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_api_url', [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'whatsapp_enabled',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_http_method', [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'default' => 'POST',
                'null' => false,
                'after' => 'whatsapp_api_url',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_auth_type', [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'none',
                'null' => false,
                'after' => 'whatsapp_http_method',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_auth_header', [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'whatsapp_auth_type',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_auth_token', [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'whatsapp_auth_header',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_sender_id', [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'whatsapp_auth_token',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_timeout_sec', [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 20,
                'null' => false,
                'after' => 'whatsapp_sender_id',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_alert_numbers', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_timeout_sec',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_extra_headers_json', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_alert_numbers',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_body_template', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_extra_headers_json',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_notify_order_created', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
                'after' => 'whatsapp_body_template',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_notify_order_status_changed', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
                'after' => 'whatsapp_notify_order_created',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_notify_order_ready', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
                'after' => 'whatsapp_notify_order_status_changed',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_notify_order_over_budget', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
                'after' => 'whatsapp_notify_order_ready',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_notify_order_delay_daily', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
                'after' => 'whatsapp_notify_order_over_budget',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_template_order_created', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_notify_order_delay_daily',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_template_order_status_changed', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_template_order_created',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_template_order_ready', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_template_order_status_changed',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_template_order_over_budget', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_template_order_ready',
            ]);
            $this->addFieldIfMissing($fields, 'whatsapp_template_order_delay_daily', [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'whatsapp_template_order_over_budget',
            ]);

            if ($fields !== []) {
                $this->forge->addColumn('company_settings', $fields);
            }
        }

        if (! $this->db->tableExists('whatsapp_message_logs')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'event_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                ],
                'event_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 190,
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
                'recipient_phone' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ],
                'message_text' => [
                    'type' => 'TEXT',
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
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'pending',
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'sent_on' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('event_key');
            $this->forge->addKey('event_hash');
            $this->forge->addKey('order_id');
            $this->forge->addKey('customer_id');
            $this->forge->addKey('sent_on');
            $this->forge->createTable('whatsapp_message_logs', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('company_settings')) {
            $columns = [
                'whatsapp_enabled',
                'whatsapp_api_url',
                'whatsapp_http_method',
                'whatsapp_auth_type',
                'whatsapp_auth_header',
                'whatsapp_auth_token',
                'whatsapp_sender_id',
                'whatsapp_timeout_sec',
                'whatsapp_alert_numbers',
                'whatsapp_extra_headers_json',
                'whatsapp_body_template',
                'whatsapp_notify_order_created',
                'whatsapp_notify_order_status_changed',
                'whatsapp_notify_order_ready',
                'whatsapp_notify_order_over_budget',
                'whatsapp_notify_order_delay_daily',
                'whatsapp_template_order_created',
                'whatsapp_template_order_status_changed',
                'whatsapp_template_order_ready',
                'whatsapp_template_order_over_budget',
                'whatsapp_template_order_delay_daily',
            ];

            $drop = [];
            foreach ($columns as $column) {
                if ($this->db->fieldExists($column, 'company_settings')) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $this->forge->dropColumn('company_settings', $drop);
            }
        }

        if ($this->db->tableExists('whatsapp_message_logs')) {
            $this->forge->dropTable('whatsapp_message_logs', true);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $fields
     * @param array<string,mixed> $definition
     */
    private function addFieldIfMissing(array &$fields, string $column, array $definition): void
    {
        if (! $this->db->fieldExists($column, 'company_settings')) {
            $fields[$column] = $definition;
        }
    }
}
