<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondBagModel extends Model
{
    protected $table         = 'diamond_bags';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'bag_no',
        'prepared_date',
        'order_id',
        'warehouse_id',
        'bin_id',
        'shape',
        'chalni_size',
        'chalni_min',
        'chalni_max',
        'color',
        'clarity',
        'diamond_cut',
        'fluorescence',
        'pcs_balance',
        'cts_balance',
        'notes',
        'audit_image_name',
        'audit_image_path',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
