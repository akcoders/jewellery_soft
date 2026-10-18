<?php

namespace App\Models;

use CodeIgniter\Model;

class ArattaiOrderConversationModel extends Model
{
    protected $table = 'arattai_order_conversations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'arattai_user_id',
        'actor_admin_user_id',
        'current_step',
        'draft_data',
        'status',
        'order_id',
        'expires_at',
    ];
}
