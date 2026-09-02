<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryPurchaseHeaderModel extends Model
{
    protected $table      = 'gold_inventory_purchase_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_date',
        'vendor_id',
        'production_document_id',
        'supplier_name',
        'supplier_address',
        'supplier_gstin',
        'supplier_phone',
        'supplier_email',
        'invoice_no',
        'due_date',
        'place_of_supply',
        'purchase_description',
        'gst_master_id',
        'tax_breakup_json',
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
        'payment_status',
        'paid_amount',
        'payment_date',
        'stock_posted',
        'location_id',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
