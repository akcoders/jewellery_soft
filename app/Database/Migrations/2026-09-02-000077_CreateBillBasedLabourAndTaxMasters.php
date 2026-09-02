<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillBasedLabourAndTaxMasters extends Migration
{
    public function up()
    {
        $this->createTaxTypes();
        $this->createGstMasters();
        $this->createGstMasterComponents();
        $this->enhanceLabourBills();
        $this->createLabourBillJobworks();
        $this->enhancePurchaseHeaders();
        $this->seedTaxMasters();
    }

    public function down()
    {
        foreach (['purchase_headers', 'gold_inventory_purchase_headers', 'stone_inventory_purchase_headers', 'purchases'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            foreach (['gst_master_id', 'tax_breakup_json'] as $column) {
                if ($this->db->fieldExists($column, $table)) {
                    $this->forge->dropColumn($table, $column);
                }
            }
        }

        if ($this->db->tableExists('stone_inventory_purchase_headers')) {
            foreach (['cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount', 'igst_rate', 'igst_amount', 'round_off_amount'] as $column) {
                if ($this->db->fieldExists($column, 'stone_inventory_purchase_headers')) {
                    $this->forge->dropColumn('stone_inventory_purchase_headers', $column);
                }
            }
        }

        if ($this->db->tableExists('purchases')) {
            foreach (['taxable_amount', 'gst_amount', 'round_off_amount'] as $column) {
                if ($this->db->fieldExists($column, 'purchases')) {
                    $this->forge->dropColumn('purchases', $column);
                }
            }
        }

        if ($this->db->tableExists('labour_bills')) {
            foreach ([
                'gst_master_id', 'tax_breakup_json', 'taxable_amount', 'cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount',
                'igst_rate', 'igst_amount', 'gst_amount', 'round_off_amount', 'attachment_path',
                'attachment_name', 'source_type',
            ] as $column) {
                if ($this->db->fieldExists($column, 'labour_bills')) {
                    $this->forge->dropColumn('labour_bills', $column);
                }
            }
        }

        $this->forge->dropTable('labour_bill_jobworks', true);
        $this->forge->dropTable('gst_master_components', true);
        $this->forge->dropTable('gst_masters', true);
        $this->forge->dropTable('tax_types', true);
    }

    private function createTaxTypes(): void
    {
        if ($this->db->tableExists('tax_types')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('tax_types', true);
    }

    private function createGstMasters(): void
    {
        if ($this->db->tableExists('gst_masters')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'total_percentage' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('gst_masters', true);
    }

    private function createGstMasterComponents(): void
    {
        if ($this->db->tableExists('gst_master_components')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'gst_master_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tax_type_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'percentage' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['gst_master_id', 'tax_type_id']);
        $this->forge->addKey('tax_type_id');
        $this->forge->createTable('gst_master_components', true);
    }

    private function enhanceLabourBills(): void
    {
        if (! $this->db->tableExists('labour_bills')) {
            return;
        }
        $fields = [
            'gst_master_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'karigar_id'],
            'tax_breakup_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'gst_master_id'],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'other_amount'],
            'cgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0, 'after' => 'taxable_amount'],
            'cgst_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'cgst_rate'],
            'sgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0, 'after' => 'cgst_amount'],
            'sgst_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'sgst_rate'],
            'igst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0, 'after' => 'sgst_amount'],
            'igst_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'igst_rate'],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'igst_amount'],
            'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'gst_amount'],
            'attachment_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'payment_status'],
            'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true, 'after' => 'attachment_path'],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Manual', 'after' => 'attachment_name'],
        ];
        $this->addMissingColumns('labour_bills', $fields);
    }

    private function createLabourBillJobworks(): void
    {
        if ($this->db->tableExists('labour_bill_jobworks')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'labour_bill_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jobwork_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'jobwork_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'receive_movement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'jobwork_date' => ['type' => 'DATE', 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 220, 'null' => true],
            'gross_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'net_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'labour_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['jobwork_type', 'jobwork_id']);
        $this->forge->addKey('labour_bill_id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('labour_bill_jobworks', true);
    }

    private function enhancePurchaseHeaders(): void
    {
        foreach (['purchase_headers', 'gold_inventory_purchase_headers', 'stone_inventory_purchase_headers', 'purchases'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            $this->addMissingColumns($table, [
                'gst_master_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'tax_breakup_json' => ['type' => 'LONGTEXT', 'null' => true],
            ]);
        }

        if ($this->db->tableExists('stone_inventory_purchase_headers')) {
            $this->addMissingColumns('stone_inventory_purchase_headers', [
                'cgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'cgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'sgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'sgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'igst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'igst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            ]);
        }

        if ($this->db->tableExists('purchases')) {
            $this->addMissingColumns('purchases', [
                'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
                'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
                'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            ]);
        }
    }

    private function seedTaxMasters(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (['CGST', 'SGST', 'IGST'] as $name) {
            if ($this->db->table('tax_types')->where('name', $name)->countAllResults() === 0) {
                $this->db->table('tax_types')->insert(['name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $templates = [
            ['name' => 'Exempt / No GST', 'parts' => []],
            ['name' => 'Local GST 3%', 'parts' => ['CGST' => 1.5, 'SGST' => 1.5]],
            ['name' => 'Interstate GST 3%', 'parts' => ['IGST' => 3.0]],
            ['name' => 'Local GST 5%', 'parts' => ['CGST' => 2.5, 'SGST' => 2.5]],
            ['name' => 'Interstate GST 5%', 'parts' => ['IGST' => 5.0]],
        ];
        foreach ($templates as $template) {
            $master = $this->db->table('gst_masters')->where('name', $template['name'])->get()->getRowArray();
            if (! $master) {
                $this->db->table('gst_masters')->insert([
                    'name' => $template['name'],
                    'total_percentage' => array_sum($template['parts']),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $masterId = (int) $this->db->insertID();
            } else {
                $masterId = (int) $master['id'];
            }
            foreach ($template['parts'] as $typeName => $percentage) {
                $type = $this->db->table('tax_types')->where('name', $typeName)->get()->getRowArray();
                if (! $type) {
                    continue;
                }
                if ($this->db->table('gst_master_components')->where(['gst_master_id' => $masterId, 'tax_type_id' => (int) $type['id']])->countAllResults() === 0) {
                    $this->db->table('gst_master_components')->insert([
                        'gst_master_id' => $masterId,
                        'tax_type_id' => (int) $type['id'],
                        'percentage' => $percentage,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /** @param array<string,array<string,mixed>> $fields */
    private function addMissingColumns(string $table, array $fields): void
    {
        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $definition]);
            }
        }
    }
}
