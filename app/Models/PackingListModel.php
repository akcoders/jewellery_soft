<?php

namespace App\Models;

use CodeIgniter\Model;

class PackingListModel extends Model
{
    protected $table = 'packing_lists';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'packing_no',
        'packing_date',
        'order_id',
        'customer_id',
        'warehouse_id',
        'status',
        'seal_no',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
