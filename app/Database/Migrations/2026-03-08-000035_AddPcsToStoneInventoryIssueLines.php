<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPcsToStoneInventoryIssueLines extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('stone_inventory_issue_lines')) {
            return;
        }

        if (! $this->db->fieldExists('pcs', 'stone_inventory_issue_lines')) {
            $this->forge->addColumn('stone_inventory_issue_lines', [
                'pcs' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,3',
                    'default' => 0,
                    'after' => 'item_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('stone_inventory_issue_lines') && $this->db->fieldExists('pcs', 'stone_inventory_issue_lines')) {
            $this->forge->dropColumn('stone_inventory_issue_lines', 'pcs');
        }
    }
}
