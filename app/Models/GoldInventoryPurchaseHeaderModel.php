<?php

namespace App\Models;

use CodeIgniter\Model;

class GoldInventoryPurchaseHeaderModel extends Model
{
    protected $table      = 'gold_inventory_purchase_headers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_date',
        'supplier_name',
        'invoice_no',
        'location_id',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

