<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddArattaiPurchaseBillFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('purchases')) {
            return;
        }
        $fields = [];
        foreach ([
            'bill_file_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'bill_file_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'bill_mime_type' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'bill_file_size' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'purchases')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('purchases', $fields);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('purchases')) {
            return;
        }
        foreach (['bill_file_name', 'bill_file_path', 'bill_mime_type', 'bill_file_size'] as $field) {
            if ($this->db->fieldExists($field, 'purchases')) {
                $this->forge->dropColumn('purchases', $field);
            }
        }
    }
}
