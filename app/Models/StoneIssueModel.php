<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneIssueModel extends Model
{
    protected $table         = 'stone_issues';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'karigar_id',
        'location_id',
        'stone_type',
        'size',
        'stone_item_type',
        'color',
        'quality',
        'issue_pcs',
        'issue_weight_cts',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
