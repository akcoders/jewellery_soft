<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStandaloneDeliveryChallans extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_settings')) {
            $columns = [];
            if (! $this->db->fieldExists('delivery_challan_last_number', 'company_settings')) {
                $columns['delivery_challan_last_number'] = [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0,
                    'after' => 'delivery_challan_suffix',
                ];
            }
            if (! $this->db->fieldExists('mumbai_branch_address', 'company_settings')) {
                $columns['mumbai_branch_address'] = ['type' => 'TEXT', 'null' => true];
            }
            if (! $this->db->fieldExists('hyderabad_branch_address', 'company_settings')) {
                $columns['hyderabad_branch_address'] = ['type' => 'TEXT', 'null' => true];
            }
            if ($columns !== []) {
                $this->forge->addColumn('company_settings', $columns);
            }
        }

        if (! $this->db->tableExists('delivery_challans')) {
            return;
        }

        $this->forge->modifyColumn('delivery_challans', [
            'order_id' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
            ],
        ]);

        $columns = [];
        $definitions = [
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'dispatch_from' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'dispatch_from_address' => ['type' => 'TEXT', 'null' => true],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'customer_address' => ['type' => 'TEXT', 'null' => true],
            'customer_gstin' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'total_pcs' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ];
        foreach ($definitions as $name => $definition) {
            if (! $this->db->fieldExists($name, 'delivery_challans')) {
                $columns[$name] = $definition;
            }
        }
        if ($columns !== []) {
            $this->forge->addColumn('delivery_challans', $columns);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_challans')) {
            foreach (['customer_id', 'dispatch_from', 'dispatch_from_address', 'customer_name', 'customer_address', 'customer_gstin', 'total_pcs', 'notes'] as $column) {
                if ($this->db->fieldExists($column, 'delivery_challans')) {
                    $this->forge->dropColumn('delivery_challans', $column);
                }
            }
        }
        if ($this->db->tableExists('company_settings')) {
            foreach (['delivery_challan_last_number', 'mumbai_branch_address', 'hyderabad_branch_address'] as $column) {
                if ($this->db->fieldExists($column, 'company_settings')) {
                    $this->forge->dropColumn('company_settings', $column);
                }
            }
        }
    }
}
