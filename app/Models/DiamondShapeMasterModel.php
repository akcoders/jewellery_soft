<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondShapeMasterModel extends Model
{
    protected $table = 'diamond_shape_masters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['code', 'name', 'sort_order', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
