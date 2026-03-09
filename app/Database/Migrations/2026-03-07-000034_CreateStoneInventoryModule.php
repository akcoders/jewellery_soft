<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStoneInventoryModule extends Migration
{
    public function up()
    {
        $this->createItemsTable();
        $this->createStockTable();
        $this->createPurchaseTables();
        $this->createIssueTables();
        $this->createReturnTables();
        $this->createAdjustmentTables();
        $this->createPurchaseAttachmentTable();
    }

    public function down()
    {
        foreach ([
            'stone_purchase_attachments',
            'stone_inventory_adjustment_lines',
            'stone_inventory_adjustment_headers',
            'stone_inventory_return_lines',
            'stone_inventory_return_headers',
            'stone_inventory_issue_lines',
            'stone_inventory_issue_headers',
            'stone_inventory_purchase_lines',
            'stone_inventory_purchase_headers',
            'stone_inventory_stock',
            'stone_inventory_items',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }

    private function createItemsTable(): void
    {
        if ($this->db->tableExists('stone_inventory_items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'stone_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'default_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'remarks' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['product_name', 'stone_type'], 'uq_stone_item_signature');
        $this->forge->addKey('product_name');
        $this->forge->addKey('stone_type');
        $this->forge->createTable('stone_inventory_items', true);
    }

    private function createStockTable(): void
    {
        if ($this->db->tableExists('stone_inventory_stock')) {
            return;
        }

        $this->forge->addField([
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'qty_balance' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'avg_rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'stock_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('item_id');
        $this->forge->addForeignKey('item_id', 'stone_inventory_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stone_inventory_stock', true);
    }

    private function createPurchaseTables(): void
    {
        if (! $this->db->tableExists('stone_inventory_purchase_headers')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'purchase_date' => ['type' => 'DATE'],
                'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'due_date' => ['type' => 'DATE', 'null' => true],
                'tax_percentage' => ['type' => 'DECIMAL', 'constraint' => '6,3', 'default' => 0],
                'invoice_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('purchase_date');
            $this->forge->addKey('invoice_no');
            $this->forge->addKey('vendor_id');
            $this->forge->addKey('due_date');
            if ($this->db->tableExists('vendors')) {
                $this->forge->addForeignKey('vendor_id', 'vendors', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('stone_inventory_purchase_headers', true);
        }

        if (! $this->db->tableExists('stone_inventory_purchase_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'purchase_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('purchase_id');
            $this->forge->addKey('item_id');
            $this->forge->addForeignKey('purchase_id', 'stone_inventory_purchase_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'stone_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('stone_inventory_purchase_lines', true);
        }
    }

    private function createIssueTables(): void
    {
        if (! $this->db->tableExists('stone_inventory_issue_headers')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'voucher_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'issue_date' => ['type' => 'DATE'],
                'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'issue_to' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'purpose' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'attachment_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('voucher_no');
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
            $this->forge->createTable('stone_inventory_issue_headers', true);
        }

        if (! $this->db->tableExists('stone_inventory_issue_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'issue_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('issue_id');
            $this->forge->addKey('item_id');
            $this->forge->addForeignKey('issue_id', 'stone_inventory_issue_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'stone_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('stone_inventory_issue_lines', true);
        }
    }

    private function createReturnTables(): void
    {
        if (! $this->db->tableExists('stone_inventory_return_headers')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'voucher_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'return_date' => ['type' => 'DATE'],
                'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'issue_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'return_from' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'purpose' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'attachment_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('voucher_no');
            $this->forge->addKey('return_date');
            $this->forge->addKey('order_id');
            $this->forge->addKey('issue_id');
            $this->forge->addKey('karigar_id');
            $this->forge->addKey('location_id');
            if ($this->db->tableExists('orders')) {
                $this->forge->addForeignKey('order_id', 'orders', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('stone_inventory_issue_headers')) {
                $this->forge->addForeignKey('issue_id', 'stone_inventory_issue_headers', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('karigars')) {
                $this->forge->addForeignKey('karigar_id', 'karigars', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('stone_inventory_return_headers', true);
        }

        if (! $this->db->tableExists('stone_inventory_return_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'return_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('return_id');
            $this->forge->addKey('item_id');
            $this->forge->addForeignKey('return_id', 'stone_inventory_return_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'stone_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('stone_inventory_return_lines', true);
        }
    }

    private function createAdjustmentTables(): void
    {
        if (! $this->db->tableExists('stone_inventory_adjustment_headers')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'adjustment_date' => ['type' => 'DATE'],
                'adjustment_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'add'],
                'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('adjustment_date');
            $this->forge->addKey('adjustment_type');
            $this->forge->addKey('location_id');
            if ($this->db->tableExists('inventory_locations')) {
                $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('stone_inventory_adjustment_headers', true);
        }

        if (! $this->db->tableExists('stone_inventory_adjustment_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'adjustment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('adjustment_id');
            $this->forge->addKey('item_id');
            $this->forge->addForeignKey('adjustment_id', 'stone_inventory_adjustment_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'stone_inventory_items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('stone_inventory_adjustment_lines', true);
        }
    }

    private function createPurchaseAttachmentTable(): void
    {
        if ($this->db->tableExists('stone_purchase_attachments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'purchase_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'file_name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'file_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'uploaded_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('purchase_id');
        $this->forge->addForeignKey('purchase_id', 'stone_inventory_purchase_headers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stone_purchase_attachments', true);
    }
}
