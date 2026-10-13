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
        $this->assertStringContainsString('public const FOLLOWUP_LATE_POINTS = -2.0', $service);
        $this->assertStringContainsString("\$request['status'] === 'approved'", $service);
        $this->assertStringContainsString("'followup_approval_credit_per_day' => abs(self::FOLLOWUP_LATE_POINTS)", $service);
        $this->assertStringContainsString("private const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'OWNER']", $service);
        $this->assertStringContainsString("'events_by_user'", $service);
    }

    public function testFollowupDelayCostsTwoPointsPerDayUsingSubmissionTimeNotApprovalTime(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [
            [
                'status' => 'completed_late',
                'due_at' => '2026-10-01 10:00:00',
                'completed_at' => '2026-10-03 10:00:00',
                'approved_at' => '2026-10-12 10:00:00',
                'score_delta' => 0,
            ],
            [
                'status' => 'pending',
                'due_at' => '2026-10-01 10:00:00',
                'performance_until_at' => '2026-10-04 10:00:00',
                'approval_review_delayed_until' => '2026-10-12 10:00:00',
            ],
        ], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(10.0, $summary['points_lost']);
        $this->assertSame(90.0, $summary['score']);
        $this->assertSame(2, $summary['followup_late']);
        $this->assertSame(0, $summary['followup_overdue']);
        $this->assertSame(5, $summary['followup_delay_days']);
        $this->assertSame(0.0, $summary['points_earned']);
    }

    public function testLateFollowupDaysAreCountedInTheMonthTheyOccur(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'completed_late',
            'due_at' => '2026-09-30 10:00:00',
            'completed_at' => '2026-10-02 10:00:00',
            'score_delta' => 0,
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(4.0, $summary['points_lost']);
        $this->assertSame(2, $summary['followup_delay_days']);
    }

    public function testApprovalDelayBeforeTheNextFollowupExistsDoesNotAddDelayPoints(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'pending',
            'due_at' => '2026-10-08 10:00:00',
            'created_at' => '2026-10-10 10:00:00',
            'performance_until_at' => '2026-10-12 10:00:00',
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(4.0, $summary['points_lost']);
        $this->assertSame(2, $summary['followup_delay_days']);
    }

    public function testRejectedFollowupDoesNotEarnApprovalDelayCredit(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'pending',
            'due_at' => '2026-10-01 10:00:00',
            'created_at' => '2026-09-30 10:00:00',
            'performance_until_at' => '2026-10-09 10:00:00',
            'approval_pause_intervals' => [[
                'start_at' => '2026-10-02 10:00:00',
                'end_at' => '2026-10-07 10:00:00',
            ]],
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(6.0, $summary['points_lost']);
        $this->assertSame(3, $summary['followup_delay_days']);
        $this->assertSame(0.0, $summary['points_earned']);
        $this->assertSame(0, $summary['followup_approval_credit_days']);
    }

    public function testTimelySubmittedFollowupGetsTwoPointsPerFullDayUntilApproved(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'completed_on_time',
            'due_at' => '2026-10-01 10:00:00',
            'created_at' => '2026-09-30 10:00:00',
            'completed_at' => '2026-10-01 09:00:00',
            'completed_followup_id' => 45,
            'approval_credit_intervals' => [[
                'start_at' => '2026-10-01 09:00:00',
                'end_at' => '2026-10-04 09:00:00',
            ]],
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(0.0, $summary['points_lost']);
        $this->assertSame(6.0, $summary['points_earned']);
        $this->assertSame(106.0, $summary['score']);
        $this->assertSame(3, $summary['followup_approval_credit_days']);
        $this->assertSame(6.0, $summary['followup_approval_credit_points']);
    }

    public function testPerformanceAnalysisIncludesFollowupQualityAndScheduleDiscipline(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [[
            'status' => 'completed_on_time', 'is_done' => 1, 'score_delta' => 2,
        ]], [[
            'status' => 'completed_on_time', 'due_at' => '2026-10-10 10:00:00',
            'completed_at' => '2026-10-10 09:00:00', 'score_delta' => 0,
        ]], [], [
            ['type' => 'Approved follow-up', 'score_delta' => 2],
            ['type' => 'Meaningful update', 'score_delta' => .25],
            ['type' => 'Duplicate follow-up', 'score_delta' => -1],
            ['type' => 'Image evidence missing', 'score_delta' => -.5],
        ], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(2, $summary['followup_submission_count']);
        $this->assertSame(1, $summary['meaningful_updates']);
        $this->assertSame(1, $summary['duplicate_followups']);
        $this->assertSame(1, $summary['missing_image_followups']);
        $this->assertSame(50.0, $summary['evidence_compliance_rate']);
        $this->assertSame(50.0, $summary['unique_update_rate']);
        $this->assertSame(100.0, $summary['on_time_rate']);
        $this->assertSame('Needs review', $summary['performance_band']);
        $this->assertTrue($summary['needs_attention']);
        $this->assertTrue($summary['has_scored_activity']);
        $this->assertSame(2, $summary['due_action_count']);
        $this->assertSame(2.0, $summary['point_earned_breakdown']['Completed tasks']);
        $this->assertSame(2.0, $summary['point_earned_breakdown']['Approved follow-up']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Meaningful update']);
        $this->assertSame(1.0, $summary['point_loss_breakdown']['Duplicate follow-up']);
        $this->assertSame(.5, $summary['point_loss_breakdown']['Image evidence missing']);
    }

    public function testPendingFollowupHasNoScoreAndDisapprovedFollowupLosesTwoPoints(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'completed_on_time',
            'completed_followup_approval_status' => 'rejected',
            'due_at' => '2026-10-10 10:00:00',
            'completed_at' => '2026-10-10 09:00:00',
        ]], [], [[
            'type' => 'Disapproved follow-up', 'score_delta' => -2,
        ]], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(2.0, $summary['points_lost']);
        $this->assertSame(98.0, $summary['score']);
        $this->assertSame(1, $summary['disapproved_followups']);
        $this->assertSame(0, $summary['followup_on_time']);
        $this->assertSame('Approval rejected', $summary['performance_band']);
    }

    public function testPerformanceViewShowsManagementQualityAndRiskAnalysis(): void
    {
        $view = $this->source('Views/admin/performance/dashboard.php');

        $this->assertStringContainsString('Follow-up Quality', $view);
        $this->assertStringContainsString('Management Focus', $view);
        $this->assertStringContainsString('Evidence Compliance', $view);
        $this->assertStringContainsString('Employee Performance Analysis', $view);
        $this->assertStringContainsString("analysis_summary", $view);
        $this->assertStringContainsString('staff-points-chart', $view);
        $this->assertStringContainsString('loss-reasons-chart', $view);
        $this->assertStringContainsString('earned-reasons-chart', $view);
        $this->assertStringContainsString('team-discipline-chart', $view);
        $this->assertStringContainsString('Follow-up Evidence &amp; Scoring Audit', $view);
        $this->assertStringContainsString('id="followup-audit-table"', $view);
        $this->assertStringContainsString('apexcharts.min.js', $view);
        $this->assertStringNotContainsString('activity-row', $view);
    }

    public function testDuplicateAuditEventKeepsFollowupAndMatchedIdsForAdminReview(): void
    {
        $events = new \ReflectionMethod(new \App\Services\StaffPerformanceService(), 'events');
        $rows = $events->invoke(new \App\Services\StaffPerformanceService(), [], [], [], [[
            'type' => 'Duplicate follow-up', 'title' => 'ORD-1', 'reference' => 'Matched follow-up #4',
            'occurred_at' => '2026-10-13 10:00:00', 'score_delta' => -1, 'order_id' => 9,
            'followup_id' => 12, 'matched_followup_id' => 4,
        ]], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(12, $rows[0]['followup_id']);
        $this->assertSame(4, $rows[0]['matched_followup_id']);
    }

    public function testFollowupAuditEventKeepsValidEvidenceLink(): void
    {
        $events = new \ReflectionMethod(new \App\Services\StaffPerformanceService(), 'events');
        $rows = $events->invoke(new \App\Services\StaffPerformanceService(), [], [], [], [[
            'type' => 'Meaningful update', 'title' => 'ORD-2', 'reference' => 'Unique update',
            'occurred_at' => '2026-10-13 11:00:00', 'score_delta' => .25, 'order_id' => 10,
            'followup_id' => 13, 'proof_url' => 'http://localhost/uploads/followups/evidence.jpg',
        ]], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame('http://localhost/uploads/followups/evidence.jpg', $rows[0]['proof_url']);
    }

    public function testFollowerIsAssignedWithKarigarAndEveryDueFollowupIsTracked(): void
    {
        $controller = $this->source('Controllers/Admin/OrderController.php');
        $mobileController = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $followupService = $this->source('Services/OrderFollowupService.php');
        $create = $this->source('Views/admin/orders/create.php');
        $edit = $this->source('Views/admin/orders/edit.php');
        $orderList = $this->source('Views/admin/orders/index.php');
        $followupList = $this->source('Views/admin/orders/followups.php');
        $orderDetail = $this->source('Views/admin/orders/show.php');
        $routes = $this->source('Config/Routes.php');
        $mobileOrderDetail = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/order_detail_screen.dart');
        $mobileApi = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/services/mobile_api_service.dart');

        $this->assertStringContainsString("getPost('followup_assigned_to')", $controller);
        $this->assertStringContainsString("getPost('followup_due_at')", $controller);
        $this->assertStringContainsString('OrderFamilyService', $controller);
        $this->assertStringContainsString('syncFollower(', $controller);
        $this->assertStringContainsString('OrderFollowupService', $controller);
        $this->assertStringContainsString('completeOrderFollowup(', $followupService);
        $this->assertStringContainsString('Only the assigned order follower can submit this follow-up.', $mobileController);
        $this->assertStringContainsString('Only the assigned order follower can submit this follow-up.', $controller);
        $this->assertStringContainsString('next_followup_date is required while the order remains open.', $mobileController);
        $this->assertStringContainsString('public function updateFollower(int $id)', $controller);
        $this->assertStringContainsString('public function updateFollower(int $id)', $mobileController);
        $this->assertStringContainsString("orders/(:num)/follower", $routes);
        $this->assertStringContainsString("'can_add_followup'", $mobileController);
        $this->assertStringContainsString("'can_change_follower'", $mobileController);
        $this->assertStringNotContainsString('name="followup_assigned_to"', $create);
        $this->assertStringNotContainsString('name="followup_due_at"', $create);
        $this->assertStringNotContainsString('name="followup_assigned_to"', $edit);
        $this->assertStringContainsString('id="assignKarigarModal"', $orderList);
        $this->assertStringContainsString('name="followup_assigned_to"', $orderList);
        $this->assertStringContainsString('name="followup_due_at"', $orderList);
        $this->assertStringContainsString('Only the assigned follower can take this follow-up', $followupList);
        $this->assertStringContainsString('id="changeFollowerModal"', $followupList);
        $this->assertStringContainsString('id="changeFollowerModal"', $orderDetail);
        $this->assertStringContainsString('updateOrderFollower', $mobileApi);
        $this->assertStringContainsString('_canTakeOrderFollowup', $mobileOrderDetail);
        $this->assertStringContainsString('Change Follower', $mobileOrderDetail);
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
