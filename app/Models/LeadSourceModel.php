<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadSourceModel extends Model
{
    protected $table         = 'lead_sources';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

