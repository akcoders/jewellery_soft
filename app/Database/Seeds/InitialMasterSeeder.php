<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialMasterSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $leadSources = ['Walk-in', 'Instagram', 'WhatsApp', 'Referral', 'Website', 'Exhibition'];
        foreach ($leadSources as $name) {
            $exists = $this->db->table('lead_sources')->where('name', $name)->countAllResults();
            if ($exists === 0) {
                $this->db->table('lead_sources')->insert([
                    'name'       => $name,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $goldPurities = [
            ['purity_code' => '24K', 'purity_percent' => 99.900, 'color_name' => 'Yellow'],
            ['purity_code' => '22K', 'purity_percent' => 91.600, 'color_name' => 'Yellow'],
            ['purity_code' => '18K', 'purity_percent' => 75.000, 'color_name' => 'Yellow'],
            ['purity_code' => '14K', 'purity_percent' => 58.500, 'color_name' => 'Rose'],
        ];

        foreach ($goldPurities as $row) {
            $exists = $this->db->table('gold_purities')->where('purity_code', $row['purity_code'])->countAllResults();
            if ($exists === 0) {
                $this->db->table('gold_purities')->insert([
                    'purity_code'    => $row['purity_code'],
                    'purity_percent' => $row['purity_percent'],
                    'color_name'     => $row['color_name'],
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }
}

