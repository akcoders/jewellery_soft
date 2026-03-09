<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoDesignInventorySeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->seedDesigns($now);
        $locationIds = $this->seedLocations($now);
        $this->seedInventoryItems($now, $locationIds);
    }

    /**
     * @return array<string, int>
     */
    private function seedLocations(string $now): array
    {
        $rows = [
            ['name' => 'Main Vault', 'location_type' => 'Vault'],
            ['name' => 'Manufacturing WIP', 'location_type' => 'WIP'],
            ['name' => 'Showroom Counter', 'location_type' => 'Showroom'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $table = $this->db->table('inventory_locations');
            $found = $table->where('name', $row['name'])->get()->getRowArray();
            if ($found) {
                $ids[$row['name']] = (int) $found['id'];
                continue;
            }

            $table->insert([
                'name'         => $row['name'],
                'location_type'=> $row['location_type'],
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $ids[$row['name']] = (int) $this->db->insertID();
        }

        return $ids;
    }

    private function seedDesigns(string $now): void
    {
        $rows = [
            ['design_code' => 'RG-1001', 'name' => 'Solitaire Ring Classic', 'category' => 'Ring'],
            ['design_code' => 'BG-2001', 'name' => 'Temple Bangle Pair', 'category' => 'Bangle'],
            ['design_code' => 'NK-3001', 'name' => 'Diamond Necklace Floral', 'category' => 'Necklace'],
            ['design_code' => 'ER-4001', 'name' => 'Stud Earring Daily Wear', 'category' => 'Earring'],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->table('design_masters')->where('design_code', $row['design_code'])->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $this->db->table('design_masters')->insert([
                'design_code' => $row['design_code'],
                'name'        => $row['name'],
                'category'    => $row['category'],
                'image_path'  => null,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    /**
     * @param array<string, int> $locationIds
     */
    private function seedInventoryItems(string $now, array $locationIds): void
    {
        $purity22 = $this->getPurityId('22K');
        $purity18 = $this->getPurityId('18K');

        $rows = [
            [
                'item_type'       => 'Gold',
                'material_name'   => 'Gold Bar 22K',
                'gold_purity_id'  => $purity22,
                'diamond_shape'   => null,
                'diamond_sieve'   => null,
                'diamond_color'   => null,
                'diamond_clarity' => null,
                'pcs'             => 0,
                'weight_gm'       => 250.000,
                'cts'             => 0.000,
                'location_id'     => $locationIds['Main Vault'] ?? null,
                'reference_code'  => 'INV-GOLD-22K-001',
            ],
            [
                'item_type'       => 'Gold',
                'material_name'   => 'Gold Alloy 18K Rose',
                'gold_purity_id'  => $purity18,
                'diamond_shape'   => null,
                'diamond_sieve'   => null,
                'diamond_color'   => null,
                'diamond_clarity' => null,
                'pcs'             => 0,
                'weight_gm'       => 110.500,
                'cts'             => 0.000,
                'location_id'     => $locationIds['Manufacturing WIP'] ?? null,
                'reference_code'  => 'INV-GOLD-18K-001',
            ],
            [
                'item_type'       => 'Diamond',
                'material_name'   => 'Natural Diamond Packet',
                'gold_purity_id'  => null,
                'diamond_shape'   => 'Round',
                'diamond_sieve'   => '+11',
                'diamond_color'   => 'EF',
                'diamond_clarity' => 'VVS',
                'pcs'             => 120,
                'weight_gm'       => 0.000,
                'cts'             => 18.750,
                'location_id'     => $locationIds['Main Vault'] ?? null,
                'reference_code'  => 'INV-DIA-RD11-001',
            ],
            [
                'item_type'       => 'Finished Goods',
                'material_name'   => 'Ring SKU FG-RG-5001',
                'gold_purity_id'  => $purity18,
                'diamond_shape'   => null,
                'diamond_sieve'   => null,
                'diamond_color'   => null,
                'diamond_clarity' => null,
                'pcs'             => 2,
                'weight_gm'       => 13.200,
                'cts'             => 1.420,
                'location_id'     => $locationIds['Showroom Counter'] ?? null,
                'reference_code'  => 'INV-FG-RING-5001',
            ],
            [
                'item_type'       => 'Stone',
                'material_name'   => 'Ruby',
                'gold_purity_id'  => null,
                'diamond_shape'   => 'Oval',
                'diamond_sieve'   => '2.0 mm',
                'diamond_color'   => 'Red',
                'diamond_clarity' => 'AA',
                'pcs'             => 300,
                'weight_gm'       => 0.000,
                'cts'             => 22.500,
                'location_id'     => $locationIds['Main Vault'] ?? null,
                'reference_code'  => 'INV-STN-RUBY-001',
            ],
            [
                'item_type'       => 'Stone',
                'material_name'   => 'Emerald',
                'gold_purity_id'  => null,
                'diamond_shape'   => 'Round',
                'diamond_sieve'   => '1.8 mm',
                'diamond_color'   => 'Green',
                'diamond_clarity' => 'A',
                'pcs'             => 260,
                'weight_gm'       => 0.000,
                'cts'             => 19.300,
                'location_id'     => $locationIds['Main Vault'] ?? null,
                'reference_code'  => 'INV-STN-EMR-001',
            ],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->table('inventory_items')->where('reference_code', $row['reference_code'])->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $this->db->table('inventory_items')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function getPurityId(string $code): ?int
    {
        $row = $this->db->table('gold_purities')->select('id')->where('purity_code', $code)->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }
}
