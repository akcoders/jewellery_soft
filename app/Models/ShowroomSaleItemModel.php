<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomSaleItemModel extends Model
{
    protected $table = 'showroom_sale_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'showroom_sale_id',
        'fg_item_id',
        'order_id',
        'invoice_item_id',
        'description',
        'image_path',
        'qty',
        'rate',
        'amount',
        'gross_wt',
        'net_gold_wt',
        'diamond_cts',
        'stone_wt',
        'gold_rate',
        'gold_amount',
        'diamond_rate',
        'diamond_amount',
        'stone_rate',
        'stone_amount',
        'other_amount',
        'gst_percent',
        'gst_amount',
    ];
    protected $useTimestamps = false;
}
