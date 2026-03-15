<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomModel extends Model
{
    protected $table         = 'showrooms';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'showroom_code',
        'name',
        'showroom_type',
        'manager_employee_id',
        'phone',
        'email',
        'gstin',
        'state_name',
        'city_name',
        'address_line',
        'opening_date',
        'is_active',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
