<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerAddressModel extends Model
{
    protected $table         = 'customer_addresses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'customer_id',
        'address_type',
        'line1',
        'line2',
        'city',
        'state',
        'country',
        'pincode',
        'is_default',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

