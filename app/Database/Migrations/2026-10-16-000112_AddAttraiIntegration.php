<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttraiIntegration extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $fields = [];
        $definitions = [
            'attrai_enabled' => [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false,
            ],
            'attrai_api_url' => [
                'type' => 'VARCHAR', 'constraint' => 500, 'null' => true,
            ],
            'attrai_http_method' => [
                'type' => 'VARCHAR', 'constraint' => 10, 'default' => 'POST', 'null' => false,
            ],
            'attrai_auth_type' => [
                'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'bearer', 'null' => false,
            ],
            'attrai_auth_header' => [
                'type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Authorization', 'null' => false,
            ],
            'attrai_auth_token' => [
                'type' => 'VARCHAR', 'constraint' => 500, 'null' => true,
            ],
            'attrai_sender_id' => [
                'type' => 'VARCHAR', 'constraint' => 190, 'null' => true,
            ],
            'attrai_extra_headers_json' => [
                'type' => 'TEXT', 'null' => true,
            ],
            'attrai_body_template' => [
                'type' => 'LONGTEXT', 'null' => true,
            ],
            'attrai_approval_numbers' => [
                'type' => 'TEXT', 'null' => true,
            ],
            'attrai_webhook_secret' => [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true,
            ],
            'attrai_approval_reviewer_id' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
            ],
            'attrai_notify_approval_requested' => [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false,
            ],
            'attrai_notify_karigar_assigned' => [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false,
            ],
            'attrai_template_approval_requested' => [
                'type' => 'TEXT', 'null' => true,
            ],
            'attrai_template_karigar_assigned' => [
                'type' => 'TEXT', 'null' => true,
            ],
            'attrai_template_follower_assigned' => [
                'type' => 'TEXT', 'null' => true,
            ],
            'attrai_approval_body_template' => [
                'type' => 'LONGTEXT', 'null' => true,
            ],
        ];

        foreach ($definitions as $name => $definition) {
            if (! $this->db->fieldExists($name, 'company_settings')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('company_settings', $fields);
        }

        foreach ([
            'orders' => ['attrai_notification_number', 'whatsapp_notification_number'],
            'karigars' => ['attrai_number', 'phone'],
            'employees' => ['attrai_number', 'mobile'],
        ] as $table => [$column, $after]) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists($column, $table)) {
                $this->forge->addColumn($table, [
                    $column => [
                        'type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => $after,
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('whatsapp_sender_queue')
            && ! $this->db->fieldExists('provider_key', 'whatsapp_sender_queue')) {
            $this->forge->addColumn('whatsapp_sender_queue', [
                'provider_key' => [
                    'type' => 'VARCHAR', 'constraint' => 40, 'default' => 'whatsapp', 'null' => false,
                    'after' => 'event_key',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $columns = [
            'attrai_enabled',
            'attrai_api_url',
            'attrai_http_method',
            'attrai_auth_type',
            'attrai_auth_header',
            'attrai_auth_token',
            'attrai_sender_id',
            'attrai_extra_headers_json',
            'attrai_body_template',
            'attrai_approval_numbers',
            'attrai_webhook_secret',
            'attrai_approval_reviewer_id',
            'attrai_notify_approval_requested',
            'attrai_notify_karigar_assigned',
            'attrai_template_approval_requested',
            'attrai_template_karigar_assigned',
            'attrai_template_follower_assigned',
            'attrai_approval_body_template',
        ];
        $drop = array_values(array_filter(
            $columns,
            fn(string $column): bool => $this->db->fieldExists($column, 'company_settings')
        ));
        if ($drop !== []) {
            $this->forge->dropColumn('company_settings', $drop);
        }
        if ($this->db->tableExists('whatsapp_sender_queue')
            && $this->db->fieldExists('provider_key', 'whatsapp_sender_queue')) {
            $this->forge->dropColumn('whatsapp_sender_queue', 'provider_key');
        }
        foreach ([
            'orders' => 'attrai_notification_number',
            'karigars' => 'attrai_number',
            'employees' => 'attrai_number',
        ] as $table => $column) {
            if ($this->db->tableExists($table) && $this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}
