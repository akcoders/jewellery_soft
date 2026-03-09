<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountModel extends Model
{
    protected $table = 'accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'account_code',
        'account_name',
        'account_type',
        'reference_table',
        'reference_id',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
