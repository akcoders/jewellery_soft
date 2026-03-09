<?php

namespace App\Models;

use CodeIgniter\Model;

class DesignMasterModel extends Model
{
    protected $table         = 'design_masters';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'design_code',
        'name',
        'category',
        'image_path',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

