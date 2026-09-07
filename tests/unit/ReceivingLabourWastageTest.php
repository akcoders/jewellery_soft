<?php

namespace Tests\Unit;

use App\Services\KarigarMaterialAccountingService;
use CodeIgniter\Test\CIUnitTestCase;

final class ReceivingLabourWastageTest extends CIUnitTestCase
{
    public function testWastageUsesOrnamentPurityBeforeDeductingPureGold(): void
    {
        $result = KarigarMaterialAccountingService::calculateLabourWastage(10.000, 75.000, 5.000);

        $this->assertSame(0.500, $result['ornament_weight_gm']);
        $this->assertSame(0.375, $result['pure_weight_gm']);
    }

    public function testReceivingUsesPurityMasterAndPostsOneNamedWastageCharge(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $accounting = (string) file_get_contents(APPPATH . 'Services/KarigarMaterialAccountingService.php');
        $orderList = (string) file_get_contents(APPPATH . 'Views/admin/orders/index.php');
        $orderDetail = (string) file_get_contents(APPPATH . 'Views/admin/orders/show.php');
        $finishedJewellery = (string) file_get_contents(APPPATH . 'Services/FinishedJewelleryService.php');

        $this->assertStringContainsString("'gold_purity_id' => 'required|integer|greater_than[0]'", $controller);
        $this->assertStringContainsString("\$this->goldPurityModel->where('is_active', 1)->find(\$goldPurityId)", $controller);
        $this->assertStringNotContainsString("getPost('purity_percent')", $controller);
        $this->assertStringContainsString('postLabourWastageCharge(', $controller);
        $this->assertSame(1, substr_count($controller, '$materialAccounting->postLabourWastageCharge('));
        $this->assertStringContainsString("'voucher_type' => 'LABOUR_WASTAGE_CHARGE'", $accounting);
        $this->assertStringContainsString("'credit_account_id' => \$karigarAccountId", $accounting);
        $this->assertStringContainsString('name="gold_purity_id"', $orderList);
        $this->assertStringContainsString('name="gold_purity_id"', $orderDetail);
        $this->assertStringNotContainsString('name="purity_percent"', $orderList . $orderDetail);
        $this->assertStringContainsString('name="wastage_percent"', $orderList);
        $this->assertStringContainsString('name="wastage_percent"', $orderDetail);
        $this->assertStringContainsString("(\$summary['gold_purity_id'] ?? 0)", $finishedJewellery);
        $this->assertStringContainsString("'purity_label' => \$purityLabel", $finishedJewellery);
    }

    public function testReceiveSnapshotMigrationStoresTheAuditTrail(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-07-000082_AddReceivingLabourWastageCharge.php'
        );
        $model = (string) file_get_contents(APPPATH . 'Models/OrderReceiveSummaryModel.php');

        foreach ([
            'gold_purity_id',
            'purity_percent',
            'wastage_percent',
            'wastage_weight_gm',
            'pure_wastage_weight_gm',
            'wastage_account_voucher_id',
        ] as $field) {
            $this->assertStringContainsString($field, $migration);
            $this->assertStringContainsString("'{$field}'", $model);
        }
    }
}
