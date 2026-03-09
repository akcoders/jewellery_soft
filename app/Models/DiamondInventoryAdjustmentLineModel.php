<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondInventoryAdjustmentLineModel extends Model
{
    protected $table      = 'diamond_inventory_adjustment_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'adjustment_id',
        'item_id',
        'pcs',
        'carat',
        'rate_per_carat',
        'line_value',
        'reason',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

