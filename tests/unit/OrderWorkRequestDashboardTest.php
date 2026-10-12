<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class OrderWorkRequestDashboardTest extends CIUnitTestCase
{
    public function testAdminDashboardExposesOrderRequestsBelowMobileApprovals(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderWorkRequestController.php');
        $view = (string) file_get_contents(APPPATH . 'Views/admin/order_work_requests/index.php');

        $this->assertStringContainsString("\$routes->get('order-requests'", $routes);
        $this->assertStringContainsString("site_url('admin/order-requests')", $layout);
        $this->assertGreaterThan(
            strpos($layout, "site_url('admin/mobile-approvals')"),
            strpos($layout, "site_url('admin/order-requests')")
        );
        $this->assertStringContainsString('OrderWorkRequestService', $controller);
        $this->assertStringContainsString('Order Requests', $view);
        $this->assertStringContainsString('Approve and process this order request?', $view);
    }

    public function testMobileAndWebReviewsShareTheSameWorkflowService(): void
    {
        $mobileController = (string) file_get_contents(
            APPPATH . 'Controllers/Api/Mobile/OrderWorkRequestsController.php'
        );
        $service = (string) file_get_contents(APPPATH . 'Services/OrderWorkRequestService.php');

        $this->assertStringContainsString('new OrderWorkRequestService()', $mobileController);
        $this->assertStringContainsString("userCan(\$reviewerId, 'orders.assign')", $service);
        $this->assertStringContainsString("'gold_requirement', 'diamond_requirement'", $service);
        $this->assertStringContainsString('syncOrderAssignment', $service);
        $this->assertStringContainsString("'order_gold_request'", $service);
        $this->assertStringContainsString("'order_diamond_bag'", $service);
    }

    public function testDelayApprovalRequiresAdminNoteAndFinalDueDate(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-10-12-000099_AddApprovedDueDateToOrderWorkRequests.php'
        );
        $service = (string) file_get_contents(APPPATH . 'Services/OrderWorkRequestService.php');
        $webView = (string) file_get_contents(APPPATH . 'Views/admin/order_work_requests/index.php');
        $pwaScreen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/order_detail_screen.dart'
        );

        $this->assertStringContainsString("'approved_due_at'", $migration);
        $this->assertStringContainsString('Enter an admin note for the updated order due date.', $service);
        $this->assertStringContainsString("\$payload['approved_due_at']", $service);
        $this->assertStringContainsString("'due_date' => \$newDue", $service);
        $this->assertStringContainsString('name="approved_due_at"', $webView);
        $this->assertStringContainsString('Admin note *', $webView);
        $this->assertStringContainsString("'approved_due_at': _dateValue(dueAt)", $pwaScreen);
    }
}
