<?php

namespace App\Models;

use CodeIgniter\Model;

class ArattaiWebhookEventModel extends Model
{
    protected $table = 'arattai_webhook_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'event_id',
        'event_type',
        'arattai_user_id',
        'business_number_id',
        'processing_status',
        'result_message',
        'received_at',
        'processed_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
