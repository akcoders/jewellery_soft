<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBrokenDiamondReturnLines extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('return_lines') || $this->db->fieldExists('is_broken', 'return_lines')) {
            return;
        }

        $this->forge->addColumn('return_lines', [
            'is_broken' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
                'null' => false,
                'after' => 'allocation_order_id',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('return_lines') && $this->db->fieldExists('is_broken', 'return_lines')) {
            $this->forge->dropColumn('return_lines', 'is_broken');
        }
    }
}
