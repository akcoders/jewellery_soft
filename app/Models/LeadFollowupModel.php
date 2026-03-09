<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadFollowupModel extends Model
{
    protected $table         = 'lead_followups';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'lead_id',
        'followup_at',
        'reminder_at',
        'status',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

