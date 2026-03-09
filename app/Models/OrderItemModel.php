<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table         = 'order_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'design_id',
        'variant_id',
        'gold_purity_id',
        'item_description',
        'size_label',
        'qty',
        'gold_required_gm',
        'diamond_required_cts',
        'item_status',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
