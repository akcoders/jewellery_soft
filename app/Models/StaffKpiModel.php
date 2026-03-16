<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffKpiModel extends Model
{
    protected $table = 'staff_kpis';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'kpi_code',
        'name',
        'module_group',
        'metric_key',
        'unit',
        'period_type',
        'description',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
