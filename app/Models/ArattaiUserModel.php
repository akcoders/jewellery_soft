<?php

namespace App\Models;

use CodeIgniter\Model;

class ArattaiUserModel extends Model
{
    protected $table = 'arattai_users';
    protected $primaryKey = 'arattai_user_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['arattai_user_id', 'name', 'phone', 'last_message_type', 'last_seen_at'];
}
