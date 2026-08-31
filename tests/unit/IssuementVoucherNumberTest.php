<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class IssuementVoucherNumberTest extends CIUnitTestCase
{
    public function testCompanySettingsExposeIssuementStartCount(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-31-000073_AddIssuementStartCount.php'
        );
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/CompanySettingsController.php');
        $model = (string) file_get_contents(APPPATH . 'Models/CompanySettingModel.php');
        $view = (string) file_get_contents(APPPATH . 'Views/admin/company_settings/index.php');

        $this->assertStringContainsString("'issuement_start_count'", $migration);
        $this->assertStringContainsString("'default'    => 1", $migration);
        $this->assertStringContainsString("'issuement_start_count' => 'required|integer|greater_than[0]", $controller);
        $this->assertStringContainsString("'issuement_start_count'", $model);
        $this->assertStringContainsString('name="issuement_start_count"', $view);
    }

    public function testAllWebIssueCreateAndEditFlowsAcceptVoucherNumber(): void
    {
        $forms = [
            APPPATH . 'Views/admin/issuements/create.php',
            APPPATH . 'Views/admin/gold_inventory/issues/form.php',
            APPPATH . 'Views/admin/diamond_inventory/issues/form.php',
            APPPATH . 'Views/admin/stone_inventory/issues/form.php',
        ];

        foreach ($forms as $form) {
            $source = (string) file_get_contents($form);
            $this->assertStringContainsString('name="voucher_no"', $source, $form);
        }

        $controllers = [
            APPPATH . 'Controllers/Admin/IssuementController.php',
            APPPATH . 'Controllers/Admin/GoldInventory/IssuesController.php',
            APPPATH . 'Controllers/Admin/DiamondInventory/IssuesController.php',
            APPPATH . 'Controllers/Admin/StoneInventory/IssuesController.php',
        ];

        foreach ($controllers as $controller) {
            $source = (string) file_get_contents($controller);
            $this->assertStringContainsString('IssuementVoucherNumberService', $source, $controller);
            $this->assertStringContainsString("'voucher_no' => 'required|max_length[80]'", $source, $controller);
        }

        foreach (array_slice($controllers, 1) as $controller) {
            $source = (string) file_get_contents($controller);
            $this->assertStringContainsString('resolveForUpdate(', $source, $controller);
            $this->assertStringContainsString("'voucher_no' => \$voucherNo", $source, $controller);
        }
    }

    public function testVoucherGeneratorHonoursStartFloorAndRejectsDuplicates(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/IssuementVoucherNumberService.php');

        $this->assertStringContainsString("max(1, (int) (\$setting['issuement_start_count'] ?? 1))", $service);
        $this->assertStringContainsString('max($nextSerial, ((int) $matches[1]) + 1)', $service);
        $this->assertStringContainsString('Voucher number already exists:', $service);
        $this->assertStringContainsString("str_pad((string) \$nextSerial, 3, '0', STR_PAD_LEFT)", $service);
        $this->assertStringContainsString("'gold_inventory_issue_headers'", $service);
        $this->assertStringContainsString("'issue_headers'", $service);
        $this->assertStringContainsString("'stone_inventory_issue_headers'", $service);
    }

    public function testMobileIssueCreationCanSendManualVoucherNumber(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $screen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/transaction_create_screen.dart'
        );

        $this->assertSame(3, substr_count($controller, '->resolveForCreate('));
        $this->assertStringContainsString("payload['voucher_no'] = _voucherNo.trim()", $screen);
        $this->assertStringContainsString('Voucher Number (optional)', $screen);
    }
}
