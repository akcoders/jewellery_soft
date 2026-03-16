<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePerformanceKpiTables extends Migration
{
    public function up()
    {
        $this->createKpisTable();
        $this->createTargetsTable();
        $this->createAchievementsTable();
        $this->createIncentiveRulesTable();
        $this->seedPerformancePermissions();
        $this->seedDefaultKpis();
    }

    public function down()
    {
        foreach (['staff_incentive_rules', 'staff_kpi_achievements', 'staff_kpi_targets', 'staff_kpis'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }

    private function createKpisTable(): void
    {
        if ($this->db->tableExists('staff_kpis')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kpi_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'module_group' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'metric_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'period_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Monthly'],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('kpi_code');
        $this->forge->addKey('metric_key');
        $this->forge->addKey('is_active');
        $this->forge->createTable('staff_kpis', true);
    }

    private function createTargetsTable(): void
    {
        if ($this->db->tableExists('staff_kpi_targets')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kpi_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'target_year' => ['type' => 'INT', 'constraint' => 4],
            'target_month' => ['type' => 'INT', 'constraint' => 2, 'null' => true],
            'period_label' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'target_value' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'weightage' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 100],
            'assigned_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('kpi_id');
        $this->forge->addKey(['target_year', 'target_month']);
        $this->forge->createTable('staff_kpi_targets', true);
    }

    private function createAchievementsTable(): void
    {
        if ($this->db->tableExists('staff_kpi_achievements')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kpi_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'target_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'target_year' => ['type' => 'INT', 'constraint' => 4],
            'target_month' => ['type' => 'INT', 'constraint' => 2, 'null' => true],
            'achieved_value' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'achievement_percent' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'incentive_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'source_key' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'calculated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('kpi_id');
        $this->forge->addKey('target_id');
        $this->forge->addKey(['target_year', 'target_month']);
        $this->forge->createTable('staff_kpi_achievements', true);
    }

    private function createIncentiveRulesTable(): void
    {
        if ($this->db->tableExists('staff_incentive_rules')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'rule_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'rule_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'designation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'kpi_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'min_percent' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'max_percent' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'incentive_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'flat'],
            'incentive_value' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('rule_code');
        $this->forge->addKey('designation_id');
        $this->forge->addKey('kpi_id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('staff_incentive_rules', true);
    }

    private function seedPerformancePermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $permissions = [
            ['code' => 'performance.dashboard.read', 'name' => 'View KPI Dashboard', 'module_group' => 'Performance', 'action_key' => 'read', 'description' => 'Access staff KPI and incentive dashboard', 'sort_order' => 520],
            ['code' => 'performance.kpis.read', 'name' => 'View KPI Master', 'module_group' => 'Performance', 'action_key' => 'read', 'description' => 'Access KPI master definitions', 'sort_order' => 521],
            ['code' => 'performance.kpis.manage', 'name' => 'Manage KPI Master', 'module_group' => 'Performance', 'action_key' => 'manage', 'description' => 'Create and edit KPI master definitions', 'sort_order' => 522],
            ['code' => 'performance.targets.read', 'name' => 'View KPI Targets', 'module_group' => 'Performance', 'action_key' => 'read', 'description' => 'Access KPI targets for staff', 'sort_order' => 523],
            ['code' => 'performance.targets.manage', 'name' => 'Manage KPI Targets', 'module_group' => 'Performance', 'action_key' => 'manage', 'description' => 'Assign and update KPI targets', 'sort_order' => 524],
            ['code' => 'performance.incentives.read', 'name' => 'View Incentive Rules', 'module_group' => 'Performance', 'action_key' => 'read', 'description' => 'Access incentive rule setup', 'sort_order' => 525],
            ['code' => 'performance.incentives.manage', 'name' => 'Manage Incentive Rules', 'module_group' => 'Performance', 'action_key' => 'manage', 'description' => 'Create and update incentive rules', 'sort_order' => 526],
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->table('permissions')->where('code', $permission['code'])->get()->getRowArray();
            $data = $permission + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
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
            $exists = $this->db->table('role_permissions')->where('role_id', (int) $superRole['id'])->where('permission_id', $permissionId)->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $superRole['id'],
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function seedDefaultKpis(): void
    {
        if (! $this->db->tableExists('staff_kpis')) {
            return;
        }

        $rows = [
            ['kpi_code' => 'SHOWROOM_SALES_AMOUNT', 'name' => 'Showroom Sales Amount', 'module_group' => 'Retail Showroom', 'metric_key' => 'showroom_sales_amount', 'unit' => 'INR', 'period_type' => 'Monthly', 'description' => 'Total billed showroom sales amount handled by the employee.'],
            ['kpi_code' => 'SHOWROOM_SALES_COUNT', 'name' => 'Showroom Sales Count', 'module_group' => 'Retail Showroom', 'metric_key' => 'showroom_sales_count', 'unit' => 'Bills', 'period_type' => 'Monthly', 'description' => 'Total showroom sale bills generated by the employee.'],
            ['kpi_code' => 'SHOWROOM_ITEMS_SOLD', 'name' => 'Showroom Items Sold', 'module_group' => 'Retail Showroom', 'metric_key' => 'showroom_items_sold', 'unit' => 'Items', 'period_type' => 'Monthly', 'description' => 'Total FG items sold by the employee.'],
        ];

        foreach ($rows as $row) {
            $existing = $this->db->table('staff_kpis')->where('kpi_code', $row['kpi_code'])->get()->getRowArray();
            $data = $row + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->table('staff_kpis')->where('id', $existing['id'])->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('staff_kpis')->insert($data);
            }
        }
    }
}
