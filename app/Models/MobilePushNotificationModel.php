<?php

namespace App\Models;

use CodeIgniter\Model;

class MobilePushNotificationModel extends Model
{
    protected $table = 'mobile_push_notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $allowedFields = [
        'admin_user_id',
        'external_user_id',
        'dedupe_key',
        'type',
        'reference_table',
        'reference_id',
        'title',
        'message',
        'payload_json',
        'scheduled_at',
        'sent_at',
        'onesignal_message_id',
        'onesignal_idempotency_key',
        'status',
        'attempt_count',
        'last_attempt_at',
        'next_attempt_at',
        'done_flag',
        'done_at',
        'error_message',
        'response_json',
    ];
}
