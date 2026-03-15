<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomStaffAssignmentModel extends Model
{
    protected $table         = 'showroom_staff_assignments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'showroom_id',
        'employee_id',
        'role_label',
        'is_primary',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
