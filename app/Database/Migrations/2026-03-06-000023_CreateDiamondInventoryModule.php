<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDiamondInventoryModule extends Migration
{
    public function up()
    {
        $this->createItemsTable();
        $this->createStockTable();
        $this->createPurchaseTables();
        $this->createIssueTables();
    }

    public function down()
    {
        foreach ([
            'issue_lines',
            'issue_headers',
            'purchase_lines',
            'purchase_headers',
            'stock',
            'items',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }

    private function createItemsTable(): void
    {
        if ($this->db->tableExists('items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'diamond_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'shape'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'chalni_from'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'chalni_to'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'color'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'clarity'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'cut'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'remarks'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey([
            'diamond_type',
            'shape',
            'chalni_from',
            'chalni_to',
            'color',
            'clarity',
            'cut',
        ], 'uq_item_signature');
        $this->forge->addKey('diamond_type');
        $this->forge->createTable('items', true);
    }

    private function createStockTable(): void
    {
        if ($this->db->tableExists('stock')) {
            return;
        }

        $this->forge->addField([
            'item_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'pcs_balance' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat_balance' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'avg_cost_per_carat' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'stock_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('item_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stock', true);
    }

    private function createPurchaseTables(): void
    {
        if (! $this->db->tableExists('purchase_headers')) {
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
                'notes'         => ['type' => 'TEXT', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('purchase_date');
            $this->forge->addKey('invoice_no');
            $this->forge->createTable('purchase_headers', true);
        }

        if (! $this->db->tableExists('purchase_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'purchase_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'pcs'         => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'carat'       => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate_per_carat' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'line_value'  => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('purchase_id', 'purchase_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('purchase_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('purchase_lines', true);
        }
    }

    private function createIssueTables(): void
    {
        if (! $this->db->tableExists('issue_headers')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'issue_date'  => ['type' => 'DATE'],
                'issue_to'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'purpose'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes'       => ['type' => 'TEXT', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('issue_date');
            $this->forge->addKey('issue_to');
            $this->forge->createTable('issue_headers', true);
        }

        if (! $this->db->tableExists('issue_lines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'issue_id'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'item_id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'pcs'             => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
                'carat'           => ['type' => 'DECIMAL', 'constraint' => '18,3'],
                'rate_per_carat'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'line_value'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('issue_id', 'issue_headers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addKey('issue_id');
            $this->forge->addKey('item_id');
            $this->forge->createTable('issue_lines', true);
        }
    }
}
