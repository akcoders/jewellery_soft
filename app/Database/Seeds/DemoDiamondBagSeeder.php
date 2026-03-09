<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoDiamondBagSeeder extends Seeder
{
    public function run()
    {
        $order = $this->db->table('orders')->select('id,order_no')->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $order) {
            return;
        }

        $bagNo = 'BAG-DEMO-' . $order['id'];
        $now = date('Y-m-d H:i:s');

        $bagTable = $this->db->table('diamond_bags');
        $bag = $bagTable->where('bag_no', $bagNo)->get()->getRowArray();

        if (! $bag) {
            $bagTable->insert([
                'bag_no'     => $bagNo,
                'order_id'   => (int) $order['id'],
                'notes'      => 'Demo bag for order ' . $order['order_no'],
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $bagId = (int) $this->db->insertID();
        } else {
            $bagId = (int) $bag['id'];
        }

        $itemTable = $this->db->table('diamond_bag_items');
        $exists = $itemTable->where('bag_id', $bagId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        $rows = [
            ['diamond_type' => 'Round', 'size' => '+11', 'color' => 'EF', 'quality' => 'VVS', 'pcs' => 30, 'cts' => 3.750],
            ['diamond_type' => 'Princess', 'size' => '+9', 'color' => 'GH', 'quality' => 'VS', 'pcs' => 18, 'cts' => 2.160],
        ];

        foreach ($rows as $r) {
            $itemTable->insert([
                'bag_id'               => $bagId,
                'diamond_type'         => $r['diamond_type'],
                'size'                 => $r['size'],
                'color'                => $r['color'],
                'quality'              => $r['quality'],
                'pcs_total'            => $r['pcs'],
                'weight_cts_total'     => $r['cts'],
                'pcs_available'        => $r['pcs'],
                'weight_cts_available' => $r['cts'],
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }
}

