<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductCategoryModel extends Model
{
    protected $table         = 'product_categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name',
        'description',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

