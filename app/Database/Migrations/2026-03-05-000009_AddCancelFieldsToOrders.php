<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancelFieldsToOrders extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('cancel_reason', 'orders')) {
            $this->forge->addColumn('orders', [
                'cancel_reason' => [
                    'type'  => 'TEXT',
                    'null'  => true,
                    'after' => 'order_notes',
                ],
                'cancelled_at' => [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'cancel_reason',
                ],
                'cancelled_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'cancelled_at',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('cancel_reason', 'orders')) {
            $this->forge->dropColumn('orders', 'cancel_reason');
            $this->forge->dropColumn('orders', 'cancelled_at');
            $this->forge->dropColumn('orders', 'cancelled_by');
        }
    }
}

