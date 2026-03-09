<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderStatusHistoryModel extends Model
{
    protected $table         = 'order_status_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'from_status',
        'to_status',
        'remarks',
        'changed_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

