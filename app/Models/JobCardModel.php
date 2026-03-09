<?php

namespace App\Models;

use CodeIgniter\Model;

class JobCardModel extends Model
{
    protected $table         = 'job_cards';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'job_card_no',
        'order_id',
        'order_item_id',
        'status',
        'priority',
        'due_date',
        'qc_status',
        'rework_count',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

