<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseItemModel extends Model
{
    protected $table         = 'purchase_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'purchase_id',
        'item_type',
        'material_name',
        'gold_purity_id',
        'diamond_shape',
        'diamond_sieve',
        'diamond_color',
        'diamond_clarity',
        'pcs',
        'weight_gm',
        'cts',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

