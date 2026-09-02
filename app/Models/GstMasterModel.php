<?php

namespace App\Models;

use CodeIgniter\Model;

class GstMasterModel extends Model
{
    protected $table = 'gst_masters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'total_percentage', 'is_active'];
    protected $useTimestamps = true;
}
