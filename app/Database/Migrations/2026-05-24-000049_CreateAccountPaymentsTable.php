<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountPaymentsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('account_payments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'payment_no' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'party_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'karigar_id' => [
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
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
            ],
            'payment_mode' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ],
            'reference_no' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'reference_file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'reference_file_name' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
                'null' => true,
            ],
            'bill_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'bill_source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'bill_source_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'labour_bill_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
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
        $this->forge->addUniqueKey('payment_no');
        $this->forge->addKey('payment_date');
        $this->forge->addKey('party_type');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('vendor_id');
        $this->forge->addKey(['bill_type', 'bill_source_type', 'bill_source_id']);
        $this->forge->addKey('labour_bill_id');
        $this->forge->createTable('account_payments', true);
    }

    public function down()
    {
        $this->forge->dropTable('account_payments', true);
    }
}
