<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsappSenderQueueModel extends Model
{
    protected $table = 'whatsapp_sender_queue';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'message_no',
        'event_key',
        'source_type',
        'source_id',
        'order_id',
        'customer_id',
        'sender_number',
        'recipient_number',
        'recipient_name',
        'message_type',
        'template_name',
        'message_text',
        'media_url',
        'payload_json',
        'request_payload',
        'response_payload',
        'http_status_code',
        'provider_message_id',
        'status',
        'attempts',
        'max_attempts',
        'scheduled_at',
        'locked_at',
        'sent_at',
        'failed_at',
        'last_attempt_at',
        'error_message',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
