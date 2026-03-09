<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKarigarAndOrderAssignFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('karigars')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'phone' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'department' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'skills_text' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'rate_per_gm' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '14,2',
                    'default'    => 0,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('name');
            $this->forge->createTable('karigars', true);
        }

        if (! $this->db->fieldExists('assigned_karigar_id', 'orders')) {
            $this->forge->addColumn('orders', [
                'assigned_karigar_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'quotation_id',
                ],
                'assigned_at' => [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'assigned_karigar_id',
                ],
            ]);
            $this->db->query('CREATE INDEX idx_orders_assigned_karigar_id ON orders (assigned_karigar_id)');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('assigned_karigar_id', 'orders')) {
            $this->forge->dropColumn('orders', 'assigned_at');
            $this->forge->dropColumn('orders', 'assigned_karigar_id');
        }

        $this->forge->dropTable('karigars', true);
    }
}

