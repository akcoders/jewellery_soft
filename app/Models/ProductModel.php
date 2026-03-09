<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table         = 'products';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'category_id',
        'product_code',
        'product_name',
        'item_type',
        'unit_type',
        'description',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

