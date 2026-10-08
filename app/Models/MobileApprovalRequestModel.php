<?php

namespace App\Models;

use CodeIgniter\Model;

class MobileApprovalRequestModel extends Model
{
    protected $table = 'mobile_approval_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'request_type', 'status', 'summary', 'payload_json',
        'attachment_name', 'attachment_path', 'requested_by',
        'subject_table', 'subject_id',
        'reviewed_by', 'reviewed_at', 'review_note',
        'result_table', 'result_id', 'result_reference',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
