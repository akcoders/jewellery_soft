<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminUserTourPreferences extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('admin_user_tour_preferences')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'admin_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'tour_key' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
            ],
            'tour_version' => [
                'type' => 'VARCHAR',
                'constraint' => 32,
                'null' => true,
            ],
            'state' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'new',
            ],
            'current_step_key' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'dont_show_again' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'dismissed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['admin_user_id', 'tour_key'], 'uq_admin_user_tour');
        $this->forge->addKey('state');
        $this->forge->addForeignKey('admin_user_id', 'admin_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admin_user_tour_preferences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admin_user_tour_preferences', true);
    }
}
