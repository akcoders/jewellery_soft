<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsappMessageLogModel extends Model
{
    protected $table = 'whatsapp_message_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'event_key',
        'event_hash',
        'order_id',
        'customer_id',
        'recipient_phone',
        'message_text',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
        'sent_on',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
