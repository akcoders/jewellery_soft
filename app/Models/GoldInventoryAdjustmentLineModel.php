<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryAdjustmentLineModel extends Model
{
    protected $table      = 'gold_inventory_adjustment_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'adjustment_id',
        'item_id',
        'weight_gm',
        'fine_weight_gm',
        'rate_per_gm',
        'line_value',
        'reason',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

