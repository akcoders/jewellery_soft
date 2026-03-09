<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryStockModel extends Model
{
    protected $table = 'stone_inventory_stock';
    protected $primaryKey = 'item_id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'item_id',
        'qty_balance',
        'avg_rate',
        'stock_value',
        'updated_at',
    ];

    protected $useTimestamps = false;
}

