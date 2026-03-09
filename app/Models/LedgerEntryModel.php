<?php

namespace App\Models;

use CodeIgniter\Model;

class LedgerEntryModel extends Model
{
    protected $table = 'ledger_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_id',
        'line_no',
        'debit_account_id',
        'credit_account_id',
        'item_type',
        'item_key',
        'qty_pcs',
        'qty_cts',
        'qty_weight',
        'fine_gold_qty',
        'order_id',
        'job_card_id',
    ];
    protected $useTimestamps = false;
}
