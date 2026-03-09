<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondLedgerEntryModel extends Model
{
    protected $table         = 'diamond_ledger_entries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'bag_id',
        'bag_item_id',
        'karigar_id',
        'location_id',
        'entry_type',
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
