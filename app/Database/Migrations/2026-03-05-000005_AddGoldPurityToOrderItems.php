<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGoldPurityToOrderItems extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('gold_purity_id', 'order_items')) {
            $this->forge->addColumn('order_items', [
                'gold_purity_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'variant_id',
                ],
            ]);
            $this->db->query('CREATE INDEX idx_order_items_gold_purity_id ON order_items (gold_purity_id)');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('gold_purity_id', 'order_items')) {
            $this->forge->dropColumn('order_items', 'gold_purity_id');
        }
    }
}

