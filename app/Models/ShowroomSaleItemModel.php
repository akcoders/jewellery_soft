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
        'invoice_item_id',
        'description',
        'qty',
        'rate',
        'amount',
        'gross_wt',
        'net_gold_wt',
        'diamond_cts',
        'stone_wt',
        'gst_percent',
        'gst_amount',
    ];
    protected $useTimestamps = false;
}
