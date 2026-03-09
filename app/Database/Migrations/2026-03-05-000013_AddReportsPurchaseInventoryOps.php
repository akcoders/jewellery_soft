<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReportsPurchaseInventoryOps extends Migration
{
    public function up()
    {
        $this->addLocationColumns();
        $this->createVendors();
        $this->createPurchases();
        $this->createPurchaseItems();
        $this->createInventoryTransactions();
    }

    public function down()
    {
        $this->forge->dropTable('inventory_transactions', true);
        $this->forge->dropTable('purchase_items', true);
        $this->forge->dropTable('purchases', true);
        $this->forge->dropTable('vendors', true);

        $this->dropLocationColumns('stone_ledger_entries');
        $this->dropLocationColumns('stone_issues');
        $this->dropLocationColumns('diamond_ledger_entries');
        $this->dropLocationColumns('gold_ledger_entries');
        $this->dropLocationColumns('order_material_movements');
    }

    private function addLocationColumns(): void
    {
        $tables = [
            'order_material_movements',
            'gold_ledger_entries',
            'diamond_ledger_entries',
            'stone_ledger_entries',
            'stone_issues',
        ];

        foreach ($tables as $table) {
            if (! $this->db->tableExists($table) || $this->db->fieldExists('location_id', $table)) {
                continue;
            }

            $this->forge->addColumn($table, [
                'location_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'karigar_id',
                ],
            ]);

            $indexName = 'idx_' . substr(str_replace('_', '', $table), 0, 18) . '_loc';
            $this->db->query('CREATE INDEX ' . $indexName . ' ON ' . $table . ' (location_id)');
        }
    }

    private function dropLocationColumns(string $table): void
    {
        if (! $this->db->tableExists($table) || ! $this->db->fieldExists('location_id', $table)) {
            return;
        }
        $this->forge->dropColumn($table, 'location_id');
    }

    private function createVendors(): void
    {
        if ($this->db->tableExists('vendors')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'contact_person' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'gstin' => [
                'type'       => 'VARCHAR',
                'constraint' => 25,
                'null'       => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('vendors', true);
    }

    private function createPurchases(): void
    {
        if ($this->db->tableExists('purchases')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'purchase_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'vendor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'purchase_date' => [
                'type' => 'DATE',
            ],
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('purchase_no');
        $this->forge->addKey('vendor_id');
        $this->forge->addKey('location_id');
        $this->forge->createTable('purchases', true);
    }

    private function createPurchaseItems(): void
    {
        if ($this->db->tableExists('purchase_items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'purchase_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'material_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'gold_purity_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'diamond_shape' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_sieve' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_color' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_clarity' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'pcs' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('purchase_id');
        $this->forge->addKey('item_type');
        $this->forge->createTable('purchase_items', true);
    }

    private function createInventoryTransactions(): void
    {
        if ($this->db->tableExists('inventory_transactions')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'txn_date' => [
                'type' => 'DATE',
            ],
            'transaction_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'counter_location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'material_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'gold_purity_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'diamond_shape' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_sieve' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_color' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'diamond_clarity' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'pcs' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'reference_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('txn_date');
        $this->forge->addKey('transaction_type');
        $this->forge->addKey('location_id');
        $this->forge->addKey('item_type');
        $this->forge->createTable('inventory_transactions', true);
    }
}
