<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedDemoSalesExecutive extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('admin_users') || ! $this->db->tableExists('employees') || ! $this->db->tableExists('roles')) {
            return;
        }

        $departmentId = $this->ensureDepartment();
        $designationId = $this->ensureDesignation($departmentId);
        $roleId = $this->ensureRole();
        $userId = $this->ensureAdminUser();
        $this->ensureEmployee($userId, $departmentId, $designationId);
        $this->ensureUserRole($userId, $roleId);
        $this->grantRolePermissions($roleId);
    }

    public function down()
    {
    }

    private function ensureDepartment(): int
    {
        $row = $this->db->table('departments')->where('department_code', 'SALES')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('departments')->insert([
            'department_code' => 'SALES',
            'name' => 'Sales',
            'sort_order' => 20,
            'is_active' => 1,
            'notes' => 'Retail showroom and sales staff',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureDesignation(int $departmentId): int
    {
        $row = $this->db->table('designations')->where('designation_code', 'SALES_EXEC')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('designations')->insert([
            'department_id' => $departmentId,
            'designation_code' => 'SALES_EXEC',
            'name' => 'Sales Executive',
            'level_no' => 2,
            'can_manage_team' => 0,
            'is_active' => 1,
            'description' => 'Retail showroom sales executive',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureRole(): int
    {
        $row = $this->db->table('roles')->where('role_code', 'SALES_EXECUTIVE')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('roles')->insert([
            'role_code' => 'SALES_EXECUTIVE',
            'name' => 'Sales Executive',
            'description' => 'Retail showroom sales user with showroom billing and KPI access',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureAdminUser(): int
    {
        $row = $this->db->table('admin_users')->where('email', 'salesexec@demo.com')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('admin_users')->insert([
            'name' => 'Demo Sales Executive',
            'email' => 'salesexec@demo.com',
            'password_hash' => password_hash('Sales@123', PASSWORD_DEFAULT),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureEmployee(int $userId, int $departmentId, int $designationId): void
    {
        $row = $this->db->table('employees')->where('admin_user_id', $userId)->get()->getRowArray();
        $data = [
            'employee_code' => 'EMP-SALES-DEMO',
            'admin_user_id' => $userId,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'full_name' => 'Demo Sales Executive',
            'mobile' => '9000000010',
            'email' => 'salesexec@demo.com',
            'work_location' => 'Retail Showroom',
            'joining_date' => date('Y-m-d'),
            'notes' => 'Demo showroom sales user',
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($row) {
            $this->db->table('employees')->where('id', $row['id'])->update($data);
            return;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('employees')->insert($data);
    }

    private function ensureUserRole(int $userId, int $roleId): void
    {
        $exists = $this->db->table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        $this->db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function grantRolePermissions(int $roleId): void
    {
        if (! $this->db->tableExists('permissions') || ! $this->db->tableExists('role_permissions')) {
            return;
        }

        $codes = [
            'dashboard.read',
            'customers.read',
            'customers.create',
            'orders.read',
            'orders.followup',
            'showroom.stock.read',
            'showroom.sales.read',
            'showroom.sales.manage',
            'performance.dashboard.read',
        ];

        $permissions = $this->db->table('permissions')->select('id')->whereIn('code', $codes)->get()->getResultArray();
        foreach ($permissions as $permission) {
            $permissionId = (int) ($permission['id'] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }
            $exists = $this->db->table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
