<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateArattaiUserMappings extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('arattai_user_mappings')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'arattai_user_id' => ['type' => 'VARCHAR', 'constraint' => 80],
            'is_approval_admin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['entity_type', 'entity_id'], 'uq_arattai_mapping_entity');
        $this->forge->addKey('arattai_user_id', false, false, 'idx_arattai_mapping_user');
        $this->forge->addKey(['is_approval_admin', 'is_active'], false, false, 'idx_arattai_mapping_approval');
        $this->forge->createTable('arattai_user_mappings');
    }

    public function down(): void
    {
        $this->forge->dropTable('arattai_user_mappings', true);
    }
}
