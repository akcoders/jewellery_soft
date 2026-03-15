<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomCounterModel extends Model
{
    protected $table         = 'showroom_counters';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'showroom_id',
        'counter_code',
        'counter_name',
        'counter_type',
        'incharge_employee_id',
        'is_active',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
