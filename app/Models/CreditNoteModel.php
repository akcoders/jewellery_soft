<?php

namespace App\Models;

use CodeIgniter\Model;

class CreditNoteModel extends Model
{
    protected $table = 'credit_notes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'note_no',
        'note_date',
        'party_type',
        'customer_id',
        'vendor_id',
        'order_id',
        'invoice_id',
        'reference_no',
        'reason',
        'taxable_amount',
        'gst_percent',
        'gst_amount',
        'total_amount',
        'status',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
