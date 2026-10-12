<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceivingWastageTypeAndRepairIssueWeight extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('order_receive_summaries')) {
            if (! $this->db->fieldExists('effective_purity_percent', 'order_receive_summaries')) {
                $this->forge->addColumn('order_receive_summaries', [
                    'effective_purity_percent' => [
                        'type' => 'DECIMAL',
                        'constraint' => '8,3',
                        'default' => 0,
                        'after' => 'purity_percent',
                    ],
                ]);
            }
            if (! $this->db->fieldExists('wastage_type', 'order_receive_summaries')) {
                $this->forge->addColumn('order_receive_summaries', [
                    'wastage_type' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'default' => 'normal',
                        'after' => 'wastage_percent',
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('orders') && ! $this->db->fieldExists('repair_issue_weight_gm', 'orders')) {
            $this->forge->addColumn('orders', [
                'repair_issue_weight_gm' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'null' => true,
                    'after' => 'repair_receive_weight_gm',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('orders') && $this->db->fieldExists('repair_issue_weight_gm', 'orders')) {
            $this->forge->dropColumn('orders', 'repair_issue_weight_gm');
        }
        if ($this->db->tableExists('order_receive_summaries')) {
            foreach (['wastage_type', 'effective_purity_percent'] as $column) {
                if ($this->db->fieldExists($column, 'order_receive_summaries')) {
                    $this->forge->dropColumn('order_receive_summaries', $column);
                }
            }
        }
    }
}
