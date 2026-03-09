<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadNoteModel extends Model
{
    protected $table         = 'lead_notes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['lead_id', 'note', 'created_by'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

