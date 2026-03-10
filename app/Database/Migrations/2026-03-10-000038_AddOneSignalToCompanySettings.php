<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOneSignalToCompanySettings extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('onesignal_enabled', 'company_settings')) {
            $fields['onesignal_enabled'] = [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'null' => false,
                'after' => 'sale_bill_suffix',
            ];
        }
        if (! $this->db->fieldExists('onesignal_app_id', 'company_settings')) {
            $fields['onesignal_app_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'onesignal_enabled',
            ];
        }
        if (! $this->db->fieldExists('onesignal_rest_api_key', 'company_settings')) {
            $fields['onesignal_rest_api_key'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'onesignal_app_id',
            ];
        }
        if (! $this->db->fieldExists('onesignal_sender_id', 'company_settings')) {
            $fields['onesignal_sender_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'onesignal_rest_api_key',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('company_settings', $fields);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $columns = [];
        foreach (['onesignal_enabled', 'onesignal_app_id', 'onesignal_rest_api_key', 'onesignal_sender_id'] as $col) {
            if ($this->db->fieldExists($col, 'company_settings')) {
                $columns[] = $col;
            }
        }
        if ($columns !== []) {
            $this->forge->dropColumn('company_settings', $columns);
        }
    }
}

