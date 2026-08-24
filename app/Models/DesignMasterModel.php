<?php

namespace App\Models;

use CodeIgniter\Model;

class DesignMasterModel extends Model
{
    protected $table         = 'design_masters';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'design_code',
        'name',
        'category',
        'subcategory',
        'image_path',
        'source_order_id',
        'source_order_item_id',
        'source_karigar_id',
        'purity_label',
        'gross_weight_gm',
        'net_gold_weight_gm',
        'pure_gold_weight_gm',
        'diamond_weight_cts',
        'stone_weight_cts',
        'studded_details_json',
        'source_image_sha256',
        'source_type',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
