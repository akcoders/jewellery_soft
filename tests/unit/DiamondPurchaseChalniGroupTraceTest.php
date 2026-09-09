<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondPurchaseChalniGroupTraceTest extends CIUnitTestCase
{
    public function testMigrationSeedsFourPurchaseGroupsAndMapsRoundChalnis(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-09-000090_TraceDiamondPurchasesByChalniGroup.php');

        $this->assertStringContainsString("'MINUS_TWO'", $migration);
        $this->assertStringContainsString("'Star (+2 to +5.5)'", $migration);
        $this->assertStringContainsString("'Melle (+6 to +11)'", $migration);
        $this->assertStringContainsString("'Pointer (+11 Up)'", $migration);
        $this->assertStringContainsString("createTable('diamond_chalni_groups'", $migration);
        $this->assertStringContainsString("createTable('diamond_chalni_group_sizes'", $migration);
        $this->assertStringContainsString("'shape_master_id'", $migration);
        $this->assertStringContainsString("'chalni_group_id'", $migration);
    }

    public function testPurchaseFormUsesSearchableMasterShapeAndGroupInsteadOfLegacyRangeAndCut(): void
    {
        $form = $this->source('Views/admin/diamond_inventory/purchases/form.php');

        $this->assertStringContainsString('name="shape_master_id[]"', $form);
        $this->assertStringContainsString('name="chalni_group_id[]"', $form);
        $this->assertStringContainsString('purchase-searchable', $form);
        $this->assertStringContainsString('minimumResultsForSearch: 0', $form);
        $this->assertStringNotContainsString('name="chalni_from[]"', $form);
        $this->assertStringNotContainsString('name="chalni_to[]"', $form);
        $this->assertStringNotContainsString('name="cut[]"', $form);
    }

    public function testPurchasePostingMaintainsProductAndChalniGroupStockTogether(): void
    {
        $controller = $this->source('Controllers/Admin/DiamondInventory/PurchasesController.php');
        $service = $this->source('Services/DiamondChalniStockService.php');
        $model = $this->source('Models/PurchaseLineModel.php');

        $this->assertStringContainsString("getPost('chalni_group_id')", $controller);
        $this->assertStringContainsString("'shape_master_id' => \$line['shape_master_id']", $controller);
        $this->assertStringContainsString("'chalni_group_id' => \$line['chalni_group_id']", $controller);
        $this->assertGreaterThanOrEqual(2, substr_count($controller, '->applyPurchase('));
        $this->assertGreaterThanOrEqual(2, substr_count($controller, '->reversePurchase('));
        $this->assertStringContainsString('function applyPurchase(', $service);
        $this->assertStringContainsString('function reversePurchase(', $service);
        $this->assertStringContainsString("'purchase_lines'", $service);
        $this->assertStringContainsString("'PURCHASE_GROUP'", $service);
        $this->assertStringContainsString("'shape_master_id'", $model);
        $this->assertStringContainsString("'chalni_group_id'", $model);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
