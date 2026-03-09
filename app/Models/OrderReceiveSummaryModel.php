<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderReceiveSummaryModel extends Model
{
    protected $table      = 'order_receive_summaries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'movement_id',
        'order_id',
        'gross_weight_gm',
        'net_gold_weight_gm',
        'pure_gold_weight_gm',
        'diamond_weight_cts',
        'diamond_weight_gm',
        'stone_weight_cts',
        'stone_weight_gm',
        'other_weight_gm',
        'diamond_amount',
        'stone_amount',
        'other_amount',
        'gold_amount',
        'labour_rate_per_gm',
        'labour_amount',
        'total_valuation',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

