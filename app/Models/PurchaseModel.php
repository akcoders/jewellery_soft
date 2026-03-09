<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseModel extends Model
{
    protected $table         = 'purchases';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'purchase_no',
        'purchase_type',
        'vendor_id',
        'purchase_date',
        'invoice_no',
        'invoice_amount',
        'payment_due_date',
        'payment_status',
        'location_id',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
