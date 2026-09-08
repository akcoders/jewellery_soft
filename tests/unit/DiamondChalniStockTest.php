<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondChalniStockTest extends CIUnitTestCase
{
    public function testMigrationImportsSuppliedVvsRoundPhysicalSplit(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-08-000087_CreateDiamondChalniStock.php');

        $this->assertStringContainsString("createTable('diamond_chalni_stocks'", $migration);
        $this->assertStringContainsString("createTable('diamond_chalni_stock_movements'", $migration);
        $this->assertStringContainsString('SUPPLIED_TOTAL_CTS = 146.190', $migration);
        $this->assertStringContainsString("'000000-00000' => 3.630", $migration);
        $this->assertStringContainsString("'3-4' => 23.380", $migration);
        $this->assertStringContainsString("'11-12' => 0.420", $migration);
        $this->assertStringContainsString('VVS-VS. ROUND', $migration);
    }

    public function testStockServiceSeparatesLedgerTracedAndUntracedBalances(): void
    {
        $service = $this->source('Services/DiamondChalniStockService.php');

        $this->assertStringContainsString("'untraced_cts'", $service);
        $this->assertStringContainsString("'over_traced_cts'", $service);
        $this->assertStringContainsString('function assertPackable', $service);
        $this->assertStringContainsString('function applyIssue', $service);
        $this->assertStringContainsString('function applyReturn', $service);
        $this->assertStringContainsString("'issue_lines'", $service);
        $this->assertStringContainsString("'return_lines'", $service);
        $this->assertStringContainsString('This issue is consumed from the product\'s untraced balance.', $service);
    }

    public function testProductSelectionStatsAndManualClassificationUiAreConnected(): void
    {
        $routes = $this->source('Config/Routes.php');
        $controller = $this->source('Controllers/Admin/DiamondInventory/StockController.php');
        $view = $this->source('Views/admin/diamond_inventory/stock/index.php');
        $requirement = $this->source('Services/DiamondRequirementService.php');
        $genericBags = $this->source('Controllers/Admin/DiamondBagController.php');

        $this->assertStringContainsString('diamond-inventory/stock/chalni', $routes);
        $this->assertStringContainsString('saveChalniStock', $controller);
        $this->assertStringContainsString('id="stock-product"', $view);
        $this->assertStringContainsString('Chalni Traced', $view);
        $this->assertStringContainsString('Untraced', $view);
        $this->assertStringContainsString('Add Chalni Stock', $view);
        $this->assertStringContainsString('All Diamond Product Reconciliation', $view);
        $this->assertStringContainsString('DiamondChalniStockService', $requirement);
        $this->assertStringContainsString('DiamondChalniStockService', $genericBags);
    }

    private function source(string $relative): string
    {
        return (string) file_get_contents(APPPATH . $relative);
    }
}
