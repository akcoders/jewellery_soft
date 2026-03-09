<?php

namespace App\Models;

use CodeIgniter\Model;

class ReturnLineModel extends Model
{
    protected $table      = 'return_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'return_id',
        'item_id',
        'pcs',
        'carat',
        'rate_per_carat',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
