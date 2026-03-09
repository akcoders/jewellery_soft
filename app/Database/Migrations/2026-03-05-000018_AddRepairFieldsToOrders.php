<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRepairFieldsToOrders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $columns = [
            'repair_ornament_details' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'order_notes',
            ],
            'repair_work_details' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'repair_ornament_details',
            ],
            'repair_receive_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'null' => true,
                'after' => 'repair_work_details',
            ],
            'repair_received_at' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'repair_receive_weight_gm',
            ],
        ];

        foreach ($columns as $name => $def) {
            if (! $this->db->fieldExists($name, 'orders')) {
                $this->forge->addColumn('orders', [$name => $def]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $columns = [
            'repair_received_at',
            'repair_receive_weight_gm',
            'repair_work_details',
            'repair_ornament_details',
        ];

        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, 'orders')) {
                $this->forge->dropColumn('orders', $column);
            }
        }
    }
}
