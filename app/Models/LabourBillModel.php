<?php

namespace App\Models;

use CodeIgniter\Model;

class LabourBillModel extends Model
{
    protected $table      = 'labour_bills';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'bill_no',
        'bill_date',
        'order_id',
        'receive_movement_id',
        'karigar_id',
        'gst_master_id',
        'tax_breakup_json',
        'gold_weight_gm',
        'rate_per_gm',
        'labour_amount',
        'other_amount',
        'taxable_amount',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'gst_amount',
        'round_off_amount',
        'total_amount',
        'due_date',
        'payment_status',
        'attachment_path',
        'attachment_name',
        'source_type',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
