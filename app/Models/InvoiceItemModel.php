<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table = 'invoice_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'invoice_id',
        'fg_item_id',
        'description',
        'qty',
        'rate',
        'amount',
        'gst_percent',
        'gst_amount',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
