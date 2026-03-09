<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddKarigarDetailsAndDocuments extends Migration
{
    public function up()
    {
        $this->addKarigarColumns();
        $this->createKarigarDocuments();
    }

    public function down()
    {
        $this->forge->dropTable('karigar_documents', true);

        $columns = [
            'notes',
            'ifsc_code',
            'bank_account_no',
            'bank_name',
            'joining_date',
            'pan_no',
            'aadhaar_no',
            'pincode',
            'state',
            'city',
            'address',
            'email',
        ];
        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, 'karigars')) {
                $this->forge->dropColumn('karigars', $column);
            }
        }
    }

    private function addKarigarColumns(): void
    {
        if (! $this->db->tableExists('karigars')) {
            return;
        }

        $columns = [
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'phone',
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'email',
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'address',
            ],
            'state' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'city',
            ],
            'pincode' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
                'after'      => 'state',
            ],
            'aadhaar_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'pincode',
            ],
            'pan_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'aadhaar_no',
            ],
            'joining_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'pan_no',
            ],
            'bank_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'joining_date',
            ],
            'bank_account_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
                'after'      => 'bank_name',
            ],
            'ifsc_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'bank_account_no',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'ifsc_code',
            ],
        ];

        foreach ($columns as $name => $def) {
            if (! $this->db->fieldExists($name, 'karigars')) {
                $this->forge->addColumn('karigars', [$name => $def]);
            }
        }
    }

    private function createKarigarDocuments(): void
    {
        if ($this->db->tableExists('karigar_documents')) {
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
            'document_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'remarks' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'uploaded_by' => [
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
        $this->forge->createTable('karigar_documents', true);
    }
}

