<?php

namespace App\Models;

use CodeIgniter\Model;

class IssueLineModel extends Model
{
    protected $table      = 'issue_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'issue_id',
        'item_id',
        'pcs',
        'carat',
        'rate_per_carat',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
