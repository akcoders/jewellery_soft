<?php

namespace App\Models;

use CodeIgniter\Model;

class StoneInventoryReturnHeaderModel extends Model
{
    protected $table = 'stone_inventory_return_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_no',
        'return_date',
        'order_id',
        'issue_id',
        'karigar_id',
        'location_id',
        'return_from',
        'purpose',
        'notes',
        'attachment_name',
        'attachment_path',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}

