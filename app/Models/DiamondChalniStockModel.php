<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondChalniStockModel extends Model
{
    protected $table = 'diamond_chalni_stocks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'item_id', 'shape_id', 'size_id', 'category_label',
        'pcs_balance', 'carat_balance', 'source_type', 'source_reference',
        'notes', 'is_active', 'created_by', 'updated_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
