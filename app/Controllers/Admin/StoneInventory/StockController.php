<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;

class StockController extends BaseController
{
    public function index(): string
    {
        $filters = [
            'product_name' => trim((string) $this->request->getGet('product_name')),
            'stone_type' => trim((string) $this->request->getGet('stone_type')),
        ];

        $builder = db_connect()->table('stone_inventory_items i')
            ->select('i.*, s.qty_balance, s.avg_rate, s.stock_value')
            ->join('stone_inventory_stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.product_name', 'ASC')
            ->orderBy('i.stone_type', 'ASC');

        if ($filters['product_name'] !== '') {
            $builder->where('i.product_name', $filters['product_name']);
        }
        if ($filters['stone_type'] !== '') {
            $builder->where('i.stone_type', $filters['stone_type']);
        }

        return view('admin/stone_inventory/stock/index', [
            'title' => 'Stone Stock Summary',
            'rows' => $builder->get()->getResultArray(),
            'filters' => $filters,
            'filterOptions' => [
                'product_names' => $this->distinct('product_name'),
                'stone_types' => $this->distinct('stone_type'),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinct(string $field): array
    {
        $rows = db_connect()->table('stone_inventory_items')
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

