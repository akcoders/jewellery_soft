<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderReceiveDetailModel extends Model
{
    protected $table      = 'order_receive_details';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'movement_id',
        'order_id',
        'component_type',
        'component_name',
        'pcs',
        'weight_cts',
        'weight_gm',
        'rate',
        'line_total',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

