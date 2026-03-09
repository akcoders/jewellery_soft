<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryTables extends Migration
{
    public function up()
    {
        $this->createInventoryLocations();
        $this->createInventoryItems();
    }

    public function down()
    {
        $this->forge->dropTable('inventory_items', true);
        $this->forge->dropTable('inventory_locations', true);
    }

    private function createInventoryLocations(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'location_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'Store',
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
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('inventory_locations', true);
    }

    private function createInventoryItems(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'Gold',
            ],
            'material_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'gold_purity_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'diamond_shape' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'diamond_sieve' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'diamond_color' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'diamond_clarity' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'pcs' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'weight_gm' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'cts' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,3',
                'default'    => 0,
            ],
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reference_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('item_type');
        $this->forge->addKey('location_id');
        $this->forge->createTable('inventory_items', true);
    }
}

