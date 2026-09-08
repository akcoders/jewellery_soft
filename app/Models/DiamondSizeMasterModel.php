<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondSizeMasterModel extends Model
{
    protected $table = 'diamond_size_masters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['shape_id', 'size_code', 'size_label', 'min_mm', 'max_mm', 'sort_order', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
