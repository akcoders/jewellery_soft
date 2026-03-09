<?php

namespace App\Models;

use CodeIgniter\Model;

class DiamondPurchaseAttachmentModel extends Model
{
    protected $table      = 'diamond_purchase_attachments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'purchase_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

