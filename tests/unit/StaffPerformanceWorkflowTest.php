<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class StaffPerformanceWorkflowTest extends CIUnitTestCase
{
    public function testMigrationCreatesDueDateBasedPerformanceWorkflow(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-02-000076_CreateStaffPerformanceWorkflow.php');

        $this->assertStringContainsString("'followup_assigned_to'", $migration);
        $this->assertStringContainsString("'followup_due_at'", $migration);
        $this->assertStringContainsString("createTable('order_followup_schedules'", $migration);
        $this->assertStringContainsString("'proof_path'", $migration);
        $this->assertStringContainsString("'counts_for_performance'", $migration);
        $this->assertStringContainsString("'performance.tasks.manage'", $migration);
    }

    public function testScoreIsTransparentAndExcludesAdminRoles(): void
    {
        $service = $this->source('Services/StaffPerformanceService.php');

        $this->assertStringContainsString('public const BASE_SCORE = 100.0', $service);
        $this->assertStringContainsString('public const TASK_ON_TIME_POINTS = 2.0', $service);
        $this->assertStringContainsString('public const TASK_LATE_POINTS = -2.0', $service);
        $this->assertStringContainsString('public const FOLLOWUP_ON_TIME_POINTS = 1.0', $service);
        $this->assertStringContainsString('public const FOLLOWUP_LATE_POINTS = -1.0', $service);
        $this->assertStringContainsString("private const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'OWNER']", $service);
        $this->assertStringContainsString("'events_by_user'", $service);
    }

    public function testOrdersRequireFollowerAndTrackEveryDueFollowup(): void
    {
        $controller = $this->source('Controllers/Admin/OrderController.php');
        $mobileController = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $create = $this->source('Views/admin/orders/create.php');
        $edit = $this->source('Views/admin/orders/edit.php');

        $this->assertStringContainsString("'followup_assigned_to' => 'required|integer|greater_than[0]'", $controller);
        $this->assertStringContainsString("'followup_due_at' => 'required|valid_date'", $controller);
        $this->assertStringContainsString('syncOrderAssignment(', $controller);
        $this->assertStringContainsString('completeOrderFollowup(', $controller);
        $this->assertStringContainsString('Only the assigned order follower can submit this follow-up.', $mobileController);
        $this->assertStringContainsString('next_followup_date is required while the order remains open.', $mobileController);
        $this->assertStringContainsString('name="followup_assigned_to"', $create);
        $this->assertStringContainsString('name="followup_due_at"', $create);
        $this->assertStringContainsString('name="followup_assigned_to"', $edit);
    }

    public function testAdminTasksAndMobileProofReplaceLegacyKpiScreens(): void
    {
        $routes = $this->source('Config/Routes.php');
        $taskController = $this->source('Controllers/Api/Mobile/TasksController.php');
        $login = $this->source('Views/admin/auth/login.php');

        $this->assertStringContainsString('performance/tasks', $routes);
        $this->assertStringContainsString('tasks/(:num)/complete', $routes);
        $this->assertStringContainsString('Completion proof image is required.', $taskController);
        $this->assertStringNotContainsString('performance/kpis', $routes);
        $this->assertStringNotContainsString('performance/targets', $routes);
        $this->assertStringNotContainsString('performance/incentives', $routes);
        $this->assertStringNotContainsString('admin/register', $routes);
        $this->assertStringNotContainsString('>Register<', $login);
        $this->assertFileDoesNotExist(APPPATH . 'Views/admin/auth/register.php');
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
