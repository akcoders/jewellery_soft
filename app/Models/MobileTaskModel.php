<?php

namespace App\Models;

use CodeIgniter\Model;

class MobileTaskModel extends Model
{
    protected $table = 'mobile_tasks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $allowedFields = [
        'admin_user_id',
        'title',
        'note',
        'priority',
        'scheduled_at',
        'status',
        'is_done',
        'completed_at',
        'completed_by',
        'proof_name',
        'proof_path',
        'proof_note',
        'counts_for_performance',
        'score_delta',
        'created_by',
    ];
}
