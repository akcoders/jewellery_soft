<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerUserModel extends Model
{
    protected $table = 'customer_users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'customer_id', 'name', 'mobile', 'email', 'password_hash', 'role',
        'is_active', 'last_login_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
