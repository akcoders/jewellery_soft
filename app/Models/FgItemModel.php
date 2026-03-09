<?php

namespace App\Models;

use CodeIgniter\Model;

class FgItemModel extends Model
{
    protected $table = 'fg_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tag_no',
        'order_id',
        'job_card_id',
        'product_id',
        'variant_id',
        'qty',
        'gross_wt',
        'net_gold_wt',
        'diamond_cts',
        'stone_wt',
        'status',
        'warehouse_id',
        'bin_id',
        'reserved_order_id',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
