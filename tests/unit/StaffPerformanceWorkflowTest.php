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
        $diamondWorkflow = $this->source('Services/DiamondRequirementService.php');
        $webTasks = $this->source('Controllers/Admin/PerformanceController.php');
        $mobileTasks = $this->source('Controllers/Api/Mobile/TasksController.php');
        $mobileAdminTasks = $this->source('Controllers/Api/Mobile/AdminTasksController.php');

        $this->assertStringContainsString('f.points_enabled', $service);
        $this->assertStringContainsString('public const BASE_SCORE = 100.0', $service);
        $this->assertStringContainsString('public const TASK_ON_TIME_POINTS = 2.0', $service);
        $this->assertStringContainsString('public const TASK_LATE_POINTS = -2.0', $service);
        $this->assertStringContainsString('public const FOLLOWUP_ON_TIME_POINTS = 0.0', $service);
        $this->assertStringContainsString('public const FOLLOWUP_LATE_POINTS = -2.0', $service);
        $this->assertStringContainsString('public const DISAPPROVED_FOLLOWUP_POINTS = -2.0', $service);
        $this->assertStringContainsString('public const ORDER_ENTRY_POINTS = 0.25', $service);
        $this->assertStringContainsString('public const ISSUEMENT_POINTS = 0.25', $service);
        $this->assertStringContainsString('public const RETURN_POINTS = 0.25', $service);
        $this->assertStringContainsString('public const RECEIVING_POINTS = 0.25', $service);
        $this->assertStringContainsString('public const PURCHASE_POINTS = 0.25', $service);
        // Only manually assigned Staff Tasks are scored as tasks; every transactional mirror is excluded.
        $this->assertStringContainsString("->where('t.reference_type', null)", $service);
        $this->assertStringNotContainsString('DEDICATED_WORKFLOW_TASK_TYPES', $service);
        $this->assertStringNotContainsString("->orWhereNotIn('t.reference_type'", $service);
        $this->assertStringNotContainsString("->notLike('t.reference_type', 'inventory_%')", $service);
        $this->assertStringContainsString("'score_delta' => 0", $diamondWorkflow);
        $this->assertStringContainsString("->where('t.reference_type', null)", $webTasks);
        $this->assertStringContainsString("->where('t.reference_type', null)", $mobileTasks);
        $this->assertStringContainsString("->where('t.reference_type', null)", $mobileAdminTasks);
        $this->assertStringContainsString("'approval_pause_intervals'", $service);
        $this->assertStringNotContainsString("'followup_approval_credit_per_day'", $service);
        $this->assertStringContainsString("private const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'OWNER']", $service);
        $this->assertStringContainsString("'events_by_user'", $service);
    }

    public function testLatePenaltyStartsImmediatelyAndGrowsAfterEveryFullTwentyFourHours(): void
    {
        $this->assertSame(0, \App\Services\StaffPerformanceService::lateDayUnits(
            '2026-10-01 10:00:00',
            '2026-10-01 10:00:00'
        ));
        $this->assertSame(1, \App\Services\StaffPerformanceService::lateDayUnits(
            '2026-10-01 10:00:00',
            '2026-10-01 10:00:01'
        ));
        $this->assertSame(1, \App\Services\StaffPerformanceService::lateDayUnits(
            '2026-10-01 10:00:00',
            '2026-10-02 10:00:00'
        ));
        $this->assertSame(2, \App\Services\StaffPerformanceService::lateDayUnits(
            '2026-10-01 10:00:00',
            '2026-10-02 10:00:01'
        ));
        $this->assertSame(2.0, \App\Services\StaffPerformanceService::taskPoints(
            '2026-10-01 10:00:00',
            '2026-10-01 09:59:59'
        ));
        $this->assertSame(-2.0, \App\Services\StaffPerformanceService::taskPoints(
            '2026-10-01 10:00:00',
            '2026-10-01 10:00:01'
        ));
        $this->assertSame(-4.0, \App\Services\StaffPerformanceService::taskPoints(
            '2026-10-01 10:00:00',
            '2026-10-02 10:00:01'
        ));
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

        $this->assertSame(2.0, $summary['points_lost']);
        $this->assertSame(1, $summary['followup_delay_days']);
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

    public function testReviewedApprovalWaitingTimeIsExcludedFromLatePoints(): void
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

    public function testCurrentlyPendingApprovalDoesNotBecomeOverdue(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'pending',
            'due_at' => '2026-10-02 10:00:00',
            'created_at' => '2026-10-01 09:00:00',
            'performance_until_at' => '2026-10-01 09:00:00',
            'approval_pending' => true,
            'approval_pause_intervals' => [[
                'start_at' => '2026-10-01 09:00:00',
                'end_at' => '2026-10-15 10:00:00',
            ]],
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(0.0, $summary['points_lost']);
        $this->assertSame(0, $summary['followup_delay_days']);
        $this->assertSame(0, $summary['followup_overdue']);
        $this->assertSame(100.0, $summary['score']);
    }

    public function testTimelySubmittedFollowupGetsNoApprovalWaitingCredit(): void
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
        $this->assertSame(0.0, $summary['points_earned']);
        $this->assertSame(100.0, $summary['score']);
        $this->assertSame(0, $summary['followup_approval_credit_days']);
        $this->assertSame(0.0, $summary['followup_approval_credit_points']);
    }

    public function testPendingOrRejectedFollowupDoesNotEarnApprovalDelayCredit(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');

        foreach (['pending', 'rejected'] as $approvalStatus) {
            $summary = $metrics->invoke($service, [], [[
                'status' => 'completed_on_time',
                'completed_followup_approval_status' => $approvalStatus,
                'completed_followup_points_enabled' => 1,
                'due_at' => '2026-10-01 10:00:00',
                'created_at' => '2026-09-30 10:00:00',
                'completed_at' => '2026-10-01 09:00:00',
                'approval_credit_intervals' => [[
                    'start_at' => '2026-10-01 09:00:00',
                    'end_at' => '2026-10-04 09:00:00',
                ]],
            ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

            $this->assertSame(0.0, $summary['points_earned']);
            $this->assertSame(0, $summary['followup_approval_credit_days']);
            $this->assertSame(0.0, $summary['followup_approval_credit_points']);
        }
    }

    public function testNoPointApprovalDoesNotEarnApprovalDelayCredit(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [[
            'status' => 'completed_on_time',
            'completed_followup_approval_status' => 'approved',
            'completed_followup_points_enabled' => 0,
            'due_at' => '2026-10-01 10:00:00',
            'created_at' => '2026-09-30 10:00:00',
            'completed_at' => '2026-10-01 09:00:00',
            'approval_credit_intervals' => [[
                'start_at' => '2026-10-01 09:00:00',
                'end_at' => '2026-10-04 09:00:00',
            ]],
        ]], [], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(0.0, $summary['points_earned']);
        $this->assertSame(0, $summary['followup_approval_credit_days']);
        $this->assertSame(0.0, $summary['followup_approval_credit_points']);
    }

    public function testPerformanceAnalysisUsesOnlyConfiguredPointEvents(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [[
            'status' => 'completed_on_time', 'is_done' => 1,
            'scheduled_at' => '2026-10-10 10:00:00',
            'completed_at' => '2026-10-10 09:00:00',
        ]], [[
            'status' => 'completed_on_time', 'due_at' => '2026-10-10 10:00:00',
            'completed_at' => '2026-10-10 09:00:00', 'score_delta' => 0,
        ]], [], [
            ['type' => 'Approved follow-up', 'score_delta' => 2],
            ['type' => 'Order Entry', 'score_delta' => .25],
            ['type' => 'Issuement', 'score_delta' => .25],
            ['type' => 'Return', 'score_delta' => .25],
            ['type' => 'Receiving', 'score_delta' => .25],
            ['type' => 'Purchase', 'score_delta' => .25],
        ], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(1, $summary['followup_submission_count']);
        $this->assertSame(0, $summary['meaningful_updates']);
        $this->assertSame(0, $summary['duplicate_followups']);
        $this->assertSame(0, $summary['missing_image_followups']);
        $this->assertSame(100.0, $summary['on_time_rate']);
        $this->assertSame('Excellent', $summary['performance_band']);
        $this->assertFalse($summary['needs_attention']);
        $this->assertTrue($summary['has_scored_activity']);
        $this->assertSame(2, $summary['due_action_count']);
        $this->assertSame(2.0, $summary['point_earned_breakdown']['Completed tasks']);
        $this->assertSame(2.0, $summary['point_earned_breakdown']['Approved follow-up']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Order Entry']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Issuement']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Return']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Receiving']);
        $this->assertSame(.25, $summary['point_earned_breakdown']['Purchase']);
        $this->assertSame(105.3, $summary['score']);
    }

    public function testDisapprovedFollowupDeductsTwoPoints(): void
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
        $this->assertSame(1, $summary['followup_on_time']);
        $this->assertSame('Approval rejected', $summary['performance_band']);
    }

    public function testApprovedWithoutPointsIsCountedSeparatelyAndAddsZero(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [], [], [[
            'type' => 'Approved follow-up',
            'score_delta' => 0,
        ]], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(0, $summary['approved_followups']);
        $this->assertSame(1, $summary['no_point_followups']);
        $this->assertSame(1, $summary['followup_submission_count']);
        $this->assertSame(0.0, $summary['points_earned']);
        $this->assertSame(100.0, $summary['score']);
    }

    public function testCompletionRatingKeepsExistingFivePointStepFromThreeStars(): void
    {
        $service = new \App\Services\StaffPerformanceService();
        $metrics = new \ReflectionMethod($service, 'metrics');
        $summary = $metrics->invoke($service, [], [], [
            ['completion_rating' => 5],
            ['completion_rating' => 2],
        ], [], '2026-10-01 00:00:00', '2026-11-01 00:00:00');

        $this->assertSame(10.0, $summary['points_earned']);
        $this->assertSame(5.0, $summary['points_lost']);
        $this->assertSame(105.0, $summary['score']);
        $this->assertSame(2, $summary['rating_count']);
        $this->assertSame(3.5, $summary['rating_average']);
        $this->assertSame(10.0, $summary['point_earned_breakdown']['Completion ratings']);
        $this->assertSame(5.0, $summary['point_loss_breakdown']['Low completion ratings']);
    }

    public function testPerformanceViewShowsManagementQualityAndRiskAnalysis(): void
    {
        $view = $this->source('Views/admin/performance/dashboard.php');

        $this->assertStringContainsString('Follow-up Decisions', $view);
        $this->assertStringContainsString('Management Focus', $view);
        $this->assertStringContainsString('Employee Performance Analysis', $view);
        $this->assertStringContainsString("analysis_summary", $view);
        $this->assertStringContainsString('staff-points-chart', $view);
        $this->assertStringContainsString('loss-reasons-chart', $view);
        $this->assertStringContainsString('earned-reasons-chart', $view);
        $this->assertStringContainsString('team-discipline-chart', $view);
        $this->assertStringContainsString('Follow-up Scoring Audit', $view);
        $this->assertStringContainsString('id="followup-audit-table"', $view);
        $this->assertStringContainsString('apexcharts.min.js', $view);
        $this->assertStringNotContainsString('Evidence Compliance', $view);
        $this->assertStringNotContainsString('Duplicate penalty', $view);
        $this->assertStringNotContainsString('Missing image', $view);
        $this->assertStringNotContainsString('activity-row', $view);
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
        $this->assertStringContainsString("'parent_order_id' => 'permit_empty|integer'", $controller);
        $this->assertStringContainsString("'parent_order_id' => \$parentOrder['id'] ?? null", $controller);
        $this->assertStringContainsString('name="parent_order_id"', $edit);
        $this->assertStringContainsString('follow-up and approval chain', $edit);
        $this->assertStringContainsString('OrderFollowupService', $controller);
        $this->assertStringContainsString('$isChildOrder = (int) ($root[\'id\'] ?? 0) !== $orderId', $followupService);
        $this->assertStringContainsString('$payload[\'order_id\'] = (int) $created[\'order_id\']', $followupService);
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
