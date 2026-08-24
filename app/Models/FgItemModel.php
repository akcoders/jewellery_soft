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
        'production_ready_item_id',
        'product_id',
        'variant_id',
        'design_name',
        'purity_label',
        'qty',
        'gross_wt',
        'net_gold_wt',
        'diamond_cts',
        'stone_wt',
        'studded_details_json',
        'source_image_path',
        'status',
        'warehouse_id',
        'bin_id',
        'showroom_id',
        'showroom_counter_id',
        'showroom_stock_status',
        'inventory_remarks',
        'terminal_at',
        'reserved_order_id',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
