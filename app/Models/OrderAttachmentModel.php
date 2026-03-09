<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderAttachmentModel extends Model
{
    protected $table         = 'order_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'order_id',
        'order_item_id',
        'file_type',
        'file_name',
        'file_path',
        'uploaded_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}

