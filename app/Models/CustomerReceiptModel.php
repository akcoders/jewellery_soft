<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerReceiptModel extends Model
{
    protected $table = 'customer_receipts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'receipt_no',
        'receipt_date',
        'customer_id',
        'invoice_id',
        'amount',
        'payment_mode',
        'reference_no',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
