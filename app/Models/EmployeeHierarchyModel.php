<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeHierarchyModel extends Model
{
    protected $table         = 'employee_hierarchies';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'employee_id',
        'reporting_manager_id',
        'observing_manager_id',
        'reviewing_manager_id',
        'approving_manager_id',
        'department_head_id',
        'effective_from',
        'effective_to',
        'is_active',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
