<?php

namespace App\Models;

use CodeIgniter\Model;

class WarehouseModel extends Model
{
    protected $table = 'warehouses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'warehouse_code',
        'name',
        'warehouse_type',
        'address',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
