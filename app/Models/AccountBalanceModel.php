<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountBalanceModel extends Model
{
    protected $table = 'account_balances';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'account_id',
        'item_type',
        'item_key',
        'qty_pcs',
        'qty_cts',
        'qty_weight',
        'fine_gold_qty',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
