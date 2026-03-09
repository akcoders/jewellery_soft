<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductCategoriesAndProducts extends Migration
{
    public function up()
    {
        $this->createProductCategories();
        $this->createProducts();
    }

    public function down()
    {
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('product_categories', true);
    }

    private function createProductCategories(): void
    {
        if ($this->db->tableExists('product_categories')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('product_categories', true);
    }

    private function createProducts(): void
    {
        if ($this->db->tableExists('products')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'product_code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'product_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'item_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'Gold',
            ],
            'unit_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'gm',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('product_code');
        $this->forge->addKey('category_id');
        $this->forge->addKey('item_type');
        $this->forge->createTable('products', true);
    }
}

