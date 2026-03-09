<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseBillPaymentModel extends Model
{
    protected $table      = 'purchase_bill_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'source_type',
        'source_id',
        'payment_date',
        'amount',
        'reference_no',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

