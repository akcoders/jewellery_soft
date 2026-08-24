<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class OrderIndependentMaterialWorkflowTest extends CIUnitTestCase
{
    public function testMaterialFormsAndRoutesDoNotRequireOrders(): void
    {
        $files = [
            APPPATH . 'Views/admin/gold_inventory/issues/form.php',
            APPPATH . 'Views/admin/gold_inventory/returns/form.php',
            APPPATH . 'Views/admin/diamond_inventory/issues/form.php',
            APPPATH . 'Views/admin/diamond_inventory/returns/form.php',
            APPPATH . 'Views/admin/stone_inventory/issues/form.php',
            APPPATH . 'Views/admin/stone_inventory/returns/form.php',
            APPPATH . 'Views/admin/issuements/create.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('name="order_id"', $source, $file);
        }

        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringNotContainsString("orders/(:num)/issue'", $routes);
        $this->assertStringNotContainsString("orders/(:num)/receive-prefill'", $routes);
        $this->assertStringNotContainsString("lookups/orders'", $routes);
        $this->assertStringContainsString("orders/(:num)/receive'", $routes);
    }

    public function testOrderDetailIsFocusedAndReceivingIsManual(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/orders/show.php');

        $this->assertStringNotContainsString('Budget Monitor', $view);
        $this->assertStringNotContainsString('Create Issue/Return', $view);
        $this->assertStringContainsString('Studded & Finished Jewellery Details', $view);
        $this->assertStringContainsString('Nothing is fetched from issuements', $view);
        $this->assertStringContainsString("'studded_diamond_rate'", $view);
        $this->assertStringContainsString("'stone_rate'", $view);

        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $accountsController = (string) file_get_contents(APPPATH . 'Controllers/Admin/AccountsController.php');
        $goldLedger = (string) file_get_contents(APPPATH . 'Controllers/Admin/GoldInventory/LedgerController.php');
        $this->assertStringNotContainsString('ih.order_id', $controller);
        $this->assertStringNotContainsString('rh.order_id', $controller);
        $this->assertStringNotContainsString("o.id = h.order_id", $accountsController);
        $this->assertStringNotContainsString('gle.order_id', $goldLedger);
    }

    public function testMobileWritesNoOrderReferenceForMaterialTransactions(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $inventoryController = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/InventoryController.php');
        $screen = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/transaction_create_screen.dart');
        $orderDetail = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/order_detail_screen.dart');

        $this->assertStringNotContainsString("payload['order_id']", $screen);
        $this->assertStringNotContainsString("(\$payload['order_id']", $controller);
        $this->assertStringNotContainsString('ih.order_id', $inventoryController);
        $this->assertStringNotContainsString('rh.order_id', $inventoryController);
        $this->assertStringNotContainsString('TransactionCreateScreen', $orderDetail);
        $this->assertStringContainsString('KarigarMaterialAccountingService', $controller);
    }
}
