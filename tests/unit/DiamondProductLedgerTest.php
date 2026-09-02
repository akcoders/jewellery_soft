<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class DiamondProductLedgerTest extends CIUnitTestCase
{
    public function testLedgerUsesDynamicProductMasterColumnsAndBothInventoryDirections(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/DiamondLedgerService.php');

        $this->assertStringContainsString("NULLIF(TRIM(clarity), '')", $service);
        $this->assertStringContainsString("'Purchase' AS txn_type", $service);
        $this->assertStringContainsString("'Issue' AS txn_type", $service);
        $this->assertStringContainsString("'Return' AS txn_type", $service);
        $this->assertStringContainsString("'received' AS direction", $service);
        $this->assertStringContainsString("'issued' AS direction", $service);
        $this->assertStringContainsString('$showAllProducts', $service);
        $this->assertStringContainsString('$activeLabels', $service);
    }

    public function testLedgerViewBuildsReceivedAndIssuedColumnsPerProduct(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/reports/diamond_ledger.php');

        $this->assertStringContainsString('id="diamond-product-ledger"', $view);
        $this->assertStringContainsString('foreach ($productRows as $product)', $view);
        $this->assertStringContainsString('<th>Received</th><th>Issued</th>', $view);
        $this->assertStringContainsString('Closing balance', $view);
        $this->assertStringContainsString('id="show-all-diamond-products"', $view);
        $this->assertStringContainsString('Show all master products', $view);
    }

    public function testDiamondInventoryExposesTheSameLedgerPage(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');

        $this->assertStringContainsString("diamond-inventory/ledger", $routes);
        $this->assertStringContainsString("site_url('admin/diamond-inventory/ledger')", $layout);
        $this->assertStringContainsString('$isDiamondInventoryLedger', $layout);
    }
}
