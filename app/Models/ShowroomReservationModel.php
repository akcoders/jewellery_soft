<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomReservationModel extends Model
{
    protected $table         = 'showroom_reservations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'fg_item_id',
        'showroom_id',
        'customer_id',
        'order_id',
        'reserved_for_name',
        'reserved_for_phone',
        'reservation_status',
        'reserved_on',
        'expires_on',
        'released_on',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
