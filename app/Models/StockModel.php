<?php

namespace App\Models;

use CodeIgniter\Model;

class StockModel extends Model
{
    protected $table      = 'stock';
    protected $primaryKey = 'item_id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'item_id',
        'pcs_balance',
        'carat_balance',
        'avg_cost_per_carat',
        'stock_value',
        'updated_at',
    ];

    protected $useTimestamps = false;
}
