<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPurityAndKarigarToMovementAndLedgers extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('gold_purity_id', 'order_material_movements')) {
            $this->forge->addColumn('order_material_movements', [
                'gold_purity_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'diamond_cts',
                ],
                'karigar_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'gold_purity_id',
                ],
            ]);
            $this->db->query('CREATE INDEX idx_omm_karigar_id ON order_material_movements (karigar_id)');
        }

        if (! $this->db->fieldExists('gold_purity_id', 'gold_ledger_entries')) {
            $this->forge->addColumn('gold_ledger_entries', [
                'gold_purity_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'weight_gm',
                ],
                'karigar_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'gold_purity_id',
                ],
            ]);
            $this->db->query('CREATE INDEX idx_gle_karigar_id ON gold_ledger_entries (karigar_id)');
        }

        if (! $this->db->fieldExists('karigar_id', 'diamond_ledger_entries')) {
            $this->forge->addColumn('diamond_ledger_entries', [
                'karigar_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'bag_item_id',
                ],
            ]);
            $this->db->query('CREATE INDEX idx_dle_karigar_id ON diamond_ledger_entries (karigar_id)');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('gold_purity_id', 'order_material_movements')) {
            $this->forge->dropColumn('order_material_movements', 'karigar_id');
            $this->forge->dropColumn('order_material_movements', 'gold_purity_id');
        }

        if ($this->db->fieldExists('gold_purity_id', 'gold_ledger_entries')) {
            $this->forge->dropColumn('gold_ledger_entries', 'karigar_id');
            $this->forge->dropColumn('gold_ledger_entries', 'gold_purity_id');
        }

        if ($this->db->fieldExists('karigar_id', 'diamond_ledger_entries')) {
            $this->forge->dropColumn('diamond_ledger_entries', 'karigar_id');
        }
    }
}

