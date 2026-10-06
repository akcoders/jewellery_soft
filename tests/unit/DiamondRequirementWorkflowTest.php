<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondRequirementWorkflowTest extends CIUnitTestCase
{
    public function testMigrationCreatesRequirementApprovalAndBagAssignmentSchema(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-08-000086_CreateDiamondRequirementWorkflow.php');
        $taskMigration = $this->source('Database/Migrations/2026-09-29-000093_LinkDiamondRequirementTasks.php');

        $this->assertStringContainsString("createTable('diamond_requirements'", $migration);
        $this->assertStringContainsString("'requested_by'", $migration);
        $this->assertStringContainsString("'approved_by'", $migration);
        $this->assertStringContainsString("'assigned_to'", $migration);
        $this->assertStringContainsString("'preparation_due_at'", $migration);
        $this->assertStringContainsString("'ready_at'", $migration);
        $this->assertStringContainsString("'requirement_id'", $migration);
        $this->assertStringContainsString("'reference_type'", $taskMigration);
        $this->assertStringContainsString("'reference_id'", $taskMigration);
        $this->assertStringContainsString('backfillAssignedRequirementTasks', $taskMigration);
    }

    public function testDirectBagCreationSupportsDiamondAndJadauOrders(): void
    {
        $service = $this->source('Services/DiamondBagService.php');

        $this->assertStringContainsString('followup_assigned_to', $service);
        $this->assertStringContainsString("str_contains(\$category, 'diamond')", $service);
        $this->assertStringContainsString("str_contains(\$category, 'jadau')", $service);
        $this->assertStringContainsString('function canCreateForOrder', $service);
        $this->assertStringContainsString('function create(', $service);
        $this->assertStringContainsString("'order_id' => \$orderId", $service);
        $this->assertStringContainsString("'requirement_id' => null", $service);
        $this->assertStringContainsString("'status' => 'fulfilled'", $service);
        $this->assertStringContainsString("'order_diamond_bag'", $service);
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
        $this->assertStringContainsString('This diamond bag can only be issued against its linked order.', $trace);
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
        $bagController = $this->source('Controllers/Api/Mobile/DiamondBagsController.php');
        $bagCreateScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/diamond_bag_create_screen.dart'
        );
        $bagsScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/diamond_bags_screen.dart'
        );
        $orderScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/order_detail_screen.dart'
        );
        $taskScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/task_scheduler_screen.dart'
        );
        $notifications = $this->source('Services/MobileNotificationEventService.php');
        $push = $this->source('Services/MobilePushService.php');
        $webBridge = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/web/aabhushan_pwa_bridge.js');

        $this->assertStringContainsString('orders/(:num)/diamond-requirements', $routes);
        $this->assertStringContainsString('diamond-requirements/(:num)/prepare', $routes);
        $this->assertStringContainsString("get('diamond-bags'", $routes);
        $this->assertStringContainsString("get('diamond-bags/create'", $routes);
        $this->assertStringContainsString("post('diamond-bags'", $routes);
        $this->assertStringContainsString("get('diamond-bags/(:num)'", $routes);
        $this->assertStringNotContainsString('Raise Diamond Requirement', $orderView);
        $this->assertStringContainsString('Create Diamond Bag', $orderView);
        $this->assertStringContainsString('Diamond requirement creation has been retired', $mobileController);
        $this->assertStringContainsString('approveAndAssign', $mobileController . $this->source('Controllers/Admin/DiamondRequirementController.php'));
        $this->assertStringContainsString('Create Diamond Bag', $bagCreateScreen);
        $this->assertStringContainsString('Diamond / Jadau Order', $bagCreateScreen);
        $this->assertStringContainsString('Chalni Size', $bagCreateScreen);
        $this->assertStringContainsString("return \$this->requirements->forAdmin()", str_replace('$items = ', 'return ', $mobileController));
        $this->assertStringContainsString(': (object) []', $mobileController);
        $this->assertStringContainsString('class DiamondBagsController', $bagController);
        $this->assertStringNotContainsString("text: 'Creation Requests'", $bagsScreen);
        $this->assertStringContainsString('Create Diamond Bag', $bagsScreen);
        $this->assertStringContainsString("value: 'diamond_requirement'", $orderScreen);
        $this->assertStringContainsString('Create Bag', $orderScreen);
        $this->assertStringContainsString('ISSUE → ORDER → STUDDING TRAIL', $bagsScreen);
        $this->assertStringContainsString('DiamondBagCreateScreen', $taskScreen);
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
