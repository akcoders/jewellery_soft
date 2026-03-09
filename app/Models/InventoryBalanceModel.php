<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryBalanceModel extends Model
{
    protected $table         = 'inventory_balances';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'balance_key',
        'item_type',
        'material_name',
        'gold_purity_id',
        'diamond_shape',
        'diamond_sieve',
        'diamond_sieve_min',
        'diamond_sieve_max',
        'diamond_color',
        'diamond_clarity',
        'diamond_cut',
        'diamond_quality',
        'diamond_fluorescence',
        'diamond_lab',
        'certificate_no',
        'packet_no',
        'lot_no',
        'stone_type',
        'stone_size',
        'stone_color_shade',
        'stone_quality_grade',
        'warehouse_id',
        'bin_id',
        'item_key',
        'qty_pcs',
        'qty_cts',
        'qty_weight',
        'fine_gold_qty',
        'pcs_balance',
        'weight_gm_balance',
        'cts_balance',
        'fine_gold_balance',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
