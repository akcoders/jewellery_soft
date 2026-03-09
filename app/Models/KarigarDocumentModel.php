<?php

namespace App\Models;

use CodeIgniter\Model;

class KarigarDocumentModel extends Model
{
    protected $table         = 'karigar_documents';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'karigar_id',
        'document_type',
        'file_name',
        'file_path',
        'remarks',
        'uploaded_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

