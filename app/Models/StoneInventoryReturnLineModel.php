<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryReturnLineModel extends Model
{
    protected $table = 'stone_inventory_return_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'return_id',
        'item_id',
        'qty',
        'rate',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

