<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDiamondInventoryAdjustments extends Migration
{
    public function up()
    {
        $this->createHeaders();
        $this->createLines();
    }

    public function down()
    {
        if ($this->db->tableExists('diamond_inventory_adjustment_lines')) {
            $this->forge->dropTable('diamond_inventory_adjustment_lines', true);
        }
        if ($this->db->tableExists('diamond_inventory_adjustment_headers')) {
            $this->forge->dropTable('diamond_inventory_adjustment_headers', true);
        }
    }

    private function createHeaders(): void
    {
        if ($this->db->tableExists('diamond_inventory_adjustment_headers')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'adjustment_date' => ['type' => 'DATE'],
            'adjustment_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'add'],
            'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('adjustment_date');
        $this->forge->addKey('adjustment_type');
        $this->forge->addKey('location_id');
        if ($this->db->tableExists('inventory_locations')) {
            $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
        }
        $this->forge->createTable('diamond_inventory_adjustment_headers', true);
    }

    private function createLines(): void
    {
        if ($this->db->tableExists('diamond_inventory_adjustment_lines')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'adjustment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'pcs' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'rate_per_carat' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('adjustment_id');
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('adjustment_id', 'diamond_inventory_adjustment_headers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('diamond_inventory_adjustment_lines', true);
    }
}

