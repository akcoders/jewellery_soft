<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGoldInventoryModule extends Migration
{
    public function up()
    {
        $this->createItemsTable();
        $this->createStockTable();
        $this->createPurchaseTables();
        $this->createIssueTables();
        $this->createReturnTables();
        $this->createAdjustmentTables();
        $this->createLedgerTable();
    }

    public function down()
    {
        foreach ([
            'gold_inventory_ledger_entries',
            'gold_inventory_adjustment_lines',
            'gold_inventory_adjustment_headers',
            'gold_inventory_return_lines',
            'gold_inventory_return_headers',
            'gold_inventory_issue_lines',
            'gold_inventory_issue_headers',
            'gold_inventory_purchase_lines',
            'gold_inventory_purchase_headers',
            'gold_inventory_stock',
            'gold_inventory_items',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }

    private function createItemsTable(): void
    {
        if ($this->db->tableExists('gold_inventory_items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'gold_purity_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'purity_code'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'purity_percent' => ['type' => 'DECIMAL', 'constraint' => '6,3', 'default' => 0],
            'color_name'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'form_type'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'remarks'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('gold_purity_id');
        $this->forge->addKey('purity_code');
        $this->forge->addKey('form_type');
        $this->forge->addUniqueKey(
            ['gold_purity_id', 'purity_code', 'color_name', 'form_type'],
            'uq_gold_inventory_item_signature'
        );

        if ($this->db->tableExists('gold_purities')) {
            $this->forge->addForeignKey('gold_purity_id', 'gold_purities', 'id', 'SET NULL', 'CASCADE');
        }

        $this->forge->createTable('gold_inventory_items', true);
    }

    private function createStockTable(): void
    {
        if ($this->db->tableExists('gold_inventory_stock')) {
            return;
        }

        $this->forge->addField([
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'weight_balance_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'fine_balance_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'avg_cost_per_gm' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'stock_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('item_id');
        $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('gold_inventory_stock', true);
    }

    private function createPurchaseTables(): void
    {
        if (! $this->db->tableExists('gold_inventory_purchase_headers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'purchase_date' => ['type' => 'DATE'],
                'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'invoice_no'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'location_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'notes'         => ['type' => 'TEXT', 'null' => true],
                'created_by'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('purchase_date');
            $this->forge->addKey('location_id');
            $this->forge->addKey('invoice_no');
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('gold_inventory_purchase_headers', true);
        }

        if (! $this->db->tableExists('gold_inventory_purchase_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'purchase_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'weight_gm'   => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'fine_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'rate_per_gm' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'line_value'  => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('purchase_id', 'gold_inventory_purchase_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('purchase_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('gold_inventory_purchase_lines', true);
        }
    }

    private function createIssueTables(): void
    {
        if (! $this->db->tableExists('gold_inventory_issue_headers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'issue_date'   => ['type' => 'DATE'],
                'order_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'karigar_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'issue_to'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'purpose'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes'        => ['type' => 'TEXT', 'null' => true],
                'created_by'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('issue_date');
            $this->forge->addKey('order_id');
            $this->forge->addKey('karigar_id');
            $this->forge->addKey('location_id');
            if ($this->db->tableExists('orders')) {
                $this->forge->addForeignKey('order_id', 'orders', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('karigars')) {
                $this->forge->addForeignKey('karigar_id', 'karigars', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('gold_inventory_issue_headers', true);
        }

        if (! $this->db->tableExists('gold_inventory_issue_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'issue_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'fine_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'rate_per_gm' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('issue_id', 'gold_inventory_issue_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('issue_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('gold_inventory_issue_lines', true);
        }
    }

    private function createReturnTables(): void
    {
        if (! $this->db->tableExists('gold_inventory_return_headers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'return_date'  => ['type' => 'DATE'],
                'order_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'karigar_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'return_from'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'purpose'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes'        => ['type' => 'TEXT', 'null' => true],
                'created_by'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('return_date');
            $this->forge->addKey('order_id');
            $this->forge->addKey('karigar_id');
            $this->forge->addKey('location_id');
            if ($this->db->tableExists('orders')) {
                $this->forge->addForeignKey('order_id', 'orders', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('karigars')) {
                $this->forge->addForeignKey('karigar_id', 'karigars', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('gold_inventory_return_headers', true);
        }

        if (! $this->db->tableExists('gold_inventory_return_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'return_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'fine_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'rate_per_gm' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('return_id', 'gold_inventory_return_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('return_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('gold_inventory_return_lines', true);
        }
    }

    private function createAdjustmentTables(): void
    {
        if (! $this->db->tableExists('gold_inventory_adjustment_headers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'adjustment_date' => ['type' => 'DATE'],
                'adjustment_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'add'],
                'location_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'notes'           => ['type' => 'TEXT', 'null' => true],
                'created_by'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('adjustment_date');
            $this->forge->addKey('adjustment_type');
            $this->forge->addKey('location_id');
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('gold_inventory_adjustment_headers', true);
        }

        if (! $this->db->tableExists('gold_inventory_adjustment_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'adjustment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'       => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'weight_gm'     => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'fine_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'rate_per_gm'   => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'reason'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('adjustment_id', 'gold_inventory_adjustment_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('adjustment_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('gold_inventory_adjustment_lines', true);
        }
    }

    private function createLedgerTable(): void
    {
        if ($this->db->tableExists('gold_inventory_ledger_entries')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'txn_date' => ['type' => 'DATE'],
            'txn_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'reference_table' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'reference_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'debit_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'credit_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'debit_fine_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'credit_fine_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'balance_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'balance_fine_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'rate_per_gm' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('txn_date');
        $this->forge->addKey('txn_type');
        $this->forge->addKey('item_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('location_id');
        $this->forge->addKey('reference_id');
        $this->forge->addForeignKey('item_id', 'gold_inventory_items', 'id', 'RESTRICT', 'CASCADE');
        if ($this->db->tableExists('orders')) {
            $this->forge->addForeignKey('order_id', 'orders', 'id', 'SET NULL', 'CASCADE');
        }
        if ($this->db->tableExists('karigars')) {
            $this->forge->addForeignKey('karigar_id', 'karigars', 'id', 'SET NULL', 'CASCADE');
        }
        if ($this->db->tableExists('inventory_locations')) {
            $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
        }

        $this->forge->createTable('gold_inventory_ledger_entries', true);
    }
}
