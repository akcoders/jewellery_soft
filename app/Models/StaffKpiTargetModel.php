<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffKpiTargetModel extends Model
{
    protected $table = 'staff_kpi_targets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'employee_id',
        'kpi_id',
        'target_year',
        'target_month',
        'period_label',
        'target_value',
        'weightage',
        'assigned_by',
        'notes',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
