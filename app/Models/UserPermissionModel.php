<?php

namespace App\Models;

use CodeIgniter\Model;

class UserPermissionModel extends Model
{
    protected $table = 'user_permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'permission_id', 'access_type', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
