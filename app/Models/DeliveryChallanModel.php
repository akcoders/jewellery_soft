<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryChallanModel extends Model
{
    protected $table      = 'delivery_challans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'challan_no',
        'challan_date',
        'order_id',
        'packing_list_id',
        'receive_movement_id',
        'gross_weight_gm',
        'net_gold_weight_gm',
        'diamond_weight_cts',
        'color_stone_weight_cts',
        'other_weight_gm',
        'taxable_value',
        'tax_percent',
        'tax_amount',
        'total_amount',
        'summary_json',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

