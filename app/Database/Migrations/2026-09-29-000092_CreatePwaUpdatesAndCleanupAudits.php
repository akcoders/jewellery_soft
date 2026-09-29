<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePwaUpdatesAndCleanupAudits extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('pwa_update_releases')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'version' => ['type' => 'VARCHAR', 'constraint' => 64],
                'message' => ['type' => 'VARCHAR', 'constraint' => 255],
                'released_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('version');
            $this->forge->addKey('created_at');
            $this->forge->createTable('pwa_update_releases', true);
        }

        if (! $this->db->tableExists('monthly_data_cleanup_audits')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'month_key' => ['type' => 'CHAR', 'constraint' => 7],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'started'],
                'preview_json' => ['type' => 'LONGTEXT', 'null' => true],
                'result_json' => ['type' => 'LONGTEXT', 'null' => true],
                'error_message' => ['type' => 'TEXT', 'null' => true],
                'requested_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'started_at' => ['type' => 'DATETIME', 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('month_key');
            $this->forge->addKey('status');
            $this->forge->createTable('monthly_data_cleanup_audits', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('monthly_data_cleanup_audits', true);
        $this->forge->dropTable('pwa_update_releases', true);
    }
}
