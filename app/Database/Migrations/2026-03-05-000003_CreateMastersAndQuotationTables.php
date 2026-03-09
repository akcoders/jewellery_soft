<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMastersAndQuotationTables extends Migration
{
    public function up()
    {
        $this->createDesignMasters();
        $this->createDesignVariants();
        $this->createGoldPurities();
        $this->createDiamondGradings();
        $this->createQuotations();
        $this->createQuotationVersions();
        $this->createQuotationItems();
    }

    public function down()
    {
        $this->forge->dropTable('quotation_items', true);
        $this->forge->dropTable('quotation_versions', true);
        $this->forge->dropTable('quotations', true);
        $this->forge->dropTable('diamond_gradings', true);
        $this->forge->dropTable('gold_purities', true);
        $this->forge->dropTable('design_variants', true);
        $this->forge->dropTable('design_masters', true);
    }

    private function createDesignMasters(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'design_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('design_code');
        $this->forge->addKey('category');
        $this->forge->createTable('design_masters', true);
    }

    private function createDesignVariants(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'design_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'variant_code' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'ring_size' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'bangle_size' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'length_mm' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('variant_code');
        $this->forge->addKey('design_id');
        $this->forge->createTable('design_variants', true);
    }

    private function createGoldPurities(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'purity_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'purity_percent' => ['type' => 'DECIMAL', 'constraint' => '6,3', 'default' => 0],
            'color_name' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('purity_code');
        $this->forge->createTable('gold_purities', true);
    }

    private function createDiamondGradings(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'shape' => ['type' => 'VARCHAR', 'constraint' => 40],
            'sieve' => ['type' => 'VARCHAR', 'constraint' => 40],
            'color' => ['type' => 'VARCHAR', 'constraint' => 40],
            'clarity' => ['type' => 'VARCHAR', 'constraint' => 40],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['shape', 'sieve', 'color', 'clarity']);
        $this->forge->createTable('diamond_gradings', true);
    }

    private function createQuotations(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quote_no' => ['type' => 'VARCHAR', 'constraint' => 40],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'lead_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Draft'],
            'current_version' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'approved_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'valid_until' => ['type' => 'DATE', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('quote_no');
        $this->forge->createTable('quotations', true);
    }

    private function createQuotationVersions(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quotation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'version_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Draft'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['quotation_id', 'version_no']);
        $this->forge->createTable('quotation_versions', true);
    }

    private function createQuotationItems(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quotation_version_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'design_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'size_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'gold_estimate_gm' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'diamond_estimate_cts' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('quotation_version_id');
        $this->forge->createTable('quotation_items', true);
    }
}

