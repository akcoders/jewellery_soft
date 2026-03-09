<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseHeaderModel extends Model
{
    protected $table      = 'purchase_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_date',
        'vendor_id',
        'supplier_name',
        'invoice_no',
        'due_date',
        'tax_percentage',
        'invoice_total',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
