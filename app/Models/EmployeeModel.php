<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table         = 'employees';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'employee_code',
        'admin_user_id',
        'department_id',
        'designation_id',
        'full_name',
        'mobile',
        'email',
        'work_location',
        'joining_date',
        'pan_no',
        'aadhaar_no',
        'bank_name',
        'bank_account_no',
        'ifsc_code',
        'photo_path',
        'notes',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
