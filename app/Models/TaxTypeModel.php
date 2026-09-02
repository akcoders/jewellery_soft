<?php

namespace App\Models;

use CodeIgniter\Model;

class TaxTypeModel extends Model
{
    protected $table = 'tax_types';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'is_active'];
    protected $useTimestamps = true;
}
