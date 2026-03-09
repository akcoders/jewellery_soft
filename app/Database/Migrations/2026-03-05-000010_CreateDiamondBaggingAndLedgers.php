<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDiamondBaggingAndLedgers extends Migration
{
    public function up()
    {
        $this->createDiamondBags();
        $this->createDiamondBagItems();
        $this->createDiamondIssues();
        $this->createGoldLedgerEntries();
        $this->createDiamondLedgerEntries();
    }

    public function down()
    {
        $this->forge->dropTable('diamond_ledger_entries', true);
        $this->forge->dropTable('gold_ledger_entries', true);
        $this->forge->dropTable('diamond_issues', true);
        $this->forge->dropTable('diamond_bag_items', true);
        $this->forge->dropTable('diamond_bags', true);
    }

    private function createDiamondBags(): void
    {
        if ($this->db->tableExists('diamond_bags')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'bag_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'order_id' => [
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
        $this->forge->addUniqueKey('bag_no');
        $this->forge->addKey('order_id');
        $this->forge->createTable('diamond_bags', true);
    }

    private function createDiamondBagItems(): void
    {
        if ($this->db->tableExists('diamond_bag_items')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'bag_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'diamond_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'quality' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'pcs_total' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_cts_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'pcs_available' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_cts_available' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('bag_id');
        $this->forge->createTable('diamond_bag_items', true);
    }

    private function createDiamondIssues(): void
    {
        if ($this->db->tableExists('diamond_issues')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'bag_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'bag_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'issue_pcs' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'issue_weight_cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
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
        $this->forge->addKey('order_id');
        $this->forge->addKey('bag_id');
        $this->forge->addKey('bag_item_id');
        $this->forge->createTable('diamond_issues', true);
    }

    private function createGoldLedgerEntries(): void
    {
        if ($this->db->tableExists('gold_ledger_entries')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'entry_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
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
        $this->forge->addKey('order_id');
        $this->forge->addKey('entry_type');
        $this->forge->createTable('gold_ledger_entries', true);
    }

    private function createDiamondLedgerEntries(): void
    {
        if ($this->db->tableExists('diamond_ledger_entries')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'bag_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'bag_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'entry_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'pcs' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
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
        $this->forge->addKey('order_id');
        $this->forge->addKey('entry_type');
        $this->forge->createTable('diamond_ledger_entries', true);
    }
}

