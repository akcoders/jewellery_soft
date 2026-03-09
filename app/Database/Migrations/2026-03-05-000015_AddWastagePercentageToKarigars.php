<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWastagePercentageToKarigars extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('karigars')) {
            return;
        }

        if (! $this->db->fieldExists('wastage_percentage', 'karigars')) {
            $this->forge->addColumn('karigars', [
                'wastage_percentage' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0.00,
                    'null'       => false,
                    'after'      => 'rate_per_gm',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('karigars') && $this->db->fieldExists('wastage_percentage', 'karigars')) {
            $this->forge->dropColumn('karigars', 'wastage_percentage');
        }
    }
}
