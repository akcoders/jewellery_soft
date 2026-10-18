<?php

namespace App\Models;

use CodeIgniter\Model;

class ArattaiUserMappingModel extends Model
{
    protected $table = 'arattai_user_mappings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'entity_type', 'entity_id', 'arattai_user_id', 'is_approval_admin', 'is_active',
        'created_by', 'updated_by',
    ];
}
