<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyModel extends Model
{
    protected $table = 'parties';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'party_type',
        'party_code',
        'name',
        'phone',
        'email',
        'gstin',
        'address',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
