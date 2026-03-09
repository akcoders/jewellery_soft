<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table         = 'orders';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_no',
        'order_type',
        'customer_id',
        'lead_id',
        'quotation_id',
        'assigned_karigar_id',
        'assigned_at',
        'status',
        'priority',
        'due_date',
        'order_notes',
        'repair_ornament_details',
        'repair_work_details',
        'repair_receive_weight_gm',
        'repair_received_at',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $dateFormat    = 'datetime';
}
