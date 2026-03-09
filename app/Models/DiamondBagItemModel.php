<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondBagItemModel extends Model
{
    protected $table         = 'diamond_bag_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'bag_id',
        'diamond_type',
        'size',
        'color',
        'quality',
        'pcs_total',
        'weight_cts_total',
        'pcs_available',
        'weight_cts_available',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

