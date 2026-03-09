<?php

namespace App\Models;

use CodeIgniter\Model;

class QcCheckModel extends Model
{
    protected $table = 'qc_checks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'fg_item_id',
        'tag_no',
        'qc_status',
        'reason_code',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
}
