<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldLedgerEntryModel extends Model
{
    protected $table         = 'gold_ledger_entries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'entry_type',
        'weight_gm',
        'gold_purity_id',
        'karigar_id',
        'location_id',
        'gross_weight_gm',
        'other_weight_gm',
        'diamond_weight_gm',
        'net_gold_weight_gm',
        'pure_gold_weight_gm',
        'purity_percent',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
