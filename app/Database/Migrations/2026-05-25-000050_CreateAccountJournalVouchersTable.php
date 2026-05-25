<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountJournalVouchersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('account_journal_vouchers')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'voucher_no' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
            ],
            'voucher_date' => [
                'type' => 'DATE',
            ],
            'voucher_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'from_party_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'from_party_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'to_party_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'to_party_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'expense_head' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
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
        $this->forge->addUniqueKey('voucher_no');
        $this->forge->addKey('voucher_date');
        $this->forge->addKey('voucher_type');
        $this->forge->addKey(['from_party_type', 'from_party_id']);
        $this->forge->addKey(['to_party_type', 'to_party_id']);
        $this->forge->addKey('status');
        $this->forge->createTable('account_journal_vouchers', true);
    }

    public function down()
    {
        $this->forge->dropTable('account_journal_vouchers', true);
    }
}
