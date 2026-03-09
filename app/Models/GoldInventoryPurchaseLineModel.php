<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryPurchaseLineModel extends Model
{
    protected $table      = 'gold_inventory_purchase_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_id',
        'item_id',
        'weight_gm',
        'fine_weight_gm',
        'rate_per_gm',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

