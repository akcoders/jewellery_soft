<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseLineModel extends Model
{
    protected $table      = 'purchase_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_id',
        'item_id',
        'pcs',
        'carat',
        'rate_per_carat',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
