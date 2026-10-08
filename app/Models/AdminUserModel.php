<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminUserModel extends Model
{
    protected $table         = 'admin_users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name',
        'email',
        'password_hash',
        'is_active',
        'followup_requires_approval',
        'issuement_requires_approval',
        'delivery_challan_requires_approval',
        'followup_gallery_enabled',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
