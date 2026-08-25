<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class EnhanceMobilePushReliability extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('mobile_push_notifications')) {
            return;
        }

        $fields = $this->db->getFieldNames('mobile_push_notifications');
        $add = [];

        if (! in_array('dedupe_key', $fields, true)) {
            $add['dedupe_key'] = [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'external_user_id',
            ];
        }
        if (! in_array('onesignal_idempotency_key', $fields, true)) {
            $add['onesignal_idempotency_key'] = [
                'type' => 'CHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'onesignal_message_id',
            ];
        }
        if (! in_array('attempt_count', $fields, true)) {
            $add['attempt_count'] = [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 0,
                'null' => false,
                'after' => 'status',
            ];
        }
        if (! in_array('last_attempt_at', $fields, true)) {
            $add['last_attempt_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'attempt_count',
            ];
        }
        if (! in_array('next_attempt_at', $fields, true)) {
            $add['next_attempt_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'last_attempt_at',
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('mobile_push_notifications', $add);
        }

        $indexes = $this->db->getIndexData('mobile_push_notifications');
        if (! array_key_exists('uq_mobile_push_dedupe_key', $indexes)) {
            $this->db->query('CREATE UNIQUE INDEX uq_mobile_push_dedupe_key ON mobile_push_notifications (dedupe_key)');
        }

        $indexes = $this->db->getIndexData('mobile_push_notifications');
        if (! array_key_exists('idx_mobile_push_due_retry', $indexes)) {
            $this->db->query('CREATE INDEX idx_mobile_push_due_retry ON mobile_push_notifications (status, done_flag, scheduled_at, next_attempt_at)');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('mobile_push_notifications')) {
            return;
        }

        foreach (['uq_mobile_push_dedupe_key', 'idx_mobile_push_due_retry'] as $index) {
            $indexes = $this->db->getIndexData('mobile_push_notifications');
            if (array_key_exists($index, $indexes)) {
                $this->db->query('DROP INDEX ' . $index . ' ON mobile_push_notifications');
            }
        }

        $fields = $this->db->getFieldNames('mobile_push_notifications');
        foreach (['next_attempt_at', 'last_attempt_at', 'attempt_count', 'onesignal_idempotency_key', 'dedupe_key'] as $column) {
            if (in_array($column, $fields, true)) {
                $this->forge->dropColumn('mobile_push_notifications', $column);
            }
        }
    }
}
