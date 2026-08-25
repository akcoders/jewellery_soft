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

    public function testOrderAndPurchaseWorkspacesUseResponsiveUiAndResolvableReadyPhotos(): void
    {
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');
        $dashboard = (string) file_get_contents(APPPATH . 'Views/admin/orders/dashboard.php');
        $purchaseBills = (string) file_get_contents(APPPATH . 'Views/admin/accounts/purchase_bills.php');
        $orderDetail = (string) file_get_contents(APPPATH . 'Views/admin/orders/show.php');
        $customerLayout = (string) file_get_contents(APPPATH . 'Views/customer/layout.php');
        $customerOrders = (string) file_get_contents(APPPATH . 'Views/customer/orders/index.php');
        $inventoryController = (string) file_get_contents(APPPATH . 'Controllers/Admin/JewelleryInventoryController.php');

        $this->assertStringContainsString("renderSection('styles')", $layout);
        $this->assertStringContainsString('dataRowCount > 10', $layout);
        $this->assertStringContainsString('usePaging', $layout);
        $this->assertStringContainsString("<body class=\"<?= \$isOrders ? 'module-orders' : '' ?>\">", $layout);
        $this->assertStringContainsString('erp-mobile-scroll-table', $layout);
        $this->assertStringContainsString('erp-scroll-shell', $layout);
        $this->assertStringContainsString("if (hasTabularHeader)", $layout);
        $this->assertStringNotContainsString("addClass('erp-mobile-table')", $layout);
        $this->assertStringNotContainsString('table.erp-mobile-table tbody tr', $layout);
        $this->assertStringContainsString('portal-scroll-table', $customerLayout);
        $this->assertStringContainsString('portal-scroll-table', $customerOrders);
        $this->assertStringNotContainsString('mobile-card-table', $customerLayout . $customerOrders);
        $this->assertStringContainsString('order-workflow-panel', $dashboard);
        $this->assertStringContainsString('Schedule &amp; Karigar', $dashboard);
        $this->assertStringNotContainsString('.order-dashboard-table { min-width: 0; }', $dashboard);
        $this->assertStringContainsString('Supplier &amp; Invoice', $purchaseBills);
        $this->assertStringContainsString('Payment Position', $purchaseBills);
        $this->assertStringContainsString('data-dt-skip="true"', $orderDetail);
        $this->assertStringContainsString('Order &amp; Ready Photos', $orderDetail);
        $this->assertStringContainsString('FCPATH . $relativePath', $inventoryController);
        $this->assertStringContainsString('WRITEPATH . $relativePath', $inventoryController);
    }

    public function testKarigarLedgersAndStoneReceiptBackflushAreWired(): void
    {
        $karigarProfile = (string) file_get_contents(APPPATH . 'Views/admin/karigars/show.php');
        $orderList = (string) file_get_contents(APPPATH . 'Views/admin/orders/index.php');
        $orderDetail = (string) file_get_contents(APPPATH . 'Views/admin/orders/show.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $accounting = (string) file_get_contents(APPPATH . 'Services/KarigarMaterialAccountingService.php');
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000065_LinkReceivedStoneInventoryConsumption.php'
        );

        $this->assertGreaterThanOrEqual(5, substr_count($karigarProfile, 'karigar-ledger-table'));
        $this->assertStringContainsString('Gold Ledger (Opening / Debit / Credit / Closing)', $karigarProfile);
        $this->assertStringContainsString('Diamond Ledger (Opening / Debit / Credit / Closing)', $karigarProfile);
        $this->assertStringContainsString('Stone Ledger (Opening / Debit / Credit / Closing)', $karigarProfile);
        $this->assertStringContainsString('Payment Ledger', $karigarProfile);
        $this->assertSame(1, preg_match('/Gold Ledger Stats.*?<\/div>\s*<\/div>\s*<\/div>/s', $karigarProfile, $goldStats));
        $this->assertStringContainsString('Pure Issued:', $goldStats[0]);
        $this->assertStringContainsString('Pure Received:', $goldStats[0]);
        $this->assertStringContainsString('Pure Balance:', $goldStats[0]);
        $this->assertStringNotContainsString('<strong>Issued:</strong>', $goldStats[0]);
        $this->assertStringNotContainsString('<strong>Received:</strong>', $goldStats[0]);
        $this->assertStringNotContainsString('<strong>Balance:</strong>', $goldStats[0]);

        $this->assertStringContainsString('name="stone_item_id[]"', $orderList);
        $this->assertStringContainsString('name="stone_item_id[]"', $orderDetail);
        $this->assertStringContainsString('js-stone-inventory-select', $orderList);
        $this->assertStringContainsString('js-stone-inventory-select', $orderDetail);

        $this->assertStringContainsString('backflushReceivedStone(', $controller);
        $this->assertStringContainsString('stoneReceiptShortfall(', $controller);
        $this->assertStringContainsString('applyReceiptBackflushIssue($issueId)', $controller);
        $this->assertStringContainsString("postInventoryHeader('stone', 'issue', \$issueId)", $controller);
        $this->assertStringContainsString("'order_id' => null", $controller);
        $this->assertStringContainsString("'item_type' => 'STONE'", $accounting);
        $this->assertStringContainsString("'item_key' => 'STONE-POOL'", $accounting);

        $stoneStock = (string) file_get_contents(APPPATH . 'Services/StoneInventory/StockService.php');
        $this->assertStringContainsString('function applyReceiptBackflushIssue', $stoneStock);
        $this->assertStringContainsString('$oldQty - $issueQty', $stoneStock);

        $this->assertStringContainsString('stone_inventory_item_id', $migration);
        $this->assertStringContainsString('receive_movement_id', $migration);
        $this->assertStringContainsString('uq_stone_issue_receive_movement', $migration);

        $backfillMigration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000066_BackfillCompletedOrderStoneInventory.php'
        );
        $receiveSummaryModel = (string) file_get_contents(APPPATH . 'Models/OrderReceiveSummaryModel.php');
        $this->assertStringContainsString('unlinkedStoneMovements', $backfillMigration);
        $this->assertStringContainsString('applyReceiptBackflushIssue', $backfillMigration);
        $this->assertStringContainsString('postFinishedJewelleryReceipt', $backfillMigration);
        $this->assertStringContainsString("'order_id' => null", $backfillMigration);
        $this->assertStringContainsString('stone_account_voucher_id', $backfillMigration);
        $this->assertStringContainsString('stone_account_voucher_id', $receiveSummaryModel);
    }

    public function testUnusedKarigarCleanupIsStrictlyGuarded(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000067_RemoveUnusedKarigars.php'
        );

        $this->assertStringContainsString("['om', 'ranjan', 'sattar']", $migration);
        $this->assertStringContainsString('assertNoKarigarReferences', $migration);
        $this->assertStringContainsString('assertNoAccountTransactions', $migration);
        $this->assertStringContainsString("'orders' => 'assigned_karigar_id'", $migration);
        $this->assertStringContainsString("'labour_bills' => 'karigar_id'", $migration);
        $this->assertStringContainsString("['account_balances', 'account_id']", $migration);
        $this->assertStringContainsString("table('accounts')->whereIn('id', \$accountIds)->delete()", $migration);
        $this->assertStringContainsString("table('karigars')->where('id', \$karigarId)->delete()", $migration);
    }
}
