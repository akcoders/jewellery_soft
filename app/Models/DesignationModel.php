<?php

namespace App\Models;

use CodeIgniter\Model;

class DesignationModel extends Model
{
    protected $table         = 'designations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'department_id',
        'designation_code',
        'name',
        'level_no',
        'reports_to_designation_id',
        'can_manage_team',
        'is_active',
        'description',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
