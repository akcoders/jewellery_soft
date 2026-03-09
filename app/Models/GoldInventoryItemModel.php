<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryItemModel extends Model
{
    protected $table      = 'gold_inventory_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'gold_purity_id',
        'purity_code',
        'purity_percent',
        'color_name',
        'form_type',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

