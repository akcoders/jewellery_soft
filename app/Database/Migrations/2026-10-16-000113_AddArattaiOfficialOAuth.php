<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddArattaiOfficialOAuth extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $definitions = [
            'attrai_client_id' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'attrai_client_secret' => ['type' => 'TEXT', 'null' => true],
            'attrai_access_token' => ['type' => 'TEXT', 'null' => true],
            'attrai_refresh_token' => ['type' => 'TEXT', 'null' => true],
            'attrai_token_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'attrai_api_domain' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'attrai_company_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attrai_business_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attrai_business_number_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attrai_approval_template_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attrai_approval_template_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'attrai_update_template_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attrai_update_template_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'attrai_connected_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $fields = [];
        foreach ($definitions as $name => $definition) {
            if (! $this->db->fieldExists($name, 'company_settings')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('company_settings', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $columns = [
            'attrai_client_id',
            'attrai_client_secret',
            'attrai_access_token',
            'attrai_refresh_token',
            'attrai_token_expires_at',
            'attrai_api_domain',
            'attrai_company_id',
            'attrai_business_id',
            'attrai_business_number_id',
            'attrai_approval_template_id',
            'attrai_approval_template_status',
            'attrai_update_template_id',
            'attrai_update_template_status',
            'attrai_connected_at',
        ];
        $drop = array_values(array_filter(
            $columns,
            fn (string $column): bool => $this->db->fieldExists($column, 'company_settings')
        ));
        if ($drop !== []) {
            $this->forge->dropColumn('company_settings', $drop);
        }
    }
}
