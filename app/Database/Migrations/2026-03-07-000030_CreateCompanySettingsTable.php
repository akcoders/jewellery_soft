<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanySettingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_settings')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'company_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'address_line' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'state' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'pincode' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'gstin' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'logo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'issuement_suffix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'delivery_challan_suffix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'sale_bill_suffix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('company_settings', true);
    }

    public function down()
    {
        if ($this->db->tableExists('company_settings')) {
            $this->forge->dropTable('company_settings', true);
        }
    }
}

