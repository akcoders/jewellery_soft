<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStaffPerformanceWorkflow extends Migration
{
    public function up(): void
    {
        $this->addOrderFollowupOwnership();
        $this->createFollowupSchedules();
        $this->enhanceTasks();
        $this->replaceKpiPermissions();
    }

    public function down(): void
    {
        if ($this->db->tableExists('order_followup_schedules')) {
            $this->forge->dropTable('order_followup_schedules', true);
        }

        if ($this->db->tableExists('orders')) {
            foreach (['followup_assigned_to', 'followup_due_at'] as $field) {
                if ($this->db->fieldExists($field, 'orders')) {
                    $this->forge->dropColumn('orders', $field);
                }
            }
        }

        if ($this->db->tableExists('mobile_tasks')) {
            foreach (['priority', 'completed_at', 'completed_by', 'proof_name', 'proof_path', 'proof_note', 'counts_for_performance', 'score_delta'] as $field) {
                if ($this->db->fieldExists($field, 'mobile_tasks')) {
                    $this->forge->dropColumn('mobile_tasks', $field);
                }
            }
        }

        if ($this->db->tableExists('permissions')) {
            if ($this->db->tableExists('role_permissions')) {
                $permissionRows = $this->db->table('permissions')
                    ->select('id')
                    ->whereIn('code', ['performance.tasks.read', 'performance.tasks.manage'])
                    ->get()->getResultArray();
                $permissionIds = array_map(static fn(array $row): int => (int) $row['id'], $permissionRows);
                if ($permissionIds !== []) {
                    $this->db->table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }
            }
            $this->db->table('permissions')->whereIn('code', ['performance.tasks.read', 'performance.tasks.manage'])->delete();
            $this->db->table('permissions')->whereIn('code', $this->legacyPermissionCodes())->update(['is_active' => 1]);
        }
    }

    private function addOrderFollowupOwnership(): void
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('followup_assigned_to', 'orders')) {
            $fields['followup_assigned_to'] = [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'assigned_at',
            ];
        }
        if (! $this->db->fieldExists('followup_due_at', 'orders')) {
            $fields['followup_due_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'followup_assigned_to',
            ];
        }
        if ($fields !== []) {
            $this->forge->addColumn('orders', $fields);
        }
    }

    private function createFollowupSchedules(): void
    {
        if ($this->db->tableExists('order_followup_schedules')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'assigned_to' => ['type' => 'INT', 'unsigned' => true],
            'due_at' => ['type' => 'DATETIME'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'completed_followup_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'completed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'score_delta' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('assigned_to');
        $this->forge->addKey('due_at');
        $this->forge->addKey('status');
        $this->forge->createTable('order_followup_schedules', true);
    }

    private function enhanceTasks(): void
    {
        if (! $this->db->tableExists('mobile_tasks')) {
            return;
        }

        $fields = [];
        $definitions = [
            'priority' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'normal', 'after' => 'note'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_done'],
            'completed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'completed_at'],
            'proof_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'completed_by'],
            'proof_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'proof_name'],
            'proof_note' => ['type' => 'TEXT', 'null' => true, 'after' => 'proof_path'],
            'counts_for_performance' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'proof_note'],
            'score_delta' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0, 'after' => 'counts_for_performance'],
        ];
        foreach ($definitions as $name => $definition) {
            if (! $this->db->fieldExists($name, 'mobile_tasks')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('mobile_tasks', $fields);
        }

        // Existing rows were personal reminder tasks created before staff scoring existed.
        $this->db->table('mobile_tasks')->update(['counts_for_performance' => 0, 'score_delta' => 0]);
    }

    private function replaceKpiPermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $permissions = [
            [
                'code' => 'performance.tasks.read',
                'name' => 'View Staff Tasks',
                'module_group' => 'Performance',
                'action_key' => 'read',
                'description' => 'View assigned staff tasks and completion proof',
                'sort_order' => 521,
            ],
            [
                'code' => 'performance.tasks.manage',
                'name' => 'Manage Staff Tasks',
                'module_group' => 'Performance',
                'action_key' => 'manage',
                'description' => 'Assign and cancel performance tasks',
                'sort_order' => 522,
            ],
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->table('permissions')->where('code', $permission['code'])->get()->getRowArray();
            $data = $permission + ['is_active' => 1, 'updated_at' => $now];
            if ($existing) {
                $this->db->table('permissions')->where('id', $existing['id'])->update($data);
            } else {
                $data['created_at'] = $now;
                $this->db->table('permissions')->insert($data);
            }
        }

        $this->db->table('permissions')->where('code', 'performance.dashboard.read')->update([
            'name' => 'View Staff Performance',
            'description' => 'View due-date based staff performance dashboard',
            'is_active' => 1,
            'updated_at' => $now,
        ]);
        $this->db->table('permissions')->whereIn('code', $this->legacyPermissionCodes())->update([
            'is_active' => 0,
            'updated_at' => $now,
        ]);

        if (! $this->db->tableExists('roles') || ! $this->db->tableExists('role_permissions')) {
            return;
        }
        $roles = $this->db->table('roles')
            ->select('id')
            ->groupStart()
                ->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
                ->orWhereIn('name', ['SUPER_ADMIN', 'ADMIN', 'OWNER', 'Super Admin', 'Admin', 'Owner'])
            ->groupEnd()
            ->get()->getResultArray();
        $permissionIds = $this->db->table('permissions')->select('id')->whereIn('code', [
            'performance.dashboard.read',
            'performance.tasks.read',
            'performance.tasks.manage',
        ])->get()->getResultArray();
        foreach ($roles as $role) {
            foreach ($permissionIds as $permission) {
                $exists = $this->db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_id', (int) $permission['id'])
                    ->countAllResults() > 0;
                if (! $exists) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission_id' => (int) $permission['id'],
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    /** @return list<string> */
    private function legacyPermissionCodes(): array
    {
        return [
            'performance.kpis.read',
            'performance.kpis.manage',
            'performance.targets.read',
            'performance.targets.manage',
            'performance.incentives.read',
            'performance.incentives.manage',
        ];
    }
}
