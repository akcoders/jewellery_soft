<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceRbacTables extends Migration
{
    public function up()
    {
        $this->addRoleColumns();
        $this->addPermissionColumns();
        $this->createUserPermissionsTable();
        $this->seedDefaultPermissions();
    }

    public function down()
    {
        if ($this->db->tableExists('user_permissions')) {
            $this->forge->dropTable('user_permissions', true);
        }

        foreach ([
            'roles' => ['role_code', 'is_active'],
            'permissions' => ['module_group', 'action_key', 'description', 'sort_order', 'is_active'],
        ] as $table => $fields) {
            foreach ($fields as $field) {
                if ($this->db->fieldExists($field, $table)) {
                    $this->forge->dropColumn($table, $field);
                }
            }
        }
    }

    private function addRoleColumns(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('role_code', 'roles')) {
            $fields['role_code'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'id'];
        }
        if (! $this->db->fieldExists('is_active', 'roles')) {
            $fields['is_active'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'description'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('roles', $fields);
        }

        if ($this->db->fieldExists('role_code', 'roles')) {
            $rows = $this->db->table('roles')->get()->getResultArray();
            foreach ($rows as $row) {
                $code = trim((string) ($row['role_code'] ?? ''));
                if ($code === '') {
                    $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string) ($row['name'] ?? 'ROLE_' . $row['id']))));
                    $code = trim($code, '_');
                    if ($code === '') {
                        $code = 'ROLE_' . (int) $row['id'];
                    }
                    $this->db->table('roles')->where('id', $row['id'])->update(['role_code' => $code]);
                }
            }

            $indexes = $this->db->getIndexData('roles');
            if (! isset($indexes['roles_role_code_unique'])) {
                $this->db->query('CREATE UNIQUE INDEX roles_role_code_unique ON roles (role_code)');
            }
        }
    }

    private function addPermissionColumns(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('module_group', 'permissions')) {
            $fields['module_group'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'name'];
        }
        if (! $this->db->fieldExists('action_key', 'permissions')) {
            $fields['action_key'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'module_group'];
        }
        if (! $this->db->fieldExists('description', 'permissions')) {
            $fields['description'] = ['type' => 'TEXT', 'null' => true, 'after' => 'action_key'];
        }
        if (! $this->db->fieldExists('sort_order', 'permissions')) {
            $fields['sort_order'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'after' => 'description'];
        }
        if (! $this->db->fieldExists('is_active', 'permissions')) {
            $fields['is_active'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'sort_order'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('permissions', $fields);
        }
    }

    private function createUserPermissionsTable(): void
    {
        if ($this->db->tableExists('user_permissions')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'permission_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'access_type' => ['type' => 'VARCHAR', 'constraint' => 10],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'permission_id']);
        $this->forge->addKey(['user_id']);
        $this->forge->addKey(['permission_id']);
        $this->forge->createTable('user_permissions', true);
    }

    private function seedDefaultPermissions(): void
    {
        $permissions = $this->defaultPermissions();

        foreach ($permissions as $index => $permission) {
            $existing = $this->db->table('permissions')->where('code', $permission['code'])->get()->getRowArray();
            $data = [
                'code' => $permission['code'],
                'name' => $permission['name'],
                'module_group' => $permission['module_group'],
                'action_key' => $permission['action_key'],
                'description' => $permission['description'],
                'sort_order' => $permission['sort_order'] ?? ($index + 1),
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
        $roleData = [
            'role_code' => 'SUPER_ADMIN',
            'name' => 'Super Admin',
            'description' => 'Full unrestricted ERP access',
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($superRole) {
            $this->db->table('roles')->where('id', $superRole['id'])->update($roleData);
            $superRoleId = (int) $superRole['id'];
        } else {
            $roleData['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('roles')->insert($roleData);
            $superRoleId = (int) $this->db->insertID();
        }

        $permissionIds = $this->db->table('permissions')->select('id')->get()->getResultArray();
        foreach ($permissionIds as $permissionRow) {
            $permissionId = (int) ($permissionRow['id'] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }

            $exists = $this->db->table('role_permissions')
                ->where('role_id', $superRoleId)
                ->where('permission_id', $permissionId)
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $superRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($this->db->tableExists('admin_users')) {
            $users = $this->db->table('admin_users')->select('id')->get()->getResultArray();
            foreach ($users as $user) {
                $userId = (int) ($user['id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                $assigned = $this->db->table('user_roles')->where('user_id', $userId)->countAllResults();
                if ($assigned === 0) {
                    $this->db->table('user_roles')->insert([
                        'user_id' => $userId,
                        'role_id' => $superRoleId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    private function defaultPermissions(): array
    {
        return [
            ['code' => 'dashboard.read', 'name' => 'View Dashboard', 'module_group' => 'Dashboard', 'action_key' => 'read', 'description' => 'Access dashboard summary cards'],
            ['code' => 'leads.read', 'name' => 'View Leads', 'module_group' => 'Leads', 'action_key' => 'read', 'description' => 'Open and review leads'],
            ['code' => 'leads.create', 'name' => 'Create Leads', 'module_group' => 'Leads', 'action_key' => 'create', 'description' => 'Create new leads'],
            ['code' => 'leads.edit', 'name' => 'Edit Leads', 'module_group' => 'Leads', 'action_key' => 'edit', 'description' => 'Update leads, stages, notes and images'],
            ['code' => 'customers.read', 'name' => 'View Customers', 'module_group' => 'Customers', 'action_key' => 'read', 'description' => 'Access customer master'],
            ['code' => 'customers.create', 'name' => 'Manage Customers', 'module_group' => 'Customers', 'action_key' => 'create', 'description' => 'Create and edit customers'],
            ['code' => 'orders.read', 'name' => 'View Orders', 'module_group' => 'Orders', 'action_key' => 'read', 'description' => 'Access order listing and detail'],
            ['code' => 'orders.create', 'name' => 'Create Orders', 'module_group' => 'Orders', 'action_key' => 'create', 'description' => 'Create fresh and repair orders'],
            ['code' => 'orders.edit', 'name' => 'Edit Orders', 'module_group' => 'Orders', 'action_key' => 'edit', 'description' => 'Update order details'],
            ['code' => 'orders.status', 'name' => 'Update Order Status', 'module_group' => 'Orders', 'action_key' => 'status', 'description' => 'Update or cancel orders'],
            ['code' => 'orders.followup', 'name' => 'Manage Followups', 'module_group' => 'Orders', 'action_key' => 'followup', 'description' => 'Take followups and update order pipeline'],
            ['code' => 'orders.assign', 'name' => 'Assign Karigar', 'module_group' => 'Orders', 'action_key' => 'assign', 'description' => 'Assign orders to karigars'],
            ['code' => 'orders.receive', 'name' => 'Receive Orders', 'module_group' => 'Orders', 'action_key' => 'receive', 'description' => 'Receive ornament and generate downstream docs'],
            ['code' => 'orders.documents', 'name' => 'Generate Order Documents', 'module_group' => 'Orders', 'action_key' => 'documents', 'description' => 'Generate packing list, challan and uploads'],
            ['code' => 'issuements.read', 'name' => 'View Issuements', 'module_group' => 'Issuements', 'action_key' => 'read', 'description' => 'Access combined issuement listing and voucher views'],
            ['code' => 'issuements.create', 'name' => 'Create Issuements', 'module_group' => 'Issuements', 'action_key' => 'create', 'description' => 'Create material issuements'],
            ['code' => 'issuements.edit', 'name' => 'Edit Issuements', 'module_group' => 'Issuements', 'action_key' => 'edit', 'description' => 'Edit issuement records'],
            ['code' => 'issuements.print', 'name' => 'Print Issuement Docs', 'module_group' => 'Issuements', 'action_key' => 'print', 'description' => 'Download or print issuement vouchers'],
            ['code' => 'reports.read', 'name' => 'View Reports', 'module_group' => 'Reports', 'action_key' => 'read', 'description' => 'Access report screens'],
            ['code' => 'reports.export', 'name' => 'Export Reports', 'module_group' => 'Reports', 'action_key' => 'export', 'description' => 'Export report output'],
            ['code' => 'accounts.read', 'name' => 'View Accounts', 'module_group' => 'Accounts', 'action_key' => 'read', 'description' => 'Access bills and ledgers'],
            ['code' => 'accounts.payments', 'name' => 'Update Payments', 'module_group' => 'Accounts', 'action_key' => 'payments', 'description' => 'Update purchase and labour payment status'],
            ['code' => 'masters.designs.read', 'name' => 'View Design Master', 'module_group' => 'Masters', 'action_key' => 'read', 'description' => 'Access design master'],
            ['code' => 'masters.designs.manage', 'name' => 'Manage Design Master', 'module_group' => 'Masters', 'action_key' => 'manage', 'description' => 'Create and edit designs'],
            ['code' => 'masters.karigars.read', 'name' => 'View Karigar Master', 'module_group' => 'Masters', 'action_key' => 'read', 'description' => 'Access karigar master'],
            ['code' => 'masters.karigars.manage', 'name' => 'Manage Karigar Master', 'module_group' => 'Masters', 'action_key' => 'manage', 'description' => 'Create and edit karigars'],
            ['code' => 'masters.karigars.payments', 'name' => 'Manage Karigar Payments', 'module_group' => 'Masters', 'action_key' => 'payments', 'description' => 'Post karigar payment entries'],
            ['code' => 'masters.vendors.read', 'name' => 'View Vendors', 'module_group' => 'Masters', 'action_key' => 'read', 'description' => 'Access vendor master'],
            ['code' => 'masters.vendors.manage', 'name' => 'Manage Vendors', 'module_group' => 'Masters', 'action_key' => 'manage', 'description' => 'Create and update vendors'],
            ['code' => 'organization.departments.read', 'name' => 'View Departments', 'module_group' => 'Organization', 'action_key' => 'read', 'description' => 'Access department master'],
            ['code' => 'organization.departments.manage', 'name' => 'Manage Departments', 'module_group' => 'Organization', 'action_key' => 'manage', 'description' => 'Create, edit, activate departments'],
            ['code' => 'organization.designations.read', 'name' => 'View Designations', 'module_group' => 'Organization', 'action_key' => 'read', 'description' => 'Access designation master'],
            ['code' => 'organization.designations.manage', 'name' => 'Manage Designations', 'module_group' => 'Organization', 'action_key' => 'manage', 'description' => 'Create, edit, activate designations'],
            ['code' => 'organization.employees.read', 'name' => 'View Employees', 'module_group' => 'Organization', 'action_key' => 'read', 'description' => 'Access employee master'],
            ['code' => 'organization.employees.manage', 'name' => 'Manage Employees', 'module_group' => 'Organization', 'action_key' => 'manage', 'description' => 'Create, edit, activate employees'],
            ['code' => 'organization.hierarchy.read', 'name' => 'View Hierarchy', 'module_group' => 'Organization', 'action_key' => 'read', 'description' => 'Access hierarchy management'],
            ['code' => 'organization.hierarchy.manage', 'name' => 'Manage Hierarchy', 'module_group' => 'Organization', 'action_key' => 'manage', 'description' => 'Update reporting and approval lines'],
            ['code' => 'company-settings.read', 'name' => 'View Company Settings', 'module_group' => 'Settings', 'action_key' => 'read', 'description' => 'Access company settings'],
            ['code' => 'company-settings.manage', 'name' => 'Manage Company Settings', 'module_group' => 'Settings', 'action_key' => 'manage', 'description' => 'Update company configuration'],
            ['code' => 'inventory.settings.read', 'name' => 'View Inventory Settings', 'module_group' => 'Inventory', 'action_key' => 'read', 'description' => 'Access warehouses and inventory settings'],
            ['code' => 'inventory.settings.manage', 'name' => 'Manage Inventory Settings', 'module_group' => 'Inventory', 'action_key' => 'manage', 'description' => 'Maintain warehouses, categories and products'],
            ['code' => 'diamond.inventory.read', 'name' => 'View Diamond Inventory', 'module_group' => 'Inventory', 'action_key' => 'read', 'description' => 'Access diamond inventory modules'],
            ['code' => 'diamond.inventory.manage', 'name' => 'Manage Diamond Inventory', 'module_group' => 'Inventory', 'action_key' => 'manage', 'description' => 'Create purchases, issues, returns and adjustments for diamonds'],
            ['code' => 'gold.inventory.read', 'name' => 'View Gold Inventory', 'module_group' => 'Inventory', 'action_key' => 'read', 'description' => 'Access gold inventory modules'],
            ['code' => 'gold.inventory.manage', 'name' => 'Manage Gold Inventory', 'module_group' => 'Inventory', 'action_key' => 'manage', 'description' => 'Create purchases, issues, returns and adjustments for gold'],
            ['code' => 'stone.inventory.read', 'name' => 'View Stone Inventory', 'module_group' => 'Inventory', 'action_key' => 'read', 'description' => 'Access stone inventory modules'],
            ['code' => 'stone.inventory.manage', 'name' => 'Manage Stone Inventory', 'module_group' => 'Inventory', 'action_key' => 'manage', 'description' => 'Create purchases, issues, returns and adjustments for stones'],
            ['code' => 'access.roles.read', 'name' => 'View Roles', 'module_group' => 'Access Control', 'action_key' => 'read', 'description' => 'Access role master'],
            ['code' => 'access.roles.manage', 'name' => 'Manage Roles', 'module_group' => 'Access Control', 'action_key' => 'manage', 'description' => 'Create roles and assign permissions'],
            ['code' => 'access.permissions.read', 'name' => 'View Permissions', 'module_group' => 'Access Control', 'action_key' => 'read', 'description' => 'Access permission catalog'],
            ['code' => 'access.permissions.manage', 'name' => 'Manage Permissions', 'module_group' => 'Access Control', 'action_key' => 'manage', 'description' => 'Create and edit permission definitions'],
            ['code' => 'access.users.read', 'name' => 'View User Access', 'module_group' => 'Access Control', 'action_key' => 'read', 'description' => 'Access user-role mapping'],
            ['code' => 'access.users.manage', 'name' => 'Manage User Access', 'module_group' => 'Access Control', 'action_key' => 'manage', 'description' => 'Assign roles and direct permission overrides'],
        ];
    }
}
