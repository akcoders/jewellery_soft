<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table         = 'departments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'department_code',
        'name',
        'sort_order',
        'is_active',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
