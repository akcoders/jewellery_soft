<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMobileApiTokensTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('mobile_api_tokens')) {
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
            'token_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
            ],
            'device_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
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
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('admin_user_id');
        $this->forge->addKey('expires_at');
        $this->forge->addKey('revoked_at');

        if ($this->db->tableExists('admin_users')) {
            $this->forge->addForeignKey('admin_user_id', 'admin_users', 'id', 'CASCADE', 'CASCADE');
        }

        $this->forge->createTable('mobile_api_tokens', true);
    }

    public function down()
    {
        $this->forge->dropTable('mobile_api_tokens', true);
    }
}

