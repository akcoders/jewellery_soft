<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryItemModel extends Model
{
    protected $table = 'stone_inventory_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'product_name',
        'stone_type',
        'default_rate',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

