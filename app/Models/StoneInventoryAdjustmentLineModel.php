<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryAdjustmentLineModel extends Model
{
    protected $table = 'stone_inventory_adjustment_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'adjustment_id',
        'item_id',
        'qty',
        'rate',
        'line_value',
        'reason',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

