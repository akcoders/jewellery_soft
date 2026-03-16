<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShowroomRetailStockTables extends Migration
{
    public function up()
    {
        $this->extendShowroomsTable();
        $this->extendFgItemsTable();
        $this->createShowroomFgMovementsTable();
        $this->createShowroomReservationsTable();
        $this->seedRetailPermissions();
    }

    public function down()
    {
        if ($this->db->tableExists('showroom_reservations')) {
            $this->forge->dropTable('showroom_reservations', true);
        }
        if ($this->db->tableExists('showroom_fg_movements')) {
            $this->forge->dropTable('showroom_fg_movements', true);
        }

        foreach ([
            ['showrooms', 'warehouse_location_id'],
            ['fg_items', 'showroom_id'],
            ['fg_items', 'showroom_counter_id'],
            ['fg_items', 'showroom_stock_status'],
        ] as [$table, $field]) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }

    private function extendShowroomsTable(): void
    {
        if ($this->db->tableExists('showrooms') && ! $this->db->fieldExists('warehouse_location_id', 'showrooms')) {
            $this->forge->addColumn('showrooms', [
                'warehouse_location_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'manager_employee_id',
                ],
            ]);
        }
    }

    private function extendFgItemsTable(): void
    {
        if (! $this->db->tableExists('fg_items')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('showroom_id', 'fg_items')) {
            $fields['showroom_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'bin_id',
            ];
        }
        if (! $this->db->fieldExists('showroom_counter_id', 'fg_items')) {
            $fields['showroom_counter_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'showroom_id',
            ];
        }
        if (! $this->db->fieldExists('showroom_stock_status', 'fg_items')) {
            $fields['showroom_stock_status'] = [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'FG_STORE',
                'after' => 'showroom_counter_id',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('fg_items', $fields);
        }
    }

    private function createShowroomFgMovementsTable(): void
    {
        if ($this->db->tableExists('showroom_fg_movements')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'fg_item_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'movement_type' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'from_showroom_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'to_showroom_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'from_counter_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'to_counter_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'reference_type' => [
                'type' => 'VARCHAR',
                'constraint' => 60,
                'null' => true,
            ],
            'reference_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
            ],
            'remarks' => [
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
        $this->forge->addKey('fg_item_id');
        $this->forge->addKey('movement_type');
        $this->forge->addKey('to_showroom_id');
        $this->forge->createTable('showroom_fg_movements', true);
    }

    private function createShowroomReservationsTable(): void
    {
        if ($this->db->tableExists('showroom_reservations')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'fg_item_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'showroom_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'customer_id' => [
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
            'reserved_for_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'reserved_for_phone' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ],
            'reservation_status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'Reserved',
            ],
            'reserved_on' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_on' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'released_on' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('fg_item_id');
        $this->forge->addKey('showroom_id');
        $this->forge->addKey('reservation_status');
        $this->forge->createTable('showroom_reservations', true);
    }

    private function seedRetailPermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $permissions = [
            [
                'code' => 'showroom.stock.read',
                'name' => 'View Showroom Stock',
                'module_group' => 'Showroom',
                'action_key' => 'read',
                'description' => 'Access showroom stock movement, counters and reservations',
                'sort_order' => 502,
            ],
            [
                'code' => 'showroom.stock.manage',
                'name' => 'Manage Showroom Stock',
                'module_group' => 'Showroom',
                'action_key' => 'manage',
                'description' => 'Transfer FG to showroom, allocate to counters and move stock',
                'sort_order' => 503,
            ],
            [
                'code' => 'showroom.reservations.manage',
                'name' => 'Manage Showroom Reservations',
                'module_group' => 'Showroom',
                'action_key' => 'reserve',
                'description' => 'Reserve and release showroom FG items',
                'sort_order' => 504,
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
        $permissionRows = $this->db->table('permissions')->select('id')->whereIn('code', array_column($permissions, 'code'))->get()->getResultArray();
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
