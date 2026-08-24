<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductionImportTables extends Migration
{
    public function up()
    {
        $this->createBatches();
        $this->createSourceRows();
        $this->createPurchaseDocuments();
        $this->createGoldMovements();
        $this->createDiamondMovements();
        $this->createDiamondIssueLines();
        $this->createReadyItems();
    }

    public function down()
    {
        foreach ([
            'production_ready_items',
            'production_diamond_issue_lines',
            'production_diamond_movements',
            'production_gold_movements',
            'production_purchase_documents',
            'production_source_rows',
            'production_import_batches',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createBatches(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'source_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'source_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'imported_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'processing'],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'summary_json' => ['type' => 'LONGTEXT', 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('source_sha256');
        $this->forge->addKey('status');
        $this->forge->createTable('production_import_batches', true);
    }

    private function createSourceRows(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'source_file' => ['type' => 'VARCHAR', 'constraint' => 255],
            'sheet_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'row_number' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'record_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'record_key' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'data_json' => ['type' => 'LONGTEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey(['record_type', 'record_key']);
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_source_rows', true);
    }

    private function createPurchaseDocuments(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 40],
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'vendor_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'source_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'stored_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'document_date' => ['type' => 'DATE', 'null' => true],
            'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Unknown'],
            'file_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'metadata_json' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('vendor_id');
        $this->forge->addKey('document_date');
        $this->forge->addKey('sha256');
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_purchase_documents', true);
    }

    private function createGoldMovements(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'movement_date' => ['type' => 'DATE', 'null' => true],
            'party_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'purity_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'weight_24k_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'received_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 120],
            'source_row' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('movement_date');
        $this->forge->addKey('reference_no');
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_gold_movements', true);
    }

    private function createDiamondMovements(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'movement_date' => ['type' => 'DATE', 'null' => true],
            'party_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'quality_bucket' => ['type' => 'VARCHAR', 'constraint' => 80],
            'received_cts' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'issued_cts' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 120],
            'source_row' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('movement_date');
        $this->forge->addKey('reference_no');
        $this->forge->addKey('quality_bucket');
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_diamond_movements', true);
    }

    private function createDiamondIssueLines(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'issue_date' => ['type' => 'DATE', 'null' => true],
            'issue_group' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'design_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'quality' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'shade' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'size_label' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'pcs' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'weight_cts' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'bag_label' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 120],
            'source_row' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('issue_date');
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_diamond_issue_lines', true);
    }

    private function createReadyItems(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'ready_group' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'ready_date' => ['type' => 'DATE', 'null' => true],
            'serial_no' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'design_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'purity_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'gross_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'net_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'pure_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'gold_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'labour_charges' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'total_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'stones_json' => ['type' => 'LONGTEXT', 'null' => true],
            'status_note' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 120],
            'source_row' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('ready_date');
        $this->forge->addKey('reference_no');
        $this->forge->addForeignKey('batch_id', 'production_import_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('production_ready_items', true);
    }
}
