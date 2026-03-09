<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadImageModel extends Model
{
    protected $table         = 'lead_images';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['lead_id', 'file_name', 'file_path'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

