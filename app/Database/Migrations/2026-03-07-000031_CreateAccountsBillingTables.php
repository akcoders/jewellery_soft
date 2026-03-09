<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountsBillingTables extends Migration
{
    public function up()
    {
        $this->createPurchaseBillPayments();
        $this->createLabourBills();
        $this->createLabourBillPayments();
    }

    public function down()
    {
        $this->forge->dropTable('labour_bill_payments', true);
        $this->forge->dropTable('labour_bills', true);
        $this->forge->dropTable('purchase_bill_payments', true);
    }

    private function createPurchaseBillPayments(): void
    {
        if ($this->db->tableExists('purchase_bill_payments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'source_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'reference_no' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
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
        $this->forge->addKey(['source_type', 'source_id']);
        $this->forge->addKey('payment_date');
        $this->forge->createTable('purchase_bill_payments', true);
    }

    private function createLabourBills(): void
    {
        if ($this->db->tableExists('labour_bills')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'bill_no' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'bill_date' => [
                'type' => 'DATE',
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'receive_movement_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'karigar_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'gold_weight_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,3',
                'default' => 0,
            ],
            'rate_per_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'labour_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'other_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'due_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'payment_status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'Pending',
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
        $this->forge->addUniqueKey('bill_no');
        $this->forge->addUniqueKey('receive_movement_id');
        $this->forge->addKey('bill_date');
        $this->forge->addKey('order_id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('payment_status');
        $this->forge->createTable('labour_bills', true);
    }

    private function createLabourBillPayments(): void
    {
        if ($this->db->tableExists('labour_bill_payments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'labour_bill_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'reference_no' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
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
        $this->forge->addKey('labour_bill_id');
        $this->forge->addKey('payment_date');
        $this->forge->createTable('labour_bill_payments', true);
    }
}
