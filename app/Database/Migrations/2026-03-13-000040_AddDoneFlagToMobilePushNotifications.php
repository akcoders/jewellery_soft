<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDoneFlagToMobilePushNotifications extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('mobile_push_notifications')) {
            return;
        }

        $fields = $this->db->getFieldNames('mobile_push_notifications');

        if (! in_array('done_flag', $fields, true)) {
            $this->forge->addColumn('mobile_push_notifications', [
                'done_flag' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'null' => false,
                    'after' => 'status',
                ],
            ]);
        }

        if (! in_array('done_at', $fields, true)) {
            $this->forge->addColumn('mobile_push_notifications', [
                'done_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'done_flag',
                ],
            ]);
        }

        try {
            $this->db->query('CREATE INDEX idx_mobile_push_done_status ON mobile_push_notifications (done_flag, status)');
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('mobile_push_notifications')) {
            return;
        }

        $fields = $this->db->getFieldNames('mobile_push_notifications');
        if (in_array('done_at', $fields, true)) {
            $this->forge->dropColumn('mobile_push_notifications', 'done_at');
        }
        if (in_array('done_flag', $fields, true)) {
            $this->forge->dropColumn('mobile_push_notifications', 'done_flag');
        }

        try {
            $this->db->query('DROP INDEX idx_mobile_push_done_status ON mobile_push_notifications');
        } catch (\Throwable $e) {
        }
    }
}
