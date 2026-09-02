<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryPurchaseHeaderModel extends Model
{
    protected $table = 'stone_inventory_purchase_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_date',
        'vendor_id',
        'supplier_name',
        'supplier_address',
        'supplier_gstin',
        'supplier_phone',
        'supplier_email',
        'invoice_no',
        'due_date',
        'gst_master_id',
        'tax_breakup_json',
        'tax_percentage',
        'taxable_amount',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'gst_amount',
        'round_off_amount',
        'invoice_total',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
