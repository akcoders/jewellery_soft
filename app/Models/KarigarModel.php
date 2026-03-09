<?php

namespace App\Models;

use CodeIgniter\Model;

class KarigarModel extends Model
{
    protected $table         = 'karigars';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'aadhaar_no',
        'pan_no',
        'joining_date',
        'bank_name',
        'bank_account_no',
        'ifsc_code',
        'department',
        'skills_text',
        'rate_per_gm',
        'wastage_percentage',
        'notes',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
