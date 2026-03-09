<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDiamondPurchaseAttachmentsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('diamond_purchase_attachments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'purchase_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'file_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'file_size' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'uploaded_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('purchase_id');
        $this->forge->addForeignKey('purchase_id', 'purchase_headers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('diamond_purchase_attachments', true);
    }

    public function down()
    {
        if ($this->db->tableExists('diamond_purchase_attachments')) {
            $this->forge->dropTable('diamond_purchase_attachments', true);
        }
    }
}

