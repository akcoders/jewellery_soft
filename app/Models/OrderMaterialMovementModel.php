<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderMaterialMovementModel extends Model
{
    protected $table         = 'order_material_movements';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'movement_type',
        'gold_gm',
        'diamond_cts',
        'gold_purity_id',
        'karigar_id',
        'location_id',
        'gross_weight_gm',
        'other_weight_gm',
        'diamond_weight_gm',
        'net_gold_weight_gm',
        'pure_gold_weight_gm',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
