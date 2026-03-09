<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderReceiveAndDeliveryChallanTables extends Migration
{
    public function up()
    {
        $this->createOrderReceiveDetailsTable();
        $this->createOrderReceiveSummariesTable();
        $this->createDeliveryChallansTable();
    }

    public function down()
    {
        $this->forge->dropTable('delivery_challans', true);
        $this->forge->dropTable('order_receive_summaries', true);
        $this->forge->dropTable('order_receive_details', true);
    }

    private function createOrderReceiveDetailsTable(): void
    {
        if ($this->db->tableExists('order_receive_details')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'movement_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'component_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'component_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'pcs' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'weight_cts' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'rate' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'line_total' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('movement_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('component_type');
        $this->forge->createTable('order_receive_details', true);
    }

    private function createOrderReceiveSummariesTable(): void
    {
        if ($this->db->tableExists('order_receive_summaries')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'movement_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'gross_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'net_gold_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'pure_gold_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'diamond_weight_cts' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'diamond_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'stone_weight_cts' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'stone_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'other_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'diamond_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'stone_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'other_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'gold_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'labour_rate_per_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'labour_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'total_valuation' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('movement_id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('order_receive_summaries', true);
    }

    private function createDeliveryChallansTable(): void
    {
        if ($this->db->tableExists('delivery_challans')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'challan_no' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'challan_date' => [
                'type' => 'DATE',
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'packing_list_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'receive_movement_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'gross_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'net_gold_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'diamond_weight_cts' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'color_stone_weight_cts' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'other_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'taxable_value' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'tax_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'default' => 3.00,
            ],
            'tax_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'summary_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('challan_no');
        $this->forge->addKey('order_id');
        $this->forge->addKey('packing_list_id');
        $this->forge->addKey('challan_date');
        $this->forge->createTable('delivery_challans', true);
    }
}

