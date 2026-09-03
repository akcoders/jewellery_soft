<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderCategoryModel extends Model
{
    protected $table = 'order_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'code', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
