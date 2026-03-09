<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondIssueModel extends Model
{
    protected $table         = 'diamond_issues';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'bag_id',
        'bag_item_id',
        'issue_pcs',
        'issue_weight_cts',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

