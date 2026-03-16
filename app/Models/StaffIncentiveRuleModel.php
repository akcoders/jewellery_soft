<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffIncentiveRuleModel extends Model
{
    protected $table = 'staff_incentive_rules';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'rule_code',
        'rule_name',
        'designation_id',
        'kpi_id',
        'min_percent',
        'max_percent',
        'incentive_type',
        'incentive_value',
        'notes',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
