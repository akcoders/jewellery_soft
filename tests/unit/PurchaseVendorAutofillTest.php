<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PurchaseVendorAutofillTest extends CIUnitTestCase
{
    public function testEveryMaterialPurchaseFormAutofillsVendorSnapshot(): void
    {
        foreach (['gold_inventory', 'diamond_inventory', 'stone_inventory'] as $inventory) {
            $form = (string) file_get_contents(APPPATH . 'Views/admin/' . $inventory . '/purchases/form.php');
            $this->assertStringContainsString('data-address=', $form, $inventory);
            $this->assertStringContainsString('data-gstin=', $form, $inventory);
            $this->assertStringContainsString('data-phone=', $form, $inventory);
            $this->assertStringContainsString('data-email=', $form, $inventory);
            $this->assertStringContainsString('function fillVendorDetails()', $form, $inventory);
            $this->assertStringContainsString('id="supplier_name"', $form, $inventory);
            $this->assertStringContainsString('id="supplier_address"', $form, $inventory);
            $this->assertStringContainsString('id="supplier_gstin"', $form, $inventory);
            $this->assertStringContainsString('id="supplier_phone"', $form, $inventory);
            $this->assertStringContainsString('id="supplier_email"', $form, $inventory);
        }
    }

    public function testLineItemsAppearBeforeAutomaticTaxInformation(): void
    {
        $forms = [
            APPPATH . 'Views/admin/gold_inventory/purchases/form.php' => 'Tax & Payment Information',
            APPPATH . 'Views/admin/diamond_inventory/purchases/form.php' => 'Tax Information',
            APPPATH . 'Views/admin/stone_inventory/purchases/form.php' => 'Tax Information',
        ];

        foreach ($forms as $path => $taxHeading) {
            $form = (string) file_get_contents($path);
            $this->assertLessThan(strpos($form, $taxHeading), strpos($form, 'Purchase Lines'), $path);
            $this->assertStringContainsString('function recalcTotals()', $form, $path);
            $this->assertStringContainsString('id="invoice_total"', $form, $path);
        }
    }

    public function testStonePurchasesPersistCompleteSupplierAndTaxSnapshot(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-01-000075_EnhanceStonePurchaseSupplierTaxDetails.php'
        );
        $model = (string) file_get_contents(APPPATH . 'Models/StoneInventoryPurchaseHeaderModel.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/StoneInventory/PurchasesController.php');

        foreach (['supplier_address', 'supplier_gstin', 'supplier_phone', 'supplier_email', 'taxable_amount', 'gst_amount'] as $field) {
            $this->assertStringContainsString($field, $migration, $field);
            $this->assertStringContainsString("'{$field}'", $model, $field);
            $this->assertStringContainsString("'{$field}' =>", $controller, $field);
        }
    }

    public function testGoldTaxValuesAreRecalculatedOnServer(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/GoldInventory/PurchasesController.php');
        $this->assertStringContainsString("array_sum(array_column(\$lines, 'line_value'))", $controller);
        $this->assertStringContainsString('$taxable * max(0, min(100, (float) $cgstRate)) / 100', $controller);
        $this->assertStringContainsString('$taxable * max(0, min(100, (float) $sgstRate)) / 100', $controller);
        $this->assertStringContainsString('$taxable * max(0, min(100, (float) $igstRate)) / 100', $controller);
    }
}
