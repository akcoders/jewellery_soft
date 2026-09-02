<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderFollowupScheduleModel extends Model
{
    protected $table = 'order_followup_schedules';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $allowedFields = [
        'order_id', 'assigned_to', 'due_at', 'status',
        'completed_followup_id', 'completed_by', 'completed_at',
        'score_delta', 'created_by',
    ];
}
