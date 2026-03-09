<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;

class StockController extends BaseController
{
    public function index(): string
    {
        $filters = [
            'diamond_type' => trim((string) $this->request->getGet('diamond_type')),
            'shape' => trim((string) $this->request->getGet('shape')),
            'color' => trim((string) $this->request->getGet('color')),
            'clarity' => trim((string) $this->request->getGet('clarity')),
            'chalni_from' => trim((string) $this->request->getGet('chalni_from')),
            'chalni_to' => trim((string) $this->request->getGet('chalni_to')),
            'location' => trim((string) $this->request->getGet('location')),
        ];

        $db = db_connect();
        $builder = $db->table('items i')
            ->select('i.*, s.pcs_balance, s.carat_balance, s.avg_cost_per_carat, s.stock_value')
            ->join('stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.diamond_type', 'ASC')
            ->orderBy('i.shape', 'ASC')
            ->orderBy('CAST(i.chalni_from AS UNSIGNED)', 'ASC', false);

        if ($filters['diamond_type'] !== '') {
            $builder->where('i.diamond_type', $filters['diamond_type']);
        }
        if ($filters['shape'] !== '') {
            $builder->where('i.shape', $filters['shape']);
        }
        if ($filters['color'] !== '') {
            $builder->where('i.color', $filters['color']);
        }
        if ($filters['clarity'] !== '') {
            $builder->where('i.clarity', $filters['clarity']);
        }
        if ($filters['chalni_from'] !== '') {
            if (ctype_digit($filters['chalni_from'])) {
                $from = (int) ltrim($filters['chalni_from'], '0');
                $builder->where('(i.chalni_to IS NULL OR CAST(i.chalni_to AS UNSIGNED) >= ' . $db->escape($from) . ')', null, false);
            }
        }
        if ($filters['chalni_to'] !== '') {
            if (ctype_digit($filters['chalni_to'])) {
                $to = (int) ltrim($filters['chalni_to'], '0');
                $builder->where('(i.chalni_from IS NULL OR CAST(i.chalni_from AS UNSIGNED) <= ' . $db->escape($to) . ')', null, false);
            }
        }

        $rows = $builder->get()->getResultArray();

        return view('admin/diamond_inventory/stock/index', [
            'title' => 'Diamond Stock Summary',
            'rows' => $rows,
            'filters' => $filters,
            'filterOptions' => [
                'diamond_types' => $this->distinct('diamond_type'),
                'shapes' => $this->distinct('shape'),
                'colors' => $this->distinct('color'),
                'clarities' => $this->distinct('clarity'),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinct(string $field): array
    {
        $rows = db_connect()->table('items')
            ->select($field)
            ->distinct()
            ->where($field . ' IS NOT NULL', null, false)
            ->where($field . ' <>', '')
            ->orderBy($field, 'ASC')
            ->get()
            ->getResultArray();

        $values = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
