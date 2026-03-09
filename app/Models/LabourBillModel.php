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
        'gold_weight_gm',
        'rate_per_gm',
        'labour_amount',
        'other_amount',
        'total_amount',
        'due_date',
        'payment_status',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

