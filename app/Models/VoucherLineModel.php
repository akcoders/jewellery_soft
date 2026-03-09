<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherLineModel extends Model
{
    protected $table = 'voucher_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_id',
        'line_no',
        'item_type',
        'item_key',
        'material_name',
        'bag_id',
        'tag_no',
        'gold_purity_id',
        'shape',
        'chalni_size',
        'color',
        'clarity',
        'stone_type',
        'qty_pcs',
        'qty_cts',
        'qty_weight',
        'fine_gold',
        'rate',
        'amount',
        'remarks',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
