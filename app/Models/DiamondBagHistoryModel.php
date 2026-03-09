<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondBagHistoryModel extends Model
{
    protected $table = 'diamond_bag_history';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'bag_id',
        'action_type',
        'ref_voucher_id',
        'from_warehouse_id',
        'from_bin_id',
        'to_warehouse_id',
        'to_bin_id',
        'pcs',
        'cts',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
