<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DiamondPurchaseRoundOffAndGstCascadeTest extends CIUnitTestCase
{
    public function testDiamondPurchaseSupportsSuggestedAndManualRoundOff(): void
    {
        $form = $this->source('Views/admin/diamond_inventory/purchases/form.php');
        $controller = $this->source('Controllers/Admin/DiamondInventory/PurchasesController.php');

        $this->assertStringContainsString('name="round_off_amount"', $form);
        $this->assertStringContainsString('id="round_off_suggestion"', $form);
        $this->assertStringContainsString('id="apply_round_off_suggestion"', $form);
        $this->assertStringContainsString('Math.round(beforeRoundOff) - beforeRoundOff', $form);
        $this->assertStringContainsString("getPost('round_off_amount')", $controller);
        $this->assertStringContainsString("'round_off_amount' => \$tax['round_off_amount']", $controller);
        $this->assertGreaterThanOrEqual(2, substr_count($controller, "'round_off_amount' => \$tax['round_off_amount']"));
    }

    public function testGstMasterCanBeEditedAndLinkedPurchasesAreRecalculated(): void
    {
        $routes = $this->source('Config/Routes.php');
        $controller = $this->source('Controllers/Admin/TaxMasterController.php');
        $service = $this->source('Services/TaxMasterService.php');
        $view = $this->source('Views/admin/tax_masters/index.php');

        $this->assertStringContainsString("tax-masters/gst/(:num)/update", $routes);
        $this->assertStringContainsString('function updateGstMaster(', $controller);
        $this->assertStringContainsString('updateMasterAndLinkedPurchases(', $controller);
        $this->assertStringContainsString('id="editGstMasterModal"', $view);
        $this->assertStringContainsString('Update &amp; Recalculate Purchases', $view);

        foreach ([
            "'purchase_headers' => 'invoice_total'",
            "'gold_inventory_purchase_headers' => 'invoice_total'",
            "'stone_inventory_purchase_headers' => 'invoice_total'",
            "'purchases' => 'invoice_amount'",
        ] as $mapping) {
            $this->assertStringContainsString($mapping, $service);
        }
        $this->assertStringContainsString("'tax_breakup_json' => \$tax['tax_breakup_json']", $service);
        $this->assertStringContainsString("'gst_amount' => \$tax['gst_amount']", $service);
        $this->assertStringNotContainsString("'paid_amount' =>", $service);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
