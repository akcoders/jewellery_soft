<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table = 'vouchers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_no',
        'voucher_type',
        'voucher_date',
        'voucher_datetime',
        'from_warehouse_id',
        'from_bin_id',
        'to_warehouse_id',
        'to_bin_id',
        'order_id',
        'job_card_id',
        'party_id',
        'debit_account_id',
        'credit_account_id',
        'status',
        'is_reversal',
        'reversal_of_id',
        'remarks',
        'created_by',
        'created_ip',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
