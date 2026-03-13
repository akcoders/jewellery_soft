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
        'type',
        'reference_table',
        'reference_id',
        'title',
        'message',
        'payload_json',
        'scheduled_at',
        'sent_at',
        'onesignal_message_id',
        'status',
        'done_flag',
        'done_at',
        'error_message',
        'response_json',
    ];
}
