<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondRequirementWorkflowTest extends CIUnitTestCase
{
    public function testMigrationCreatesRequirementApprovalAndBagAssignmentSchema(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-08-000086_CreateDiamondRequirementWorkflow.php');

        $this->assertStringContainsString("createTable('diamond_requirements'", $migration);
        $this->assertStringContainsString("'requested_by'", $migration);
        $this->assertStringContainsString("'approved_by'", $migration);
        $this->assertStringContainsString("'assigned_to'", $migration);
        $this->assertStringContainsString("'preparation_due_at'", $migration);
        $this->assertStringContainsString("'ready_at'", $migration);
        $this->assertStringContainsString("'requirement_id'", $migration);
    }

    public function testFollowerRaiseAdminAssignmentAndAssigneePreparationAreEnforced(): void
    {
        $service = $this->source('Services/DiamondRequirementService.php');

        $this->assertStringContainsString('followup_assigned_to', $service);
        $this->assertStringContainsString('Only the assigned order follower can raise', $service);
        $this->assertStringContainsString('function approveAndAssign', $service);
        $this->assertStringContainsString("'status' => 'assigned'", $service);
        $this->assertStringContainsString('This bag preparation is assigned to another staff member.', $service);
        $this->assertStringContainsString("'status' => 'preparing'", $service);
        $this->assertStringContainsString("'status' => 'bag_ready'", $service);
        $this->assertStringContainsString("'shape_master_id'", $service);
        $this->assertStringContainsString("'size_master_id'", $service);
        $this->assertStringContainsString('whole PCS and positive CTS are required', $service);
    }

    public function testRequirementBagStaysLockedToOrderThroughIssueReturnAndReceipt(): void
    {
        $trace = $this->source('Services/DiamondBagTraceService.php');
        $orders = $this->source('Controllers/Admin/OrderController.php');
        $mobileTransactions = $this->source('Controllers/Api/Mobile/TransactionsController.php');
        $bagController = $this->source('Controllers/Admin/DiamondBagController.php');

        $this->assertStringContainsString('assertRequirementAllocation', $trace);
        $this->assertStringContainsString('This requirement bag can only be issued against its linked order.', $trace);
        $this->assertStringContainsString('receiptOptions(int $karigarId, int $orderId = 0)', $trace);
        $this->assertStringContainsString("->where('il.allocation_order_id', \$orderId)", $trace);
        $this->assertStringContainsString('Selected diamond bag was issued for another order.', $trace);
        $this->assertStringContainsString('receiptOptions($karigarId, $orderId)', $orders);
        $this->assertStringContainsString('$requiresExactBag', $orders);
        $this->assertStringContainsString("table('diamond_requirements')->where('order_id', \$orderId)", $orders);
        $this->assertStringContainsString('parseDiamondReturnLines', $mobileTransactions);
        $this->assertStringContainsString("'issue_line_id' => \$issueLineId", $mobileTransactions);
        $this->assertStringContainsString('approved requirement bag cannot be deleted', $bagController);
    }

    public function testWebMobileRoutesScreensAndPushDeepLinksAreConnected(): void
    {
        $routes = $this->source('Config/Routes.php');
        $orderView = $this->source('Views/admin/orders/show.php');
        $mobileController = $this->source('Controllers/Api/Mobile/DiamondRequirementsController.php');
        $mobileScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/diamond_requirements_screen.dart'
        );
        $notifications = $this->source('Services/MobileNotificationEventService.php');
        $push = $this->source('Services/MobilePushService.php');
        $webBridge = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/web/aabhushan_pwa_bridge.js');

        $this->assertStringContainsString('orders/(:num)/diamond-requirements', $routes);
        $this->assertStringContainsString('diamond-requirements/(:num)/prepare', $routes);
        $this->assertStringContainsString('Raise Diamond Requirement', $orderView);
        $this->assertStringContainsString('approveAndAssign', $mobileController . $this->source('Controllers/Admin/DiamondRequirementController.php'));
        $this->assertStringContainsString('Complete & Mark Bag Ready', $mobileScreen);
        $this->assertStringContainsString('Add Diamond Size', $mobileScreen);
        $this->assertStringContainsString("'screen' => 'diamond_requirements'", $notifications);
        $this->assertStringContainsString('notifyDiamondRequirementAssigned', $notifications);
        $this->assertStringContainsString('notifyDiamondBagReady', $notifications);
        $this->assertStringContainsString("\$data['requirement_id']", $push);
        $this->assertStringContainsString('"requirement_id"', $webBridge);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
