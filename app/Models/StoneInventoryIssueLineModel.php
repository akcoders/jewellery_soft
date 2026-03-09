<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryIssueLineModel extends Model
{
    protected $table = 'stone_inventory_issue_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'issue_id',
        'item_id',
        'pcs',
        'qty',
        'rate',
        'line_value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
