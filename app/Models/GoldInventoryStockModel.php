<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryStockModel extends Model
{
    protected $table      = 'gold_inventory_stock';
    protected $primaryKey = 'item_id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'item_id',
        'weight_balance_gm',
        'fine_balance_gm',
        'avg_cost_per_gm',
        'stock_value',
        'updated_at',
    ];

    protected $useTimestamps = false;
}

