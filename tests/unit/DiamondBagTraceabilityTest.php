<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondBagTraceabilityTest extends CIUnitTestCase
{
    public function testSchemaSupportsShapeSizeAndExactMovementTrace(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-07-000083_CreateBagWiseDiamondTraceability.php'
        );

        foreach ([
            'diamond_shape_masters',
            'diamond_size_masters',
            'diamond_bag_movements',
            'bag_item_id',
            'allocation_order_id',
            'diamond_issue_line_id',
            'receive_detail_id',
            'seedSizesFromExistingDiamondItems',
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
    }

    public function testIssueReturnAndStuddingUseExactBagRows(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/DiamondBagTraceService.php');
        $issues = (string) file_get_contents(APPPATH . 'Controllers/Admin/DiamondInventory/IssuesController.php');
        $returns = (string) file_get_contents(APPPATH . 'Controllers/Admin/DiamondInventory/ReturnsController.php');
        $orders = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');

        $this->assertStringContainsString('function applyIssue', $service);
        $this->assertStringContainsString('function applyReturn', $service);
        $this->assertStringContainsString('function recordStudding', $service);
        $this->assertStringContainsString('consumedAgainstIssueLine', $service);
        $this->assertStringContainsString("'bag_item_id' =>", $issues);
        $this->assertStringContainsString("'issue_line_id' =>", $returns);
        $this->assertStringContainsString('validateReceivedDiamondSelection', $orders);
        $this->assertStringContainsString('diamond_issue_line_id', $orders);
    }

    public function testUiAndMobileApiRequireCalibratedBagSelection(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $bagForm = (string) file_get_contents(APPPATH . 'Views/admin/diamond_bags/form.php');
        $issueForm = (string) file_get_contents(APPPATH . 'Views/admin/diamond_inventory/issues/form.php');
        $returnForm = (string) file_get_contents(APPPATH . 'Views/admin/diamond_inventory/returns/form.php');
        $mobile = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');

        $this->assertStringContainsString('diamond-inventory/shape-sizes', $routes);
        $this->assertStringContainsString('lookups/diamond-bag-items', $routes);
        $this->assertStringContainsString('lookups/diamond-order-allocations', $routes);
        $this->assertStringContainsString('shape_master_id[]', $bagForm);
        $this->assertStringContainsString('size_master_id[]', $bagForm);
        $this->assertStringContainsString('bag_item_id[]', $issueForm);
        $this->assertStringContainsString('data-pcs=', $issueForm);
        $this->assertStringContainsString('data-cts=', $issueForm);
        $this->assertStringContainsString('issue_line_id[]', $returnForm);
        $this->assertStringContainsString('parseDiamondLines($payload[\'lines\'] ?? [], true)', $mobile);
        $this->assertStringContainsString('parseDiamondReturnLines', $mobile);
        $this->assertStringContainsString("'allocation_order_id' =>", $mobile);
    }

    public function testSuppliedRoundPrincessAndMarquiseSizesAreSeededWithDimensions(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-08-000084_SeedStandardDiamondShapeSizes.php'
        );
        $model = (string) file_get_contents(APPPATH . 'Models/DiamondSizeMasterModel.php');
        $view = (string) file_get_contents(APPPATH . 'Views/admin/diamond_inventory/shape_sizes/index.php');

        $this->assertStringContainsString("['-000000', 0.65]", $migration);
        $this->assertStringContainsString("['20', 4.50]", $migration);
        $this->assertStringContainsString('for ($tenths = 14; $tenths <= 58; $tenths++)', $migration);
        $this->assertStringContainsString('[2.95, 1.75]', $migration);
        $this->assertStringContainsString('[7.00, 3.00]', $migration);
        $this->assertStringContainsString('chalni_label', $model);
        $this->assertStringContainsString('length_mm', $model);
        $this->assertStringContainsString('width_mm', $model);
        $this->assertStringContainsString('Length / Diameter mm', $view);
    }
}
