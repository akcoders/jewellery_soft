<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountsAdvancedTables extends Migration
{
    public function up()
    {
        $this->createDebitNotes();
        $this->createCreditNotes();
    }

    public function down()
    {
        $this->forge->dropTable('credit_notes', true);
        $this->forge->dropTable('debit_notes', true);
    }

    private function createDebitNotes(): void
    {
        if ($this->db->tableExists('debit_notes')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'note_no' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'note_date' => [
                'type' => 'DATE',
            ],
            'party_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'customer_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'vendor_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'invoice_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'reference_no' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'taxable_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'gst_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'default' => 0,
            ],
            'gst_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'Posted',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('note_no');
        $this->forge->addKey('note_date');
        $this->forge->addKey('party_type');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('vendor_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('debit_notes', true);
    }

    private function createCreditNotes(): void
    {
        if ($this->db->tableExists('credit_notes')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'note_no' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'note_date' => [
                'type' => 'DATE',
            ],
            'party_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'customer_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'vendor_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'invoice_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'reference_no' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'taxable_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'gst_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'default' => 0,
            ],
            'gst_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'Posted',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('note_no');
        $this->forge->addKey('note_date');
        $this->forge->addKey('party_type');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('vendor_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('credit_notes', true);
    }
}
