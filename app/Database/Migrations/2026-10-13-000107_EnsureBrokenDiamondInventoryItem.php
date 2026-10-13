<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureBrokenDiamondInventoryItem extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('items') || ! $this->db->tableExists('stock')) {
            return;
        }

        $item = $this->db->table('items')
            ->select('id')
            ->where("LOWER(TRIM(diamond_type)) = 'broken'", null, false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        $itemId = (int) ($item['id'] ?? 0);
        if ($itemId <= 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('items')->insert([
                'diamond_type' => 'Broken',
                'shape' => 'Mixed',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'Mix',
                'clarity' => 'Mix',
                'cut' => null,
                'remarks' => 'Permanent weight-only broken diamond inventory',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $itemId = (int) $this->db->insertID();
        }

        if ($itemId > 0 && ! $this->db->table('stock')->where('item_id', $itemId)->countAllResults()) {
            $this->db->table('stock')->insert([
                'item_id' => $itemId,
                'pcs_balance' => 0,
                'carat_balance' => 0,
                'avg_cost_per_carat' => 0,
                'stock_value' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        // Keep the permanent product and its ledger history on rollback.
    }
}
