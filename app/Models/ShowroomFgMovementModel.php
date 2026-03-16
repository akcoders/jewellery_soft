<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomFgMovementModel extends Model
{
    protected $table         = 'showroom_fg_movements';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'fg_item_id',
        'movement_type',
        'from_showroom_id',
        'to_showroom_id',
        'from_counter_id',
        'to_counter_id',
        'reference_type',
        'reference_id',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
