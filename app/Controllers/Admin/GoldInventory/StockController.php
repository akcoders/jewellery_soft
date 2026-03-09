<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;

class StockController extends BaseController
{
    public function index(): string
    {
        $filters = [
            'purity_code' => trim((string) $this->request->getGet('purity_code')),
            'color_name' => trim((string) $this->request->getGet('color_name')),
            'form_type' => trim((string) $this->request->getGet('form_type')),
            'location' => trim((string) $this->request->getGet('location')),
        ];

        $builder = db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, gp.purity_code as master_purity_code, gis.weight_balance_gm, gis.fine_balance_gm, gis.avg_cost_per_gm, gis.stock_value')
            ->join('gold_inventory_stock gis', 'gis.item_id = gi.id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->orderBy('gi.purity_percent', 'DESC')
            ->orderBy('gi.id', 'DESC');

        if ($filters['purity_code'] !== '') {
            $builder->groupStart()
                ->where('gi.purity_code', $filters['purity_code'])
                ->orWhere('gp.purity_code', $filters['purity_code'])
                ->groupEnd();
        }
        if ($filters['color_name'] !== '') {
            $builder->where('gi.color_name', $filters['color_name']);
        }
        if ($filters['form_type'] !== '') {
            $builder->where('gi.form_type', $filters['form_type']);
        }

        return view('admin/gold_inventory/stock/index', [
            'title' => 'Gold Stock Summary',
            'rows' => $builder->get()->getResultArray(),
            'filters' => $filters,
            'filterOptions' => [
                'purity_codes' => $this->distinct('purity_code'),
                'color_names' => $this->distinct('color_name'),
                'form_types' => $this->distinct('form_type'),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinct(string $field): array
    {
        $rows = db_connect()->table('gold_inventory_items')
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

