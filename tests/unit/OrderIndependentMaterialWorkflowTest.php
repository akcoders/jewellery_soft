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

    public function testStoneIssuesAreOrderWiseWhileGoldStaysFreeFlow(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/StoneInventory/StoneIssueOrderService.php');
        $stoneForm = (string) file_get_contents(APPPATH . 'Views/admin/stone_inventory/issues/form.php');
        $stoneController = (string) file_get_contents(APPPATH . 'Controllers/Admin/StoneInventory/IssuesController.php');
        $combinedForm = (string) file_get_contents(APPPATH . 'Views/admin/issuements/create.php');
        $combinedController = (string) file_get_contents(APPPATH . 'Controllers/Admin/IssuementController.php');
        $mobile = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $approval = (string) file_get_contents(APPPATH . 'Services/MobileApprovalService.php');
        $goldForm = (string) file_get_contents(APPPATH . 'Views/admin/gold_inventory/issues/form.php');

        $this->assertStringContainsString('Select the order this stone is issued for.', $service);
        $this->assertStringContainsString('name="stone_order_id" id="stone_order_id" class="form-select js-select2" required', $stoneForm);
        $this->assertStringContainsString("'order_id' => (int) \$this->request->getPost('stone_order_id')", $stoneController);
        $this->assertStringContainsString('name="stone_order_id"', $combinedForm);
        $this->assertStringContainsString("'order_id' => \$stoneOrderId", $combinedController);
        $this->assertStringContainsString('resolveStoneOrder($payload, true)', $mobile);
        $this->assertStringContainsString("array_merge(\$common, ['order_id' => \$stoneOrderId])", $approval);
        // Gold is free-flow stock and is never tied to an order.
        $this->assertStringContainsString("array_merge(\$common, ['order_id' => null])", $approval);
        $this->assertStringNotContainsString('order_id', $goldForm);
    }

    public function testReceivedOrdersBlockDiamondAndStoneIssuementButNotGold(): void
    {
        $diamondGuard = (string) file_get_contents(APPPATH . 'Services/DiamondIssueOrderService.php');
        $stoneGuard = (string) file_get_contents(APPPATH . 'Services/StoneInventory/StoneIssueOrderService.php');
        $diamondAdmin = (string) file_get_contents(APPPATH . 'Controllers/Admin/DiamondInventory/IssuesController.php');
        $combinedAdmin = (string) file_get_contents(APPPATH . 'Controllers/Admin/IssuementController.php');
        $mobile = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/TransactionsController.php');
        $approval = (string) file_get_contents(APPPATH . 'Services/MobileApprovalService.php');

        $this->assertStringContainsString('hasRecordedReceipt($orderId)', $diamondGuard);
        $this->assertStringContainsString('Ornament already received; diamond or stone issuance for this order is not possible.', $diamondGuard);
        $this->assertStringContainsString('hasRecordedReceipt($orderId)', $stoneGuard);
        $this->assertStringContainsString('DiamondIssueOrderService', $diamondAdmin);
        $this->assertStringContainsString('DiamondIssueOrderService', $combinedAdmin);
        $this->assertStringContainsString('DiamondIssueOrderService', $mobile);
        $this->assertStringContainsString('DiamondIssueOrderService', $approval);
        $this->assertStringContainsString("array_merge(\$common, ['order_id' => null])", $approval);
    }

    public function testMobileReadyDocumentsRequireRecordedReceiving(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/OrdersController.php');

        $this->assertStringContainsString('$documents = $this->documentLinks($order, $isReceived)', $controller);
        $this->assertStringContainsString('$eligible = $isReceived && in_array($status', $controller);
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
        $this->assertStringContainsString('Pure Gold Account', $karigarProfile);
        $this->assertStringContainsString('Diamond Account', $karigarProfile);
        $this->assertStringContainsString('Stone Account', $karigarProfile);
        $this->assertStringContainsString('Labour & Payment Account', $karigarProfile);
        $this->assertStringContainsString('Material given', $karigarProfile);
        $this->assertStringContainsString('Material returned', $karigarProfile);
        $this->assertStringContainsString('Closing with karigar', $karigarProfile);
        $this->assertStringNotContainsString('Gold Ledger Stats', $karigarProfile);

        $this->assertStringContainsString('name="stone_item_id[]"', $orderList);
        $this->assertStringContainsString('name="stone_item_id[]"', $orderDetail);
        $this->assertStringContainsString('js-stone-inventory-select', $orderList);
        $this->assertStringContainsString('js-stone-inventory-select', $orderDetail);
        $this->assertStringContainsString('js-diamond-balance-select', $orderList);
        $this->assertStringContainsString('js-diamond-balance-select', $orderDetail);
        $this->assertStringContainsString("admin_can('orders.receive') && (\$order['status'] ?? '') === 'Ready'", $orderList);

        $this->assertStringContainsString('backflushReceivedStone(', $controller);
        $this->assertStringContainsString('validateReceivedDiamondSelection(', $controller);
        $this->assertStringContainsString('karigarDiamondOptions(', $controller);
        $this->assertStringContainsString('stoneReceiptShortfall(', $controller);
        $this->assertStringContainsString('applyReceiptBackflushIssue($issueId)', $controller);
        $this->assertStringContainsString("postInventoryHeader('stone', 'issue', \$issueId)", $controller);
        $this->assertStringContainsString("'order_id' => \$orderId > 0 ? \$orderId : null", $controller);
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
