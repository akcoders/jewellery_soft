<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomerRememberTokens extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('customer_remember_tokens')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'customer_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'selector' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
            ],
            'validator_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('selector');
        $this->forge->addKey('customer_user_id');
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('customer_user_id', 'customer_users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('customer_remember_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('customer_remember_tokens', true);
    }
}
