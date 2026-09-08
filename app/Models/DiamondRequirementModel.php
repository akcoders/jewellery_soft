<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondRequirementModel extends Model
{
    protected $table = 'diamond_requirements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $allowedFields = [
        'requirement_no',
        'order_id',
        'requested_by',
        'requirement_note',
        'required_by',
        'status',
        'approved_by',
        'approved_at',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'preparation_due_at',
        'approval_note',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'bag_id',
        'ready_by',
        'ready_at',
    ];
}
