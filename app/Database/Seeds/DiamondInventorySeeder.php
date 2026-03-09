<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DiamondInventorySeeder extends Seeder
{
    public function run()
    {
        $items = [
            [
                'diamond_type' => 'Round',
                'shape' => 'Round',
                'chalni_from' => 3,
                'chalni_to' => 4,
                'color' => 'G',
                'clarity' => 'VS',
                'cut' => 'Excellent',
                'remarks' => 'Round chalni 3-4 G VS',
            ],
            [
                'diamond_type' => 'Pan',
                'shape' => 'Freeform',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'H',
                'clarity' => 'SI',
                'cut' => null,
                'remarks' => 'Pan H SI',
            ],
            [
                'diamond_type' => 'Baguette',
                'shape' => 'Rectangle',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'F',
                'clarity' => 'VS',
                'cut' => 'Step',
                'remarks' => 'Baguette F VS',
            ],
            [
                'diamond_type' => 'Polki',
                'shape' => 'Freeform',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'Mix',
                'clarity' => 'NA',
                'cut' => null,
                'remarks' => 'Polki Mix',
            ],
            [
                'diamond_type' => 'Rose Cut',
                'shape' => 'Round',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'I',
                'clarity' => 'SI',
                'cut' => 'Rose',
                'remarks' => 'Rose Cut I SI',
            ],
            [
                'diamond_type' => 'Broken',
                'shape' => 'Mixed',
                'chalni_from' => null,
                'chalni_to' => null,
                'color' => 'Mix',
                'clarity' => 'Mix',
                'cut' => null,
                'remarks' => 'Broken Mix',
            ],
        ];

        foreach ($items as $item) {
            $existing = $this->db->query(
                'SELECT id FROM items
                 WHERE diamond_type = ?
                   AND shape <=> ?
                   AND chalni_from <=> ?
                   AND chalni_to <=> ?
                   AND color <=> ?
                   AND clarity <=> ?
                   AND cut <=> ?
                 LIMIT 1',
                [
                    $item['diamond_type'],
                    $item['shape'],
                    $item['chalni_from'],
                    $item['chalni_to'],
                    $item['color'],
                    $item['clarity'],
                    $item['cut'],
                ]
            )->getRowArray();

            if ($existing) {
                $itemId = (int) $existing['id'];
            } else {
                $this->db->table('items')->insert($item + [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $itemId = (int) $this->db->insertID();
            }

            $this->db->query(
                'INSERT INTO stock (item_id, pcs_balance, carat_balance, avg_cost_per_carat, stock_value, updated_at)
                 VALUES (?, 0, 0, 0, 0, NOW())
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
                [$itemId]
            );
        }
    }
}
