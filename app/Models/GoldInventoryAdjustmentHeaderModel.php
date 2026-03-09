<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryAdjustmentHeaderModel extends Model
{
    protected $table      = 'gold_inventory_adjustment_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'adjustment_date',
        'adjustment_type',
        'location_id',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

