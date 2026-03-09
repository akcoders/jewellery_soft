<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorPaymentModel extends Model
{
    protected $table = 'vendor_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'payment_no',
        'payment_date',
        'vendor_id',
        'purchase_invoice_id',
        'amount',
        'payment_mode',
        'reference_no',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
