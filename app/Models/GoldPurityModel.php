<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldPurityModel extends Model
{
    protected $table         = 'gold_purities';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'purity_code',
        'purity_percent',
        'color_name',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

