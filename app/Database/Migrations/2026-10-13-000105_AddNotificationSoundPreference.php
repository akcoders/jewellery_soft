<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNotificationSoundPreference extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('admin_users') || $this->db->fieldExists('notification_sound', 'admin_users')) {
            return;
        }

        $this->forge->addColumn('admin_users', [
            'notification_sound' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'default' => 'signature',
                'null' => false,
                'after' => 'followup_gallery_enabled',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('admin_users') && $this->db->fieldExists('notification_sound', 'admin_users')) {
            $this->forge->dropColumn('admin_users', 'notification_sound');
        }
    }
}
