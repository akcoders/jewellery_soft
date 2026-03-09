<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDiamondInventoryReturnsAndOrderReference extends Migration
{
    public function up()
    {
        $this->addOrderReferenceToIssueHeaders();
        $this->createReturnHeadersTable();
        $this->createReturnLinesTable();
    }

    public function down()
    {
        if ($this->db->tableExists('return_lines')) {
            $this->forge->dropTable('return_lines', true);
        }

        if ($this->db->tableExists('return_headers')) {
            $this->forge->dropTable('return_headers', true);
        }

        if ($this->db->tableExists('issue_headers') && $this->db->fieldExists('order_id', 'issue_headers')) {
            $this->forge->dropColumn('issue_headers', 'order_id');
        }
    }

    private function addOrderReferenceToIssueHeaders(): void
    {
        if (! $this->db->tableExists('issue_headers') || $this->db->fieldExists('order_id', 'issue_headers')) {
            return;
        }

        $this->forge->addColumn('issue_headers', [
            'order_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'issue_date',
            ],
        ]);
        $idx = $this->db->query("SHOW INDEX FROM issue_headers WHERE Key_name = 'idx_issue_headers_order_id'")->getResultArray();
        if ($idx === []) {
            $this->db->query('CREATE INDEX idx_issue_headers_order_id ON issue_headers (order_id)');
        }
    }

    private function createReturnHeadersTable(): void
    {
        if ($this->db->tableExists('return_headers')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'return_date' => ['type' => 'DATE'],
            'order_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'return_from' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('return_date');
        $this->forge->addKey('order_id');
        $this->forge->createTable('return_headers', true);
    }

    private function createReturnLinesTable(): void
    {
        if ($this->db->tableExists('return_lines')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'return_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'pcs' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'rate_per_carat' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'line_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('return_id', 'return_headers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addKey('return_id');
        $this->forge->addKey('item_id');
        $this->forge->createTable('return_lines', true);
    }
}
