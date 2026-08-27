<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminUserTourPreferenceModel extends Model
{
    protected $table = 'admin_user_tour_preferences';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $allowedFields = [
        'admin_user_id',
        'tour_key',
        'tour_version',
        'state',
        'current_step_key',
        'dont_show_again',
        'started_at',
        'completed_at',
        'dismissed_at',
    ];
}
