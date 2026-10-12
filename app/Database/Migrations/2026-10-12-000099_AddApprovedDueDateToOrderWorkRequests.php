<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApprovedDueDateToOrderWorkRequests extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('order_work_requests')
            && ! $this->db->fieldExists('approved_due_at', 'order_work_requests')) {
            $this->forge->addColumn('order_work_requests', [
                'approved_due_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'requested_due_at',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('order_work_requests')
            && $this->db->fieldExists('approved_due_at', 'order_work_requests')) {
            $this->forge->dropColumn('order_work_requests', 'approved_due_at');
        }
    }
}
