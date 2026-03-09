<?php

namespace App\Models;

use CodeIgniter\Model;

class KarigarPaymentLedgerModel extends Model
{
    protected $table         = 'karigar_payment_ledgers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'karigar_id',
        'order_id',
        'entry_type',
        'amount',
        'reference_no',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
