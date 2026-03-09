<?php

namespace App\Models;

use CodeIgniter\Model;

class PackingListItemModel extends Model
{
    protected $table = 'packing_list_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'packing_list_id',
        'fg_item_id',
        'tag_no',
        'qty',
        'gross_wt',
        'net_gold_wt',
        'diamond_cts',
        'stone_wt',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
