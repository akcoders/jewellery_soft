<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['code', 'name', 'module_group', 'action_key', 'description', 'sort_order', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
