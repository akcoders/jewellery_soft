<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterDiamondChalniColumnsToVarchar extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('items')) {
            return;
        }

        if ($this->db->fieldExists('chalni_from', 'items')) {
            $this->forge->modifyColumn('items', [
                'chalni_from' => [
                    'name'       => 'chalni_from',
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
            ]);
        }

        if ($this->db->fieldExists('chalni_to', 'items')) {
            $this->forge->modifyColumn('items', [
                'chalni_to' => [
                    'name'       => 'chalni_to',
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('items')) {
            return;
        }

        if ($this->db->fieldExists('chalni_from', 'items')) {
            $this->forge->modifyColumn('items', [
                'chalni_from' => [
                    'name'       => 'chalni_from',
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
            ]);
        }

        if ($this->db->fieldExists('chalni_to', 'items')) {
            $this->forge->modifyColumn('items', [
                'chalni_to' => [
                    'name'       => 'chalni_to',
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
            ]);
        }
    }
}

