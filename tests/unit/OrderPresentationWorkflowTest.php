<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class OrderPresentationWorkflowTest extends CIUnitTestCase
{
    public function testOrderNameAndJewelleryCategoryAreCapturedEverywhere(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-03-000080_AddOrderNamesAndJewelleryCategories.php');
        $controller = $this->source('Controllers/Admin/OrderController.php');
        $adminCreate = $this->source('Views/admin/orders/create.php');
        $customerCreate = $this->source('Views/customer/orders/create.php');

        $this->assertStringContainsString("createTable('order_categories'", $migration);
        $this->assertStringContainsString("'order_name'", $migration);
        $this->assertStringContainsString("'order_category_id'", $migration);
        $this->assertStringContainsString('OrderCategoryService', $controller);
        $this->assertStringContainsString('name="order_name"', $adminCreate);
        $this->assertStringContainsString('+ Add New Category', $adminCreate);
        $this->assertStringContainsString('name="order_name"', $customerCreate);
        $this->assertStringContainsString('+ Add New Category', $customerCreate);
    }

    public function testOperationalOrderRegistersShowNameAndThumbnail(): void
    {
        foreach ([
            'Views/admin/orders/index.php',
            'Views/admin/orders/dashboard.php',
            'Views/admin/orders/followups.php',
            'Views/admin/karigars/show.php',
            'Views/admin/customers/show.php',
            'Views/customer/orders/index.php',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString('order_name', $source, $path);
            $this->assertStringContainsString('thumbnail_url', $source, $path);
        }
    }

    public function testSalesPersonIsOptionalAndNotShownAsAnOrderRegisterColumn(): void
    {
        $adminController = $this->source('Controllers/Admin/OrderController.php');
        $customerController = $this->source('Controllers/Customer/OrdersController.php');
        $adminCreate = $this->source('Views/admin/orders/create.php');
        $customerCreate = $this->source('Views/customer/orders/create.php');

        $this->assertStringContainsString("'sales_person_user_id' => 'permit_empty|integer'", $adminController);
        $this->assertStringContainsString("'sales_person_user_id' => 'permit_empty|integer'", $customerController);
        $this->assertStringNotContainsString('<th>Sales Person</th>', $this->source('Views/admin/orders/index.php'));
        $this->assertStringNotContainsString('<th>Sales Person</th>', $this->source('Views/customer/orders/index.php'));
        $this->assertStringNotContainsString('name="sales_person_user_id" id="order-sales-person" class="form-control js-searchable-select" data-placeholder="Search sales person" required', $adminCreate);
        $this->assertStringNotContainsString('name="sales_person_user_id" id="sales-person" class="form-select js-searchable-select" data-placeholder="Search by name or mobile" required', $customerCreate);
    }

    public function testCustomerColumnIsHiddenFromOperationalOrderTables(): void
    {
        foreach ([
            'Views/admin/orders/index.php',
            'Views/admin/orders/dashboard.php',
            'Views/admin/orders/followups.php',
            'Views/admin/karigars/show.php',
            'Views/admin/dashboard/index.php',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringNotContainsString('<th>Customer</th>', $source, $path);
            $this->assertStringNotContainsString('<th>Customer / Source</th>', $source, $path);
        }
    }

    public function testPriorityColumnIsHiddenFromOperationalOrderTables(): void
    {
        foreach ([
            'Views/admin/orders/index.php',
            'Views/admin/karigars/show.php',
            'Views/admin/dashboard/index.php',
        ] as $path) {
            $this->assertStringNotContainsString('<th>Priority</th>', $this->source($path), $path);
        }
    }

    public function testDashboardOrderStatusChangeUsesProtectedWorkflowEndpointAndEvidence(): void
    {
        $dashboard = $this->source('Views/admin/dashboard/index.php');
        $controller = $this->source('Controllers/Admin/DashboardController.php');
        $react = (string) file_get_contents(ROOTPATH . 'frontend/src/Dashboard.jsx');
        $routes = $this->source('Config/Routes.php');

        $this->assertStringContainsString("admin_can('orders.status')", $controller);
        $this->assertStringContainsString("'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]", $controller);
        $this->assertStringContainsString("'Dispatched' => 'Completed'", $dashboard);
        $this->assertStringContainsString("site_url('admin/orders/' . (int) (\$row['id'] ?? 0) . '/status')", $dashboard);
        $this->assertStringContainsString('name="audit_image" accept="image/*" required', $react);
        $this->assertStringContainsString('name="remarks"', $react);
        $this->assertStringContainsString("Admin\\OrderController::updateStatus/\$1", $routes);
        $this->assertStringContainsString("'Completed'     => 'Dispatched'", $this->source('Controllers/Admin/OrderController.php'));
        $this->assertStringContainsString("'Completed' => \$isReceived ? 'Dispatched' : ''", $dashboard);
    }

    public function testReceivingIsReadyOnlyAndBlocksDownstreamStatusesUntilReceived(): void
    {
        $controller = $this->source('Controllers/Admin/OrderController.php');
        $receivingService = $this->source('Services/OrderReceivingService.php');
        $mobileController = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $orderDetail = $this->source('Views/admin/orders/show.php');
        $dashboard = $this->source('Views/admin/dashboard/index.php');

        $this->assertStringContainsString("!== 'Ready'", $controller);
        $this->assertStringContainsString("['Packed', 'Dispatched', 'Completed']", $controller);
        $this->assertStringContainsString('assertReadyForReceiving($order)', $receivingService);
        $this->assertStringContainsString("=== 'Ready'", $mobileController);
        $this->assertStringContainsString("\$status === 'Ready'", $orderDetail);
        $this->assertStringContainsString("'Ready' => \$isReceived ? 'Packed' : ''", $dashboard);
        $this->assertStringContainsString("'receiveUrl'", $dashboard);
    }

    public function testMigrationReopensUnreceivedCompletedPackedAndDispatchedOrders(): void
    {
        $migration = (string) file_get_contents(
            ROOTPATH . 'app/Database/Migrations/2026-10-14-000111_ReopenUnreceivedOrders.php'
        );

        $this->assertStringContainsString("->whereIn('o.status', ['Completed', 'Packed', 'Dispatched'])", $migration);
        $this->assertStringContainsString("->where('rs.id', null)", $migration);
        $this->assertStringContainsString("->where('rm.id', null)", $migration);
        $this->assertStringContainsString("'status' => 'Ready'", $migration);
        $this->assertStringContainsString("\$updates['receiving_status'] = 'pending'", $migration);
        $this->assertStringContainsString("->whereIn('item_status', ['Completed', 'Packed', 'Dispatched'])", $migration);
        $this->assertStringContainsString("'to_status' => 'Ready'", $migration);
        $this->assertStringContainsString("->where('movement_type', 'receive')", $this->source('Services/OrderReceivingService.php'));
        $this->assertStringContainsString('use App\\Services\\OrderReceivingService;', $this->source('Controllers/Admin/OrderController.php'));
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
