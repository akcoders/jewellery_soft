<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneLedgerEntryModel extends Model
{
    protected $table         = 'stone_ledger_entries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'karigar_id',
        'location_id',
        'entry_type',
        'stone_type',
        'size',
        'stone_item_type',
        'color',
        'quality',
        'pcs',
        'weight_cts',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
