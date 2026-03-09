<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryIssueHeaderModel extends Model
{
    protected $table      = 'gold_inventory_issue_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_no',
        'issue_date',
        'order_id',
        'karigar_id',
        'location_id',
        'issue_to',
        'purpose',
        'notes',
        'attachment_name',
        'attachment_path',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
