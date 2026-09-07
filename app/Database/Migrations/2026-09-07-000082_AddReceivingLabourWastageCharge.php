<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceivingLabourWastageCharge extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('order_receive_summaries')) {
            return;
        }

        $columns = [
            'gold_purity_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'order_id',
            ],
            'purity_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '8,3',
                'default' => 0,
                'after' => 'gold_purity_id',
            ],
            'wastage_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '8,3',
                'default' => 0,
                'after' => 'labour_amount',
            ],
            'wastage_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
                'after' => 'wastage_percent',
            ],
            'pure_wastage_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
                'after' => 'wastage_weight_gm',
            ],
            'wastage_account_voucher_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'stone_account_voucher_id',
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'order_receive_summaries')) {
                $this->forge->addColumn('order_receive_summaries', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('order_receive_summaries')) {
            return;
        }

        foreach ([
            'wastage_account_voucher_id',
            'pure_wastage_weight_gm',
            'wastage_weight_gm',
            'wastage_percent',
            'purity_percent',
            'gold_purity_id',
        ] as $column) {
            if ($this->db->fieldExists($column, 'order_receive_summaries')) {
                $this->forge->dropColumn('order_receive_summaries', $column);
            }
        }
    }
}
