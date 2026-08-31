<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIssuementStartCount extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('company_settings')
            || $this->db->fieldExists('issuement_start_count', 'company_settings')) {
            return;
        }

        $this->forge->addColumn('company_settings', [
            'issuement_start_count' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 1,
                'after'      => 'issuement_suffix',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('company_settings')
            && $this->db->fieldExists('issuement_start_count', 'company_settings')) {
            $this->forge->dropColumn('company_settings', 'issuement_start_count');
        }
    }
}
