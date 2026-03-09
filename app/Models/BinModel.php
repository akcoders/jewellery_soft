<?php

namespace App\Models;

use CodeIgniter\Model;

class BinModel extends Model
{
    protected $table = 'bins';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'warehouse_id',
        'bin_code',
        'name',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
