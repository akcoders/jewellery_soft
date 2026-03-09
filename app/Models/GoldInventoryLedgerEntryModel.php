<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryLedgerEntryModel extends Model
{
    protected $table      = 'gold_inventory_ledger_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'txn_date',
        'txn_type',
        'reference_table',
        'reference_id',
        'order_id',
        'karigar_id',
        'location_id',
        'item_id',
        'debit_weight_gm',
        'credit_weight_gm',
        'debit_fine_gm',
        'credit_fine_gm',
        'balance_weight_gm',
        'balance_fine_gm',
        'rate_per_gm',
        'line_value',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $useTimestamps = false;
}

