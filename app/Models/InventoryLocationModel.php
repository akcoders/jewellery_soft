<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryLocationModel extends Model
{
    protected $table         = 'inventory_locations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'code',
        'name',
        'location_type',
        'address',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
