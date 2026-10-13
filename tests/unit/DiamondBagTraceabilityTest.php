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

    public function testBrokenDiamondReturnIsWeightOnlyAndNeverPreparedInBags(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-10-13-000106_AddBrokenDiamondReturnLines.php'
        );
        $productMigration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-10-13-000107_EnsureBrokenDiamondInventoryItem.php'
        );
        $stock = (string) file_get_contents(APPPATH . 'Services/DiamondInventory/StockService.php');
        $chalniStock = (string) file_get_contents(APPPATH . 'Services/DiamondChalniStockService.php');
        $trace = (string) file_get_contents(APPPATH . 'Services/DiamondBagTraceService.php');
        $bagService = (string) file_get_contents(APPPATH . 'Services/DiamondBagService.php');
        $adminReturn = (string) file_get_contents(APPPATH . 'Controllers/Admin/DiamondInventory/ReturnsController.php');
        $mobileReturn = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $adminForm = (string) file_get_contents(APPPATH . 'Views/admin/diamond_inventory/returns/form.php');
        $adminStock = (string) file_get_contents(APPPATH . 'Controllers/Admin/DiamondInventory/StockController.php');
        $mobileStock = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/InventoryController.php');
        $native = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/transaction_create_screen.dart'
        );
        $pwa = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/transaction_create_screen.dart'
        );
        $nativeInventory = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/inventory_tab.dart'
        );
        $pwaInventory = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/inventory_tab.dart'
        );

        $this->assertStringContainsString("'is_broken'", $migration);
        $this->assertStringContainsString("'diamond_type' => 'Broken'", $productMigration);
        $this->assertStringContainsString("->table('stock')->insert", $productMigration);
        $this->assertStringContainsString('brokenDiamondItemId', $stock);
        $this->assertStringContainsString("LOWER(TRIM(diamond_type)) = 'broken'", $stock);
        $this->assertStringContainsString('including broken weight', $stock);
        $this->assertStringContainsString("->where('rd.component_type', 'diamond')", $stock);
        $this->assertStringContainsString('brokenReturnedByIssue', $trace);
        $this->assertStringContainsString('if ($availableCts <= self::EPSILON)', $trace);
        $this->assertStringContainsString("LOWER(TRIM(i.diamond_type)) <> 'broken'", $bagService);
        $this->assertStringContainsString('Broken Diamond · Weight only', $chalniStock);
        $this->assertStringContainsString('cannot be classified by PCS, shape or chalni', $chalniStock);
        $this->assertStringContainsString('brokenDiamondItemId()', $adminStock);
        $this->assertStringContainsString('brokenDiamondItemId()', $mobileStock);
        $this->assertStringContainsString("'is_broken' => 1", $adminReturn);
        $this->assertStringContainsString("'is_broken' => 1", $mobileReturn);
        $this->assertStringContainsString('name="broken_carat"', $adminForm);
        $this->assertStringContainsString("payload['broken_carat']", $native);
        $this->assertStringContainsString("payload['broken_carat']", $pwa);
        $this->assertStringContainsString('Weight-only inventory | PCS not required', $nativeInventory);
        $this->assertStringContainsString('Weight-only inventory · PCS not required', $pwaInventory);
    }

    public function testDiamondReturnDeleteIsRestrictedToDashboardAdministrators(): void
    {
        $controller = (string) file_get_contents(
            APPPATH . 'Controllers/Admin/DiamondInventory/ReturnsController.php'
        );
        $index = (string) file_get_contents(
            APPPATH . 'Views/admin/diamond_inventory/returns/index.php'
        );
        $detail = (string) file_get_contents(
            APPPATH . 'Views/admin/diamond_inventory/returns/view.php'
        );
        $mobile = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString('isDashboardAdmin()', $controller);
        $this->assertStringContainsString("['SUPER_ADMIN', 'ADMIN', 'OWNER']", $controller);
        $this->assertStringContainsString('Only an administrator can delete a diamond return', $controller);
        $this->assertStringContainsString('! empty($canDelete)', $index);
        $this->assertStringContainsString('! empty($canDelete)', $detail);
        $this->assertStringNotContainsString('api/mobile/diamond-inventory/returns/(:num)/delete', $mobile);
    }

    public function testIssuedBagsAppearInOrderWorkspacesAndOpenFullDetails(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/DiamondBagService.php');
        $api = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/OrdersController.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $web = (string) file_get_contents(APPPATH . 'Views/admin/orders/show.php');
        $pwa = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/order_detail_screen.dart');
        $native = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/order_detail_screen.dart');

        $this->assertStringContainsString('function issuedForOrder', $service);
        $this->assertStringContainsString("->orWhere('il.allocation_order_id', \$orderId)", $service);
        $this->assertStringContainsString("'diamond_bags' => \$this->diamondBagService->issuedForOrder(\$id)", $api);
        $this->assertStringContainsString("'diamondBags' => \$this->diamondBagService->issuedForOrder(\$id)", $controller);
        $this->assertStringContainsString('Diamond Bags for This Order', $web);
        $this->assertStringContainsString('orderDiamondBagModal', $web);
        $this->assertStringContainsString('Issue with Image Evidence', $web);
        $this->assertStringContainsString("data['diamond_bags']", $pwa);
        $this->assertStringContainsString('DiamondBagDetailScreen', $pwa);
        $this->assertStringContainsString("data['diamond_bags']", $native);
        $this->assertStringContainsString('DiamondBagDetailScreen', $native);
    }

    public function testPreparedBagIssuementRequiresEvidenceAndMobileDataIsOwnerScoped(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $transactions = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $bags = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/DiamondBagsController.php');
        $inventory = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/InventoryController.php');
        $lookups = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/LookupsController.php');
        $service = (string) file_get_contents(APPPATH . 'Services/DiamondBagService.php');
        $native = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/order_detail_screen.dart');
        $pwa = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/order_detail_screen.dart');

        $this->assertStringContainsString("diamond-bags/(:num)/issue", $routes);
        $this->assertStringContainsString('function issueDiamondBag', $transactions);
        $this->assertStringContainsString("'attachment_base64'", $transactions);
        $this->assertStringContainsString("'diamond_bags'", $transactions);
        $this->assertStringContainsString('function issuementContext', $service);
        $this->assertStringContainsString('pending_issue_approval', $service);
        $this->assertStringContainsString('canViewAllMobileData', $bags);
        $this->assertStringContainsString("->where('ih.created_by'", $inventory);
        $this->assertStringContainsString("->where('ih.created_by'", $lookups);
        $this->assertStringContainsString('Issue with Evidence', $native);
        $this->assertStringContainsString('Issue with Evidence', $pwa);
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
