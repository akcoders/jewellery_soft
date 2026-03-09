<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorAndTotalsToDiamondPurchaseHeaders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('purchase_headers')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('vendor_id', 'purchase_headers')) {
            $fields['vendor_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'purchase_date',
            ];
        }
        if (! $this->db->fieldExists('due_date', 'purchase_headers')) {
            $fields['due_date'] = [
                'type' => 'DATE',
                'null' => true,
                'after' => 'invoice_no',
            ];
        }
        if (! $this->db->fieldExists('tax_percentage', 'purchase_headers')) {
            $fields['tax_percentage'] = [
                'type' => 'DECIMAL',
                'constraint' => '6,3',
                'default' => 0,
                'after' => 'due_date',
            ];
        }
        if (! $this->db->fieldExists('invoice_total', 'purchase_headers')) {
            $fields['invoice_total'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'tax_percentage',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_headers', $fields);
        }

        $vendorIdx = $this->db->query("SHOW INDEX FROM purchase_headers WHERE Key_name = 'idx_purchase_headers_vendor_id'")->getResultArray();
        if ($vendorIdx === [] && $this->db->fieldExists('vendor_id', 'purchase_headers')) {
            $this->db->query('CREATE INDEX idx_purchase_headers_vendor_id ON purchase_headers (vendor_id)');
        }

        $dueIdx = $this->db->query("SHOW INDEX FROM purchase_headers WHERE Key_name = 'idx_purchase_headers_due_date'")->getResultArray();
        if ($dueIdx === [] && $this->db->fieldExists('due_date', 'purchase_headers')) {
            $this->db->query('CREATE INDEX idx_purchase_headers_due_date ON purchase_headers (due_date)');
        }

        if ($this->db->tableExists('vendors') && $this->db->fieldExists('vendor_id', 'purchase_headers')) {
            $fk = $this->db->query("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'purchase_headers'
                  AND COLUMN_NAME = 'vendor_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ")->getResultArray();

            if ($fk === []) {
                $this->db->query('ALTER TABLE purchase_headers ADD CONSTRAINT fk_purchase_headers_vendor_id FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON UPDATE CASCADE ON DELETE SET NULL');
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('purchase_headers')) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE purchase_headers DROP FOREIGN KEY fk_purchase_headers_vendor_id');
        } catch (\Throwable $e) {
        }

        foreach (['idx_purchase_headers_vendor_id', 'idx_purchase_headers_due_date'] as $indexName) {
            try {
                $this->db->query('DROP INDEX ' . $indexName . ' ON purchase_headers');
            } catch (\Throwable $e) {
            }
        }

        $dropColumns = [];
        foreach (['vendor_id', 'due_date', 'tax_percentage', 'invoice_total'] as $column) {
            if ($this->db->fieldExists($column, 'purchase_headers')) {
                $dropColumns[] = $column;
            }
        }

        if ($dropColumns !== []) {
            $this->forge->dropColumn('purchase_headers', $dropColumns);
        }
    }
}
