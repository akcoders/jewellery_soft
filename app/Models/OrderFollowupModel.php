<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderFollowupModel extends Model
{
    protected $table      = 'order_followups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'order_id',
        'stage',
        'description',
        'next_followup_date',
        'followup_taken_by',
        'followup_taken_on',
        'image_name',
        'image_path',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

