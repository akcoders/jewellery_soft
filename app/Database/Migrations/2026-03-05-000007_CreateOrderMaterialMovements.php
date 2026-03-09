<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderMaterialMovements extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'movement_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'issue',
            ],
            'gold_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'diamond_cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('movement_type');
        $this->forge->createTable('order_material_movements', true);
    }

    public function down()
    {
        $this->forge->dropTable('order_material_movements', true);
    }
}

