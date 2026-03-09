<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoKarigarSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            [
                'name'       => 'Ramesh Soni',
                'phone'      => '9876500011',
                'department' => 'Casting',
                'skills_text'=> 'Casting, filing',
                'rate_per_gm'=> 45.00,
                'wastage_percentage' => 3.50,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Mahesh Prajapati',
                'phone'      => '9876500022',
                'department' => 'Setting',
                'skills_text'=> 'Diamond setting, jadau',
                'rate_per_gm'=> 62.00,
                'wastage_percentage' => 4.25,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Suresh Parmar',
                'phone'      => '9876500033',
                'department' => 'Polish',
                'skills_text'=> 'Polish, final finish',
                'rate_per_gm'=> 38.00,
                'wastage_percentage' => 2.75,
                'is_active'  => 1,
            ],
        ];

        foreach ($rows as $row) {
            $table = $this->db->table('karigars');
            $exists = $table->where('name', $row['name'])->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $table->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
