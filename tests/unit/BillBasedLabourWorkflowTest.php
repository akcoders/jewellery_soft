<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BillBasedLabourWorkflowTest extends CIUnitTestCase
{
    public function testReceivingNoLongerCreatesAutomaticLabourBill(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $this->assertStringNotContainsString('createLabourBillFromReceive', $controller);
    }

    public function testLabourBillsSupportMultipleJobworksAndAttachments(): void
    {
        $migration = (string) file_get_contents(APPPATH . 'Database/Migrations/2026-09-02-000077_CreateBillBasedLabourAndTaxMasters.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/LabourBillController.php');
        $profile = (string) file_get_contents(APPPATH . 'Views/admin/karigars/show.php');

        $this->assertStringContainsString('labour_bill_jobworks', $migration);
        $this->assertStringContainsString("getPost('jobworks')", $controller);
        $this->assertStringContainsString("getFile('attachment')", $controller);
        $this->assertStringContainsString('order_name', $controller);
        $this->assertStringContainsString('image_url', $controller);
        $this->assertStringContainsString('jobwork-card', (string) file_get_contents(APPPATH . 'Views/admin/accounts/labour_bill_create.php'));
        $this->assertStringNotContainsString('Imported Diamond Issuement Details', $profile);
        $this->assertStringContainsString('ready-thumb', $profile);
    }

    public function testAllNewPurchasesUseReusableGstMaster(): void
    {
        foreach ([
            APPPATH . 'Controllers/Admin/GoldInventory/PurchasesController.php',
            APPPATH . 'Controllers/Admin/DiamondInventory/PurchasesController.php',
            APPPATH . 'Controllers/Admin/StoneInventory/PurchasesController.php',
            APPPATH . 'Controllers/Admin/PurchaseController.php',
        ] as $path) {
            $controller = (string) file_get_contents($path);
            $this->assertStringContainsString('gst_master_id', $controller, $path);
            $this->assertStringContainsString('TaxMasterService', $controller, $path);
        }
    }
}
