<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('admin', ['filter' => 'adminGuest'], static function ($routes): void {
    $routes->get('login', 'Admin\AuthController::login');
    $routes->post('login', 'Admin\AuthController::attemptLogin');
    $routes->get('register', 'Admin\AuthController::register');
    $routes->post('register', 'Admin\AuthController::storeUser');
});

$routes->group('admin', ['filter' => 'adminAuth'], static function ($routes): void {
    $routes->get('dashboard', 'Admin\DashboardController::index');
    $routes->get('logout', 'Admin\AuthController::logout');

    $routes->get('leads', 'Admin\LeadController::index');
    $routes->get('leads/create', 'Admin\LeadController::create');
    $routes->post('leads', 'Admin\LeadController::store');
    $routes->get('leads/(:num)', 'Admin\LeadController::show/$1');
    $routes->post('leads/(:num)/notes', 'Admin\LeadController::addNote/$1');
    $routes->post('leads/(:num)/followups', 'Admin\LeadController::addFollowup/$1');
    $routes->post('leads/(:num)/stage', 'Admin\LeadController::updateStage/$1');
    $routes->post('leads/(:num)/images', 'Admin\LeadController::addImage/$1');

    $routes->get('customers', 'Admin\CustomerController::index');
    $routes->get('customers/create', 'Admin\CustomerController::create');
    $routes->post('customers', 'Admin\CustomerController::store');

    $routes->get('designs', 'Admin\DesignController::index');
    $routes->get('designs/create', 'Admin\DesignController::create');
    $routes->post('designs', 'Admin\DesignController::store');
    $routes->get('karigars', 'Admin\KarigarController::index');
    $routes->get('karigars/create', 'Admin\KarigarController::create');
    $routes->post('karigars', 'Admin\KarigarController::store');
    $routes->get('karigars/(:num)/edit', 'Admin\KarigarController::edit/$1');
    $routes->post('karigars/(:num)/update', 'Admin\KarigarController::update/$1');
    $routes->post('karigars/(:num)/status', 'Admin\KarigarController::updateStatus/$1');
    $routes->post('karigars/(:num)/payment', 'Admin\KarigarController::addPaymentEntry/$1');
    $routes->get('karigars/(:num)/profile', 'Admin\KarigarController::show/$1');
    $routes->get('karigars/(:num)', 'Admin\KarigarController::show/$1');
    $routes->get('reports', 'Admin\ReportController::index');
    $routes->get('reports/gold-ledger', 'Admin\ReportController::goldLedger');
    $routes->get('reports/diamond-ledger', 'Admin\ReportController::diamondLedger');
    $routes->get('reports/karigar-performance', 'Admin\ReportController::karigarPerformance');
    $routes->get('reports/inventory', 'Admin\ReportController::inventory');
    $routes->get('accounts', 'Admin\AccountsController::purchaseBills');
    $routes->get('accounts/purchase-bills', 'Admin\AccountsController::purchaseBills');
    $routes->post('accounts/purchase-bills/payment', 'Admin\AccountsController::updatePurchaseBillPayment');
    $routes->get('accounts/labour-bills', 'Admin\AccountsController::labourBills');
    $routes->post('accounts/labour-bills/payment', 'Admin\AccountsController::updateLabourBillPayment');
    $routes->get('accounts/sale-bills', 'Admin\AccountsController::saleBills');
    $routes->get('vendors', 'Admin\VendorController::index');
    $routes->post('vendors', 'Admin\VendorController::store');
    $routes->get('company-settings', 'Admin\CompanySettingsController::index');
    $routes->post('company-settings', 'Admin\CompanySettingsController::update');
    $routes->get('purchases', 'Admin\PurchaseController::index');
    $routes->get('purchases/gold/create', 'Admin\PurchaseController::createGold');
    $routes->post('purchases/gold', 'Admin\PurchaseController::storeGold');
    $routes->get('purchases/stone/create', 'Admin\PurchaseController::createStone');
    $routes->post('purchases/stone', 'Admin\PurchaseController::storeStone');
    $routes->get('purchases/create', 'Admin\PurchaseController::create');
    $routes->post('purchases', 'Admin\PurchaseController::store');
    $routes->get('inventory', 'Admin\InventoryController::index');
    $routes->get('inventory/stock', 'Admin\InventoryController::stock');
    $routes->get('inventory/warehouses', 'Admin\InventoryController::warehouses');
    $routes->get('inventory/warehouses/create', 'Admin\InventoryController::createWarehouse');
    $routes->get('inventory/adjustments', 'Admin\InventoryController::adjustments');
    $routes->get('inventory/adjustments/create', 'Admin\InventoryController::createAdjustment');
    $routes->get('inventory/transactions', 'Admin\InventoryController::transactions');
    $routes->get('inventory/categories', 'Admin\InventoryController::categories');
    $routes->get('inventory/categories/create', 'Admin\InventoryController::createCategory');
    $routes->post('inventory/categories', 'Admin\InventoryController::storeCategory');
    $routes->get('inventory/categories/(:num)/edit', 'Admin\InventoryController::editCategory/$1');
    $routes->post('inventory/categories/(:num)/update', 'Admin\InventoryController::updateCategory/$1');
    $routes->post('inventory/categories/(:num)/delete', 'Admin\InventoryController::deleteCategory/$1');
    $routes->get('inventory/products', 'Admin\InventoryController::products');
    $routes->get('inventory/products/create', 'Admin\InventoryController::createProduct');
    $routes->post('inventory/products', 'Admin\InventoryController::storeProduct');
    $routes->get('inventory/products/(:num)/edit', 'Admin\InventoryController::editProduct/$1');
    $routes->post('inventory/products/(:num)/update', 'Admin\InventoryController::updateProduct/$1');
    $routes->post('inventory/products/(:num)/delete', 'Admin\InventoryController::deleteProduct/$1');
    $routes->post('inventory/locations', 'Admin\InventoryController::addLocation');
    $routes->post('inventory/bins', 'Admin\InventoryController::addBin');
    $routes->post('inventory/adjust', 'Admin\InventoryController::adjust');
    $routes->post('inventory/transfer', 'Admin\InventoryController::transfer');

    $routes->get('issuements', 'Admin\IssuementController::index');
    $routes->get('issuements/create', 'Admin\IssuementController::create');
    $routes->get('issuements/view/(:segment)', 'Admin\IssuementController::show/$1');
    $routes->get('issuements/voucher/(:segment)', 'Admin\IssuementController::voucher/$1');
    $routes->post('issuements', 'Admin\IssuementController::store');

    $routes->get('diamond-inventory/items', 'Admin\DiamondInventory\ItemsController::index');
    $routes->get('diamond-inventory/items/create', 'Admin\DiamondInventory\ItemsController::create');
    $routes->post('diamond-inventory/items', 'Admin\DiamondInventory\ItemsController::store');
    $routes->get('diamond-inventory/items/(:num)/edit', 'Admin\DiamondInventory\ItemsController::edit/$1');
    $routes->post('diamond-inventory/items/(:num)/update', 'Admin\DiamondInventory\ItemsController::update/$1');
    $routes->post('diamond-inventory/items/(:num)/delete', 'Admin\DiamondInventory\ItemsController::delete/$1');

    $routes->get('diamond-inventory/purchases', 'Admin\DiamondInventory\PurchasesController::index');
    $routes->get('diamond-inventory/purchases/create', 'Admin\DiamondInventory\PurchasesController::create');
    $routes->post('diamond-inventory/purchases', 'Admin\DiamondInventory\PurchasesController::store');
    $routes->get('diamond-inventory/purchases/view/(:num)', 'Admin\DiamondInventory\PurchasesController::view/$1');
    $routes->get('diamond-inventory/purchases/(:num)/edit', 'Admin\DiamondInventory\PurchasesController::edit/$1');
    $routes->post('diamond-inventory/purchases/(:num)/update', 'Admin\DiamondInventory\PurchasesController::update/$1');
    $routes->post('diamond-inventory/purchases/(:num)/delete', 'Admin\DiamondInventory\PurchasesController::delete/$1');

    $routes->get('diamond-inventory/issues', 'Admin\DiamondInventory\IssuesController::index');
    $routes->get('diamond-inventory/issues/create', 'Admin\DiamondInventory\IssuesController::create');
    $routes->post('diamond-inventory/issues', 'Admin\DiamondInventory\IssuesController::store');
    $routes->get('diamond-inventory/issues/view/(:num)', 'Admin\DiamondInventory\IssuesController::view/$1');
    $routes->get('diamond-inventory/issues/voucher/(:num)', 'Admin\DiamondInventory\IssuesController::voucher/$1');
    $routes->get('diamond-inventory/issues/(:num)/edit', 'Admin\DiamondInventory\IssuesController::edit/$1');
    $routes->post('diamond-inventory/issues/(:num)/update', 'Admin\DiamondInventory\IssuesController::update/$1');
    $routes->post('diamond-inventory/issues/(:num)/delete', 'Admin\DiamondInventory\IssuesController::delete/$1');

    $routes->get('diamond-inventory/returns', 'Admin\DiamondInventory\ReturnsController::index');
    $routes->get('diamond-inventory/returns/create', 'Admin\DiamondInventory\ReturnsController::create');
    $routes->post('diamond-inventory/returns', 'Admin\DiamondInventory\ReturnsController::store');
    $routes->get('diamond-inventory/returns/view/(:num)', 'Admin\DiamondInventory\ReturnsController::view/$1');
    $routes->get('diamond-inventory/returns/receipt/(:num)', 'Admin\DiamondInventory\ReturnsController::receipt/$1');
    $routes->get('diamond-inventory/returns/(:num)/edit', 'Admin\DiamondInventory\ReturnsController::edit/$1');
    $routes->post('diamond-inventory/returns/(:num)/update', 'Admin\DiamondInventory\ReturnsController::update/$1');
    $routes->post('diamond-inventory/returns/(:num)/delete', 'Admin\DiamondInventory\ReturnsController::delete/$1');

    $routes->get('diamond-inventory/adjustments', 'Admin\DiamondInventory\AdjustmentsController::index');
    $routes->get('diamond-inventory/adjustments/create', 'Admin\DiamondInventory\AdjustmentsController::create');
    $routes->post('diamond-inventory/adjustments', 'Admin\DiamondInventory\AdjustmentsController::store');
    $routes->get('diamond-inventory/adjustments/view/(:num)', 'Admin\DiamondInventory\AdjustmentsController::view/$1');
    $routes->get('diamond-inventory/adjustments/(:num)/edit', 'Admin\DiamondInventory\AdjustmentsController::edit/$1');
    $routes->post('diamond-inventory/adjustments/(:num)/update', 'Admin\DiamondInventory\AdjustmentsController::update/$1');
    $routes->post('diamond-inventory/adjustments/(:num)/delete', 'Admin\DiamondInventory\AdjustmentsController::delete/$1');

    $routes->get('diamond-inventory/stock', 'Admin\DiamondInventory\StockController::index');

    $routes->get('stone-inventory/items', 'Admin\StoneInventory\ItemsController::index');
    $routes->get('stone-inventory/items/create', 'Admin\StoneInventory\ItemsController::create');
    $routes->post('stone-inventory/items', 'Admin\StoneInventory\ItemsController::store');
    $routes->get('stone-inventory/items/(:num)/edit', 'Admin\StoneInventory\ItemsController::edit/$1');
    $routes->post('stone-inventory/items/(:num)/update', 'Admin\StoneInventory\ItemsController::update/$1');
    $routes->post('stone-inventory/items/(:num)/delete', 'Admin\StoneInventory\ItemsController::delete/$1');

    $routes->get('stone-inventory/purchases', 'Admin\StoneInventory\PurchasesController::index');
    $routes->get('stone-inventory/purchases/create', 'Admin\StoneInventory\PurchasesController::create');
    $routes->post('stone-inventory/purchases', 'Admin\StoneInventory\PurchasesController::store');
    $routes->get('stone-inventory/purchases/view/(:num)', 'Admin\StoneInventory\PurchasesController::view/$1');
    $routes->get('stone-inventory/purchases/(:num)/edit', 'Admin\StoneInventory\PurchasesController::edit/$1');
    $routes->post('stone-inventory/purchases/(:num)/update', 'Admin\StoneInventory\PurchasesController::update/$1');
    $routes->post('stone-inventory/purchases/(:num)/delete', 'Admin\StoneInventory\PurchasesController::delete/$1');

    $routes->get('stone-inventory/issues', 'Admin\StoneInventory\IssuesController::index');
    $routes->get('stone-inventory/issues/create', 'Admin\StoneInventory\IssuesController::create');
    $routes->post('stone-inventory/issues', 'Admin\StoneInventory\IssuesController::store');
    $routes->get('stone-inventory/issues/view/(:num)', 'Admin\StoneInventory\IssuesController::view/$1');
    $routes->get('stone-inventory/issues/voucher/(:num)', 'Admin\StoneInventory\IssuesController::voucher/$1');
    $routes->get('stone-inventory/issues/(:num)/edit', 'Admin\StoneInventory\IssuesController::edit/$1');
    $routes->post('stone-inventory/issues/(:num)/update', 'Admin\StoneInventory\IssuesController::update/$1');
    $routes->post('stone-inventory/issues/(:num)/delete', 'Admin\StoneInventory\IssuesController::delete/$1');

    $routes->get('stone-inventory/returns', 'Admin\StoneInventory\ReturnsController::index');
    $routes->get('stone-inventory/returns/create', 'Admin\StoneInventory\ReturnsController::create');
    $routes->post('stone-inventory/returns', 'Admin\StoneInventory\ReturnsController::store');
    $routes->get('stone-inventory/returns/view/(:num)', 'Admin\StoneInventory\ReturnsController::view/$1');
    $routes->get('stone-inventory/returns/receipt/(:num)', 'Admin\StoneInventory\ReturnsController::receipt/$1');
    $routes->get('stone-inventory/returns/(:num)/edit', 'Admin\StoneInventory\ReturnsController::edit/$1');
    $routes->post('stone-inventory/returns/(:num)/update', 'Admin\StoneInventory\ReturnsController::update/$1');
    $routes->post('stone-inventory/returns/(:num)/delete', 'Admin\StoneInventory\ReturnsController::delete/$1');

    $routes->get('stone-inventory/adjustments', 'Admin\StoneInventory\AdjustmentsController::index');
    $routes->get('stone-inventory/adjustments/create', 'Admin\StoneInventory\AdjustmentsController::create');
    $routes->post('stone-inventory/adjustments', 'Admin\StoneInventory\AdjustmentsController::store');
    $routes->get('stone-inventory/adjustments/view/(:num)', 'Admin\StoneInventory\AdjustmentsController::view/$1');
    $routes->get('stone-inventory/adjustments/(:num)/edit', 'Admin\StoneInventory\AdjustmentsController::edit/$1');
    $routes->post('stone-inventory/adjustments/(:num)/update', 'Admin\StoneInventory\AdjustmentsController::update/$1');
    $routes->post('stone-inventory/adjustments/(:num)/delete', 'Admin\StoneInventory\AdjustmentsController::delete/$1');

    $routes->get('stone-inventory/stock', 'Admin\StoneInventory\StockController::index');

    $routes->get('gold-inventory/purchases', 'Admin\GoldInventory\PurchasesController::index');
    $routes->get('gold-inventory/purchases/create', 'Admin\GoldInventory\PurchasesController::create');
    $routes->post('gold-inventory/purchases', 'Admin\GoldInventory\PurchasesController::store');
    $routes->get('gold-inventory/purchases/view/(:num)', 'Admin\GoldInventory\PurchasesController::view/$1');
    $routes->get('gold-inventory/purchases/(:num)/edit', 'Admin\GoldInventory\PurchasesController::edit/$1');
    $routes->post('gold-inventory/purchases/(:num)/update', 'Admin\GoldInventory\PurchasesController::update/$1');
    $routes->post('gold-inventory/purchases/(:num)/delete', 'Admin\GoldInventory\PurchasesController::delete/$1');

    $routes->get('gold-inventory/issues', 'Admin\GoldInventory\IssuesController::index');
    $routes->get('gold-inventory/issues/create', 'Admin\GoldInventory\IssuesController::create');
    $routes->post('gold-inventory/issues', 'Admin\GoldInventory\IssuesController::store');
    $routes->get('gold-inventory/issues/view/(:num)', 'Admin\GoldInventory\IssuesController::view/$1');
    $routes->get('gold-inventory/issues/voucher/(:num)', 'Admin\GoldInventory\IssuesController::voucher/$1');
    $routes->get('gold-inventory/issues/(:num)/edit', 'Admin\GoldInventory\IssuesController::edit/$1');
    $routes->post('gold-inventory/issues/(:num)/update', 'Admin\GoldInventory\IssuesController::update/$1');
    $routes->post('gold-inventory/issues/(:num)/delete', 'Admin\GoldInventory\IssuesController::delete/$1');

    $routes->get('gold-inventory/returns', 'Admin\GoldInventory\ReturnsController::index');
    $routes->get('gold-inventory/returns/create', 'Admin\GoldInventory\ReturnsController::create');
    $routes->post('gold-inventory/returns', 'Admin\GoldInventory\ReturnsController::store');
    $routes->get('gold-inventory/returns/view/(:num)', 'Admin\GoldInventory\ReturnsController::view/$1');
    $routes->get('gold-inventory/returns/receipt/(:num)', 'Admin\GoldInventory\ReturnsController::receipt/$1');
    $routes->get('gold-inventory/returns/(:num)/edit', 'Admin\GoldInventory\ReturnsController::edit/$1');
    $routes->post('gold-inventory/returns/(:num)/update', 'Admin\GoldInventory\ReturnsController::update/$1');
    $routes->post('gold-inventory/returns/(:num)/delete', 'Admin\GoldInventory\ReturnsController::delete/$1');

    $routes->get('gold-inventory/adjustments', 'Admin\GoldInventory\AdjustmentsController::index');
    $routes->get('gold-inventory/adjustments/create', 'Admin\GoldInventory\AdjustmentsController::create');
    $routes->post('gold-inventory/adjustments', 'Admin\GoldInventory\AdjustmentsController::store');
    $routes->get('gold-inventory/adjustments/view/(:num)', 'Admin\GoldInventory\AdjustmentsController::view/$1');
    $routes->get('gold-inventory/adjustments/(:num)/edit', 'Admin\GoldInventory\AdjustmentsController::edit/$1');
    $routes->post('gold-inventory/adjustments/(:num)/update', 'Admin\GoldInventory\AdjustmentsController::update/$1');
    $routes->post('gold-inventory/adjustments/(:num)/delete', 'Admin\GoldInventory\AdjustmentsController::delete/$1');

    $routes->get('gold-inventory/stock', 'Admin\GoldInventory\StockController::index');
    $routes->get('gold-inventory/ledger', 'Admin\GoldInventory\LedgerController::index');
    $routes->get('gold-inventory/purities', 'Admin\GoldInventory\PurityController::index');
    $routes->get('gold-inventory/purities/create', 'Admin\GoldInventory\PurityController::create');
    $routes->post('gold-inventory/purities', 'Admin\GoldInventory\PurityController::store');
    $routes->get('gold-inventory/purities/(:num)/edit', 'Admin\GoldInventory\PurityController::edit/$1');
    $routes->post('gold-inventory/purities/(:num)/update', 'Admin\GoldInventory\PurityController::update/$1');
    $routes->post('gold-inventory/purities/(:num)/delete', 'Admin\GoldInventory\PurityController::delete/$1');
    $routes->get('gold-inventory/products', 'Admin\GoldInventory\ProductsController::index');
    $routes->get('gold-inventory/products/create', 'Admin\GoldInventory\ProductsController::create');
    $routes->post('gold-inventory/products', 'Admin\GoldInventory\ProductsController::store');
    $routes->get('gold-inventory/products/(:num)/edit', 'Admin\GoldInventory\ProductsController::edit/$1');
    $routes->post('gold-inventory/products/(:num)/update', 'Admin\GoldInventory\ProductsController::update/$1');
    $routes->post('gold-inventory/products/(:num)/delete', 'Admin\GoldInventory\ProductsController::delete/$1');

    $routes->get('orders', 'Admin\OrderController::index');
    $routes->get('orders/followups', 'Admin\OrderController::followups');
    $routes->get('orders/fresh', 'Admin\OrderController::fresh');
    $routes->get('orders/ready', 'Admin\OrderController::ready');
    $routes->get('orders/repair', 'Admin\OrderController::repair');
    $routes->get('orders/create', 'Admin\OrderController::create');
    $routes->get('orders/repair/create', 'Admin\OrderController::createRepair');
    $routes->post('orders', 'Admin\OrderController::store');
    $routes->get('orders/(:num)/edit', 'Admin\OrderController::edit/$1');
    $routes->post('orders/(:num)/update', 'Admin\OrderController::update/$1');
    $routes->get('orders/(:num)', 'Admin\OrderController::show/$1');
    $routes->post('orders/(:num)/status', 'Admin\OrderController::updateStatus/$1');
    $routes->post('orders/(:num)/cancel', 'Admin\OrderController::cancel/$1');
    $routes->post('orders/(:num)/followups', 'Admin\OrderController::addFollowup/$1');
    $routes->post('orders/(:num)/attachments', 'Admin\OrderController::addAttachment/$1');
    $routes->post('orders/(:num)/issue', 'Admin\OrderController::addIssue/$1');
    $routes->post('orders/(:num)/receive', 'Admin\OrderController::addReceive/$1');
    $routes->post('orders/(:num)/assign', 'Admin\OrderController::assignKarigar/$1');
    $routes->get('orders/(:num)/receive-prefill', 'Admin\OrderController::receivePrefill/$1');
    $routes->get('orders/(:num)/packing-list/generate', 'Admin\OrderController::generatePackingList/$1');
    $routes->get('orders/(:num)/packing-list/html', 'Admin\OrderController::packingListHtml/$1');
    $routes->get('orders/(:num)/ornament-details', 'Admin\OrderController::ornamentDetails/$1');
    $routes->get('orders/(:num)/delivery-challan', 'Admin\OrderController::deliveryChallan/$1');
    $routes->post('orders/(:num)/finish-photo', 'Admin\OrderController::uploadFinishPhoto/$1');
    $routes->get('karigars/(:num)/summary', 'Admin\OrderController::karigarSummary/$1');
});

$routes->group('api', static function ($routes): void {
    $routes->get('orders', 'Api\OrdersController::index');
    $routes->get('orders/(:num)', 'Api\OrdersController::show/$1');
    $routes->post('orders', 'Api\OrdersController::create');
    $routes->post('orders/(:num)/status', 'Api\OrdersController::updateStatus/$1');

    $routes->post('jobcards', 'Api\JobcardsController::create');
    $routes->post('jobcards/(:num)/assign', 'Api\JobcardsController::assign/$1');
    $routes->post('jobcards/(:num)/stages', 'Api\JobcardsController::stageUpdate/$1');

    $routes->post('purchases/grn', 'Api\PurchasesController::grn');
    $routes->post('purchases/invoices', 'Api\PurchasesController::invoice');
    $routes->post('payments/vendors', 'Api\PurchasesController::vendorPayment');


    $routes->post('vouchers', 'Api\VouchersController::create');
    $routes->post('vouchers/(:num)/reverse', 'Api\VouchersController::reverse/$1');
    $routes->post('vouchers/(:num)/correct', 'Api\VouchersController::correct/$1');

    $routes->post('ornaments/receive', 'Api\OrnamentsController::receive');
    $routes->post('qc/(:num)', 'Api\QcController::check/$1');

    $routes->post('packing-lists', 'Api\PackingController::create');
    $routes->post('packing-lists/(:num)/dispatch', 'Api\PackingController::dispatch/$1');

    $routes->post('invoices', 'Api\InvoicesController::create');
    $routes->post('receipts', 'Api\InvoicesController::receipt');

    $routes->get('reports/stock-on-hand', 'Api\ReportsController::stockOnHand');
    $routes->get('reports/karigar-outstanding', 'Api\ReportsController::karigarOutstanding');
    $routes->get('reports/order-consumption', 'Api\ReportsController::orderConsumption');
    $routes->get('reports/wastage', 'Api\ReportsController::wastage');
    $routes->get('reports/bag-history', 'Api\ReportsController::bagHistory');
    $routes->get('reports/outstanding-ageing', 'Api\ReportsController::outstandingAging');
    $routes->get('reports/sql-templates', 'Api\ReportsController::sqlTemplates');

    $routes->get('documents/job-card/(:num)', 'Api\DocumentsController::jobCard/$1');
    $routes->get('documents/gold-issue/(:num)', 'Api\DocumentsController::goldIssueChallan/$1');
    $routes->get('documents/diamond-issue/(:num)', 'Api\DocumentsController::diamondIssueChallan/$1');
    $routes->get('documents/return-voucher/(:num)', 'Api\DocumentsController::returnVoucher/$1');
    $routes->get('documents/packing-list/(:num)', 'Api\DocumentsController::packingList/$1');
    $routes->get('documents/invoice/(:num)', 'Api\DocumentsController::invoice/$1');
    $routes->get('documents/ledger/(:num)', 'Api\DocumentsController::ledgerStatement/$1');

    $routes->post('demo/full-flow', 'Api\DemoController::run');

    $routes->group('mobile', static function ($routes): void {
        $routes->post('login', 'Api\Mobile\AuthController::login');
        $routes->get('me', 'Api\Mobile\AuthController::me');
        $routes->post('logout', 'Api\Mobile\AuthController::logout');

        $routes->get('orders', 'Api\Mobile\OrdersController::index');
        $routes->get('orders/(:num)', 'Api\Mobile\OrdersController::show/$1');
        $routes->get('orders/(:num)/followups', 'Api\Mobile\OrdersController::followups/$1');
        $routes->post('orders/(:num)/followups', 'Api\Mobile\OrdersController::addFollowup/$1');

        $routes->get('inventory/summary', 'Api\Mobile\InventoryController::summary');
        $routes->get('inventory/diamonds', 'Api\Mobile\InventoryController::diamonds');
        $routes->get('inventory/gold', 'Api\Mobile\InventoryController::gold');
        $routes->get('inventory/stones', 'Api\Mobile\InventoryController::stones');

        $routes->get('diamond/issues', 'Api\Mobile\InventoryController::diamondIssues');
        $routes->get('diamond/returns', 'Api\Mobile\InventoryController::diamondReturns');
        $routes->get('diamond/purchases', 'Api\Mobile\InventoryController::diamondPurchases');

        $routes->get('gold/issues', 'Api\Mobile\InventoryController::goldIssues');
        $routes->get('gold/returns', 'Api\Mobile\InventoryController::goldReturns');
        $routes->get('gold/purchases', 'Api\Mobile\InventoryController::goldPurchases');
    });
});
