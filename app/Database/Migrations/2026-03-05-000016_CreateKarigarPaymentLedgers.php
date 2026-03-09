<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKarigarPaymentLedgers extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('karigar_payment_ledgers')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'karigar_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'entry_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'comment'    => 'charge/payment',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => 0,
            ],
            'reference_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('karigar_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('entry_type');
        $this->forge->createTable('karigar_payment_ledgers', true);
    }

    public function down()
    {
        $this->forge->dropTable('karigar_payment_ledgers', true);
    }
}
