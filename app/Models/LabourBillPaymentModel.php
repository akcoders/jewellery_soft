<?php

namespace App\Models;

use CodeIgniter\Model;

class LabourBillPaymentModel extends Model
{
    protected $table      = 'labour_bill_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'labour_bill_id',
        'payment_date',
        'amount',
        'reference_no',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

