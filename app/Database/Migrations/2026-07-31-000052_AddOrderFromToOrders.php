<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderFromToOrders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('orders') || $this->db->fieldExists('order_from', 'orders')) {
            return;
        }

        $this->forge->addColumn('orders', [
            'order_from' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
                'after' => 'order_type',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('orders') && $this->db->fieldExists('order_from', 'orders')) {
            $this->forge->dropColumn('orders', 'order_from');
        }
    }
}
