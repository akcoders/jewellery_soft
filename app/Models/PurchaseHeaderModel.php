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
        'taxable_amount',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'gst_amount',
        'round_off_amount',
        'tax_percentage',
        'invoice_total',
        'payment_status',
        'paid_amount',
        'payment_date',
        'stock_posted',
        'source_sheet',
        'source_row',
        'verification_status',
        'account_voucher_id',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
