<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadModel extends Model
{
    protected $table         = 'leads';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'lead_no',
        'name',
        'phone',
        'email',
        'source_id',
        'city',
        'requirement_text',
        'stage',
        'status',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $dateFormat    = 'datetime';
}

