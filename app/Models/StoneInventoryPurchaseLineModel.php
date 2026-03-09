<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryPurchaseLineModel extends Model
{
    protected $table = 'stone_inventory_purchase_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_id',
        'item_id',
        'qty',
        'rate',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

