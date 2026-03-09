<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryBinModel extends Model
{
    protected $table         = 'inventory_bins';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'location_id',
        'bin_code',
        'name',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
