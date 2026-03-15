<?php

namespace App\Models;

use CodeIgniter\Model;

class RolePermissionModel extends Model
{
    protected $table = 'role_permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['role_id', 'permission_id', 'created_at'];
    protected $useTimestamps = false;
}
