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

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
