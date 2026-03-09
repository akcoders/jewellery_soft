<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryItemModel extends Model
{
    protected $table         = 'inventory_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'item_type',
        'material_name',
        'gold_purity_id',
        'diamond_shape',
        'diamond_sieve',
        'diamond_color',
        'diamond_clarity',
        'pcs',
        'weight_gm',
        'cts',
        'location_id',
        'warehouse_id',
        'bin_id',
        'packet_no',
        'lot_no',
        'certificate_no',
        'reference_code',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
