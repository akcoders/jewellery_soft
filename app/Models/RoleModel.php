<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['role_code', 'name', 'description', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
