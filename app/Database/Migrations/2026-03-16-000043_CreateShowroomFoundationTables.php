<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShowroomFoundationTables extends Migration
{
    public function up()
    {
        $this->createShowroomsTable();
        $this->createShowroomCountersTable();
        $this->createShowroomStaffAssignmentsTable();
        $this->seedShowroomPermissions();
    }

    public function down()
    {
        if ($this->db->tableExists('showroom_staff_assignments')) {
            $this->forge->dropTable('showroom_staff_assignments', true);
        }
        if ($this->db->tableExists('showroom_counters')) {
            $this->forge->dropTable('showroom_counters', true);
        }
        if ($this->db->tableExists('showrooms')) {
            $this->forge->dropTable('showrooms', true);
        }
    }

    private function createShowroomsTable(): void
    {
        if ($this->db->tableExists('showrooms')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'showroom_code' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'showroom_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'Retail Showroom',
            ],
            'manager_employee_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'gstin' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ],
            'state_name' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'city_name' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'address_line' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'opening_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('showroom_code');
        $this->forge->addKey('manager_employee_id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('showrooms', true);
    }

    private function createShowroomCountersTable(): void
    {
        if ($this->db->tableExists('showroom_counters')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'showroom_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'counter_code' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'counter_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
            ],
            'counter_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'Sales Counter',
            ],
            'incharge_employee_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['showroom_id', 'counter_code']);
        $this->forge->addKey('showroom_id');
        $this->forge->addKey('incharge_employee_id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('showroom_counters', true);
    }

    private function createShowroomStaffAssignmentsTable(): void
    {
        if ($this->db->tableExists('showroom_staff_assignments')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'showroom_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'employee_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'role_label' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'default' => 'Staff',
            ],
            'is_primary' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'effective_from' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['showroom_id', 'employee_id', 'effective_from'], 'uniq_showroom_staff_effective');
        $this->forge->addKey('showroom_id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('showroom_staff_assignments', true);
    }

    private function seedShowroomPermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $permissions = [
            [
                'code' => 'showroom.masters.read',
                'name' => 'View Showroom Masters',
                'module_group' => 'Showroom',
                'action_key' => 'read',
                'description' => 'Access showroom, counter and staff assignment masters',
                'sort_order' => 500,
            ],
            [
                'code' => 'showroom.masters.manage',
                'name' => 'Manage Showroom Masters',
                'module_group' => 'Showroom',
                'action_key' => 'manage',
                'description' => 'Create and update showroom foundation masters',
                'sort_order' => 501,
            ],
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->table('permissions')->where('code', $permission['code'])->get()->getRowArray();
            $data = $permission + [
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                $this->db->table('permissions')->where('id', $existing['id'])->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('permissions')->insert($data);
            }
        }

        $superRole = $this->db->table('roles')->where('role_code', 'SUPER_ADMIN')->get()->getRowArray();
        if (! $superRole) {
            return;
        }

        $permissionRows = $this->db->table('permissions')
            ->select('id')
            ->whereIn('code', array_column($permissions, 'code'))
            ->get()
            ->getResultArray();

        foreach ($permissionRows as $permissionRow) {
            $permissionId = (int) ($permissionRow['id'] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }

            $exists = $this->db->table('role_permissions')
                ->where('role_id', (int) $superRole['id'])
                ->where('permission_id', $permissionId)
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $superRole['id'],
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
