<?php

namespace App\Models;

use CodeIgniter\Model;

class MobileApiTokenModel extends Model
{
    protected $table = 'mobile_api_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'admin_user_id',
        'token_hash',
        'device_name',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

