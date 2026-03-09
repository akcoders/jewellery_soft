<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'invoice_no',
        'invoice_date',
        'customer_id',
        'order_id',
        'packing_list_id',
        'taxable_amount',
        'gst_amount',
        'total_amount',
        'status',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
