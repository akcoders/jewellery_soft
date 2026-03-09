<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiveCalcAndStoneIssue extends Migration
{
    public function up()
    {
        $this->addReceiveColumnsToMovements();
        $this->addReceiveColumnsToGoldLedger();
        $this->createStoneIssuesTable();
        $this->createStoneLedgerTable();
    }

    public function down()
    {
        $this->forge->dropTable('stone_ledger_entries', true);
        $this->forge->dropTable('stone_issues', true);

        if ($this->db->fieldExists('purity_percent', 'gold_ledger_entries')) {
            $this->forge->dropColumn('gold_ledger_entries', 'purity_percent');
            $this->forge->dropColumn('gold_ledger_entries', 'pure_gold_weight_gm');
            $this->forge->dropColumn('gold_ledger_entries', 'net_gold_weight_gm');
            $this->forge->dropColumn('gold_ledger_entries', 'diamond_weight_gm');
            $this->forge->dropColumn('gold_ledger_entries', 'other_weight_gm');
            $this->forge->dropColumn('gold_ledger_entries', 'gross_weight_gm');
        }

        if ($this->db->fieldExists('gross_weight_gm', 'order_material_movements')) {
            $this->forge->dropColumn('order_material_movements', 'pure_gold_weight_gm');
            $this->forge->dropColumn('order_material_movements', 'net_gold_weight_gm');
            $this->forge->dropColumn('order_material_movements', 'diamond_weight_gm');
            $this->forge->dropColumn('order_material_movements', 'other_weight_gm');
            $this->forge->dropColumn('order_material_movements', 'gross_weight_gm');
        }
    }

    private function addReceiveColumnsToMovements(): void
    {
        if ($this->db->fieldExists('gross_weight_gm', 'order_material_movements')) {
            return;
        }

        $this->forge->addColumn('order_material_movements', [
            'gross_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'karigar_id',
            ],
            'other_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'gross_weight_gm',
            ],
            'diamond_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'other_weight_gm',
            ],
            'net_gold_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'diamond_weight_gm',
            ],
            'pure_gold_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'net_gold_weight_gm',
            ],
        ]);
    }

    private function addReceiveColumnsToGoldLedger(): void
    {
        if ($this->db->fieldExists('purity_percent', 'gold_ledger_entries')) {
            return;
        }

        $this->forge->addColumn('gold_ledger_entries', [
            'gross_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'gold_purity_id',
            ],
            'other_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'gross_weight_gm',
            ],
            'diamond_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'other_weight_gm',
            ],
            'net_gold_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'diamond_weight_gm',
            ],
            'pure_gold_weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'null'       => true,
                'after'      => 'net_gold_weight_gm',
            ],
            'purity_percent' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,3',
                'null'       => true,
                'after'      => 'pure_gold_weight_gm',
            ],
        ]);
    }

    private function createStoneIssuesTable(): void
    {
        if ($this->db->tableExists('stone_issues')) {
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
            'karigar_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'stone_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'stone_item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'quality' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
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
        $this->forge->addKey('karigar_id');
        $this->forge->createTable('stone_issues', true);
    }

    private function createStoneLedgerTable(): void
    {
        if ($this->db->tableExists('stone_ledger_entries')) {
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
            'karigar_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'entry_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'stone_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'stone_item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'quality' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
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
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('entry_type');
        $this->forge->createTable('stone_ledger_entries', true);
    }
}

