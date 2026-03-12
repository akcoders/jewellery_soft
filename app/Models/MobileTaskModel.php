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
        'scheduled_at',
        'status',
        'is_done',
        'created_by',
    ];
}
