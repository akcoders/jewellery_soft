<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffKpiAchievementModel extends Model
{
    protected $table = 'staff_kpi_achievements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'employee_id',
        'kpi_id',
        'target_id',
        'target_year',
        'target_month',
        'achieved_value',
        'achievement_percent',
        'incentive_amount',
        'source_key',
        'calculated_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
