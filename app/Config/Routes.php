<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('order-request', 'PublicOrderRequestController::create');
$routes->post('order-request', 'PublicOrderRequestController::store', ['filter' => 'csrf']);

$routes->group('admin', ['filter' => 'adminGuest'], static function ($routes): void {
    $routes->get('login', 'Admin\AuthController::login');
    $routes->post('login', 'Admin\AuthController::attemptLogin');
    $routes->get('register', 'Admin\AuthController::register');
    $routes->post('register', 'Admin\AuthController::storeUser');
});

$routes->group('admin', ['filter' => 'adminAuth'], static function ($routes): void {
    $routes->get('dashboard', 'Admin\DashboardController::index', ['filter' => 'permission:dashboard.read']);
    $routes->get('logout', 'Admin\AuthController::logout');

    $routes->get('customers', 'Admin\CustomerController::index', ['filter' => 'permission:customers.read']);
    $routes->get('customers/create', 'Admin\CustomerController::create', ['filter' => 'permission:customers.create']);
    $routes->post('customers', 'Admin\CustomerController::store', ['filter' => 'permission:customers.create']);

    $routes->get('designs', 'Admin\DesignController::index', ['filter' => 'permission:masters.designs.read']);
    $routes->get('designs/create', 'Admin\DesignController::create', ['filter' => 'permission:masters.designs.manage']);
    $routes->post('designs', 'Admin\DesignController::store', ['filter' => 'permission:masters.designs.manage']);
    $routes->get('departments', 'Admin\DepartmentController::index', ['filter' => 'permission:organization.departments.read']);
    $routes->get('departments/create', 'Admin\DepartmentController::create', ['filter' => 'permission:organization.departments.manage']);
    $routes->post('departments', 'Admin\DepartmentController::store', ['filter' => 'permission:organization.departments.manage']);
    $routes->get('departments/(:num)/edit', 'Admin\DepartmentController::edit/$1', ['filter' => 'permission:organization.departments.manage']);
    $routes->post('departments/(:num)/update', 'Admin\DepartmentController::update/$1', ['filter' => 'permission:organization.departments.manage']);
    $routes->post('departments/(:num)/status', 'Admin\DepartmentController::toggleStatus/$1', ['filter' => 'permission:organization.departments.manage']);
    $routes->get('designations', 'Admin\DesignationController::index', ['filter' => 'permission:organization.designations.read']);
    $routes->get('designations/create', 'Admin\DesignationController::create', ['filter' => 'permission:organization.designations.manage']);
    $routes->post('designations', 'Admin\DesignationController::store', ['filter' => 'permission:organization.designations.manage']);
    $routes->get('designations/(:num)/edit', 'Admin\DesignationController::edit/$1', ['filter' => 'permission:organization.designations.manage']);
    $routes->post('designations/(:num)/update', 'Admin\DesignationController::update/$1', ['filter' => 'permission:organization.designations.manage']);
    $routes->post('designations/(:num)/status', 'Admin\DesignationController::toggleStatus/$1', ['filter' => 'permission:organization.designations.manage']);
    $routes->get('employees', 'Admin\EmployeeController::index', ['filter' => 'permission:organization.employees.read']);
    $routes->get('employees/create', 'Admin\EmployeeController::create', ['filter' => 'permission:organization.employees.manage']);
    $routes->post('employees', 'Admin\EmployeeController::store', ['filter' => 'permission:organization.employees.manage']);
    $routes->get('employees/(:num)/edit', 'Admin\EmployeeController::edit/$1', ['filter' => 'permission:organization.employees.manage']);
    $routes->post('employees/(:num)/update', 'Admin\EmployeeController::update/$1', ['filter' => 'permission:organization.employees.manage']);
    $routes->post('employees/(:num)/status', 'Admin\EmployeeController::toggleStatus/$1', ['filter' => 'permission:organization.employees.manage']);
    $routes->get('employee-hierarchy', 'Admin\EmployeeHierarchyController::index', ['filter' => 'permission:organization.hierarchy.read']);
    $routes->post('employee-hierarchy', 'Admin\EmployeeHierarchyController::store', ['filter' => 'permission:organization.hierarchy.manage']);
    $routes->get('showrooms', 'Admin\ShowroomController::index', ['filter' => 'permission:showroom.masters.read']);
    $routes->get('showrooms/create', 'Admin\ShowroomController::create', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showrooms', 'Admin\ShowroomController::store', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showrooms/(:num)/edit', 'Admin\ShowroomController::edit/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showrooms/(:num)/update', 'Admin\ShowroomController::update/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showrooms/(:num)/status', 'Admin\ShowroomController::toggleStatus/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showroom-counters', 'Admin\ShowroomCounterController::index', ['filter' => 'permission:showroom.masters.read']);
    $routes->get('showroom-counters/create', 'Admin\ShowroomCounterController::create', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-counters', 'Admin\ShowroomCounterController::store', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showroom-counters/(:num)/edit', 'Admin\ShowroomCounterController::edit/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-counters/(:num)/update', 'Admin\ShowroomCounterController::update/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-counters/(:num)/status', 'Admin\ShowroomCounterController::toggleStatus/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showroom-staff', 'Admin\ShowroomStaffController::index', ['filter' => 'permission:showroom.masters.read']);
    $routes->get('showroom-staff/create', 'Admin\ShowroomStaffController::create', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-staff', 'Admin\ShowroomStaffController::store', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showroom-staff/(:num)/edit', 'Admin\ShowroomStaffController::edit/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-staff/(:num)/update', 'Admin\ShowroomStaffController::update/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->post('showroom-staff/(:num)/status', 'Admin\ShowroomStaffController::toggleStatus/$1', ['filter' => 'permission:showroom.masters.manage']);
    $routes->get('showroom-stock', 'Admin\ShowroomStockController::index', ['filter' => 'permission:showroom.stock.read']);
    $routes->get('showroom-stock/transfer', 'Admin\ShowroomStockController::transferForm', ['filter' => 'permission:showroom.stock.manage']);
    $routes->post('showroom-stock/transfer', 'Admin\ShowroomStockController::transfer', ['filter' => 'permission:showroom.stock.manage']);
    $routes->get('showroom-stock/allocation', 'Admin\ShowroomStockController::allocationForm', ['filter' => 'permission:showroom.stock.manage']);
    $routes->post('showroom-stock/allocate', 'Admin\ShowroomStockController::allocate', ['filter' => 'permission:showroom.stock.manage']);
    $routes->get('showroom-stock/counter-return', 'Admin\ShowroomStockController::counterReturnForm', ['filter' => 'permission:showroom.stock.manage']);
    $routes->post('showroom-stock/counter-return', 'Admin\ShowroomStockController::counterReturn', ['filter' => 'permission:showroom.stock.manage']);
    $routes->get('showroom-stock/reservation', 'Admin\ShowroomStockController::reservationForm', ['filter' => 'permission:showroom.reservations.manage']);
    $routes->post('showroom-stock/reserve', 'Admin\ShowroomStockController::reserve', ['filter' => 'permission:showroom.reservations.manage']);
    $routes->post('showroom-stock/reservations/(:num)/release', 'Admin\ShowroomStockController::releaseReservation/$1', ['filter' => 'permission:showroom.reservations.manage']);
    $routes->get('jewellery-inventory', 'Admin\JewelleryInventoryController::index', ['filter' => 'permission:showroom.stock.read']);
    $routes->get('jewellery-inventory/image/(:num)', 'Admin\JewelleryInventoryController::image/$1', ['filter' => 'permission:showroom.stock.read']);
    $routes->post('jewellery-inventory/(:num)/close', 'Admin\JewelleryInventoryController::close/$1', ['filter' => 'permission:showroom.stock.manage']);
    $routes->get('showroom-sales', 'Admin\ShowroomSalesController::index', ['filter' => 'permission:showroom.sales.read']);
    $routes->get('showroom-sales/create', 'Admin\ShowroomSalesController::create', ['filter' => 'permission:showroom.sales.manage']);
    $routes->post('showroom-sales', 'Admin\ShowroomSalesController::store', ['filter' => 'permission:showroom.sales.manage']);
    $routes->get('showroom-sales/(:num)', 'Admin\ShowroomSalesController::show/$1', ['filter' => 'permission:showroom.sales.read']);
    $routes->get('performance/dashboard', 'Admin\PerformanceController::dashboard', ['filter' => 'permission:performance.dashboard.read']);
    $routes->get('performance/kpis', 'Admin\PerformanceController::kpis', ['filter' => 'permission:performance.kpis.read']);
    $routes->get('performance/kpis/create', 'Admin\PerformanceController::createKpi', ['filter' => 'permission:performance.kpis.manage']);
    $routes->post('performance/kpis', 'Admin\PerformanceController::storeKpi', ['filter' => 'permission:performance.kpis.manage']);
    $routes->get('performance/kpis/(:num)/edit', 'Admin\PerformanceController::editKpi/$1', ['filter' => 'permission:performance.kpis.manage']);
    $routes->post('performance/kpis/(:num)/update', 'Admin\PerformanceController::updateKpi/$1', ['filter' => 'permission:performance.kpis.manage']);
    $routes->get('performance/targets', 'Admin\PerformanceController::targets', ['filter' => 'permission:performance.targets.read']);
    $routes->get('performance/targets/create', 'Admin\PerformanceController::createTarget', ['filter' => 'permission:performance.targets.manage']);
    $routes->post('performance/targets', 'Admin\PerformanceController::storeTarget', ['filter' => 'permission:performance.targets.manage']);
    $routes->get('performance/targets/(:num)/edit', 'Admin\PerformanceController::editTarget/$1', ['filter' => 'permission:performance.targets.manage']);
    $routes->post('performance/targets/(:num)/update', 'Admin\PerformanceController::updateTarget/$1', ['filter' => 'permission:performance.targets.manage']);
    $routes->get('performance/incentives', 'Admin\PerformanceController::incentives', ['filter' => 'permission:performance.incentives.read']);
    $routes->get('performance/incentives/create', 'Admin\PerformanceController::createIncentive', ['filter' => 'permission:performance.incentives.manage']);
    $routes->post('performance/incentives', 'Admin\PerformanceController::storeIncentive', ['filter' => 'permission:performance.incentives.manage']);
    $routes->get('performance/incentives/(:num)/edit', 'Admin\PerformanceController::editIncentive/$1', ['filter' => 'permission:performance.incentives.manage']);
    $routes->post('performance/incentives/(:num)/update', 'Admin\PerformanceController::updateIncentive/$1', ['filter' => 'permission:performance.incentives.manage']);
    $routes->get('access/roles', 'Admin\Access\RolesController::index', ['filter' => 'permission:access.roles.read']);
    $routes->get('access/roles/create', 'Admin\Access\RolesController::create', ['filter' => 'permission:access.roles.manage']);
    $routes->post('access/roles', 'Admin\Access\RolesController::store', ['filter' => 'permission:access.roles.manage']);
    $routes->get('access/roles/(:num)/edit', 'Admin\Access\RolesController::edit/$1', ['filter' => 'permission:access.roles.manage']);
    $routes->post('access/roles/(:num)/update', 'Admin\Access\RolesController::update/$1', ['filter' => 'permission:access.roles.manage']);
    $routes->post('access/roles/(:num)/status', 'Admin\Access\RolesController::toggleStatus/$1', ['filter' => 'permission:access.roles.manage']);
    $routes->get('access/permissions', 'Admin\Access\PermissionsController::index', ['filter' => 'permission:access.permissions.read']);
    $routes->get('access/permissions/create', 'Admin\Access\PermissionsController::create', ['filter' => 'permission:access.permissions.manage']);
    $routes->post('access/permissions', 'Admin\Access\PermissionsController::store', ['filter' => 'permission:access.permissions.manage']);
    $routes->get('access/permissions/(:num)/edit', 'Admin\Access\PermissionsController::edit/$1', ['filter' => 'permission:access.permissions.manage']);
    $routes->post('access/permissions/(:num)/update', 'Admin\Access\PermissionsController::update/$1', ['filter' => 'permission:access.permissions.manage']);
    $routes->post('access/permissions/(:num)/status', 'Admin\Access\PermissionsController::toggleStatus/$1', ['filter' => 'permission:access.permissions.manage']);
    $routes->get('access/users', 'Admin\Access\UsersController::index', ['filter' => 'permission:access.users.read']);
    $routes->get('access/users/(:num)', 'Admin\Access\UsersController::edit/$1', ['filter' => 'permission:access.users.manage']);
    $routes->post('access/users/(:num)/update', 'Admin\Access\UsersController::update/$1', ['filter' => 'permission:access.users.manage']);
    $routes->get('karigars', 'Admin\KarigarController::index', ['filter' => 'permission:masters.karigars.read']);
    $routes->get('karigars/create', 'Admin\KarigarController::create', ['filter' => 'permission:masters.karigars.manage']);
    $routes->post('karigars', 'Admin\KarigarController::store', ['filter' => 'permission:masters.karigars.manage']);
    $routes->get('karigars/(:num)/edit', 'Admin\KarigarController::edit/$1', ['filter' => 'permission:masters.karigars.manage']);
    $routes->post('karigars/(:num)/update', 'Admin\KarigarController::update/$1', ['filter' => 'permission:masters.karigars.manage']);
    $routes->post('karigars/(:num)/status', 'Admin\KarigarController::updateStatus/$1', ['filter' => 'permission:masters.karigars.manage']);
    $routes->post('karigars/(:num)/payment', 'Admin\KarigarController::addPaymentEntry/$1', ['filter' => 'permission:masters.karigars.payments']);
    $routes->get('karigars/(:num)/profile', 'Admin\KarigarController::show/$1', ['filter' => 'permission:masters.karigars.read']);
    $routes->get('karigars/(:num)', 'Admin\KarigarController::show/$1', ['filter' => 'permission:masters.karigars.read']);
    $routes->get('reports', 'Admin\ReportController::index', ['filter' => 'permission:reports.read']);
    $routes->get('reports/gold-ledger', 'Admin\ReportController::goldLedger', ['filter' => 'permission:reports.read']);
    $routes->get('reports/diamond-ledger', 'Admin\ReportController::diamondLedger', ['filter' => 'permission:reports.read']);
    $routes->get('reports/karigar-performance', 'Admin\ReportController::karigarPerformance', ['filter' => 'permission:reports.read']);
    $routes->get('reports/inventory', 'Admin\ReportController::inventory', ['filter' => 'permission:reports.read']);
    $routes->get('reports/orders-analysis', 'Admin\ReportController::ordersAnalysis', ['filter' => 'permission:reports.read']);
    $routes->get('reports/transactions', 'Admin\ReportController::transactions', ['filter' => 'permission:reports.read']);
    $routes->get('reports/staff-directory', 'Admin\ReportController::staffDirectory', ['filter' => 'permission:reports.read']);
    $routes->get('reports/department-staff', 'Admin\ReportController::departmentStaff', ['filter' => 'permission:reports.read']);
    $routes->get('reports/designation-staff', 'Admin\ReportController::designationStaff', ['filter' => 'permission:reports.read']);
    $routes->get('reports/staff-hierarchy', 'Admin\ReportController::staffHierarchy', ['filter' => 'permission:reports.read']);
    $routes->get('accounts', 'Admin\AccountsController::dashboard', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/general-ledger', 'Admin\AccountsController::generalLedger', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/vendor-transaction-ledger', 'Admin\AccountsController::vendorTransactionLedger', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/journal-vouchers', 'Admin\AccountsController::journalVouchers', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/journal-vouchers', 'Admin\AccountsController::storeJournalVoucher', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/party-balances/(:segment)', 'Admin\AccountsController::partyBalances/$1', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/party-ledger/(:segment)/(:num)', 'Admin\AccountsController::partyLedger/$1/$2', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/purchase-bills', 'Admin\AccountsController::purchaseBills', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/production-document/(:num)', 'Admin\ProductionImportController::document/$1', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/production-purchase-register', 'Admin\ProductionImportController::purchaseRegister', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/purchase-bills/payment', 'Admin\AccountsController::updatePurchaseBillPayment', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/labour-bills', 'Admin\AccountsController::labourBills', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/labour-bills/payment', 'Admin\AccountsController::updateLabourBillPayment', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/labour-ledger', 'Admin\AccountsController::labourLedger', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/payments', 'Admin\AccountsController::payments', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/payments', 'Admin\AccountsController::storePayment', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/sale-bills', 'Admin\AccountsController::saleBills', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/debit-notes', 'Admin\AccountsController::debitNotes', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/debit-notes', 'Admin\AccountsController::storeDebitNote', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/credit-notes', 'Admin\AccountsController::creditNotes', ['filter' => 'permission:accounts.read']);
    $routes->post('accounts/credit-notes', 'Admin\AccountsController::storeCreditNote', ['filter' => 'permission:accounts.payments']);
    $routes->get('accounts/gst-report', 'Admin\AccountsController::gstReport', ['filter' => 'permission:accounts.read']);
    $routes->get('accounts/outstanding-summary', 'Admin\AccountsController::outstandingSummary', ['filter' => 'permission:accounts.read']);
    $routes->get('vendors', 'Admin\VendorController::index', ['filter' => 'permission:masters.vendors.read']);
    $routes->post('vendors', 'Admin\VendorController::store', ['filter' => 'permission:masters.vendors.manage']);
    $routes->get('company-settings', 'Admin\CompanySettingsController::index', ['filter' => 'permission:company-settings.read']);
    $routes->post('company-settings', 'Admin\CompanySettingsController::update', ['filter' => 'permission:company-settings.manage']);
    $routes->get('system/database-update', 'Admin\DatabaseUpdateController::index', ['filter' => 'permission:company-settings.manage']);
    $routes->post('system/database-update', 'Admin\DatabaseUpdateController::run', ['filter' => 'permission:company-settings.manage']);
    $routes->get('system/production-import', 'Admin\ProductionImportController::index', ['filter' => 'permission:company-settings.manage']);
    $routes->post('system/production-import', 'Admin\ProductionImportController::import', ['filter' => 'permission:company-settings.manage']);
    $routes->get('system/production-import/document/(:num)', 'Admin\ProductionImportController::document/$1', ['filter' => 'permission:company-settings.manage']);
    $routes->get('purchases', 'Admin\PurchaseController::index', ['filter' => 'permission:gold.inventory.read,stone.inventory.read']);
    $routes->get('purchases/gold/create', 'Admin\PurchaseController::createGold', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('purchases/gold', 'Admin\PurchaseController::storeGold', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('purchases/stone/create', 'Admin\PurchaseController::createStone', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('purchases/stone', 'Admin\PurchaseController::storeStone', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('purchases/create', 'Admin\PurchaseController::create', ['filter' => 'permission:gold.inventory.manage,stone.inventory.manage']);
    $routes->post('purchases', 'Admin\PurchaseController::store', ['filter' => 'permission:gold.inventory.manage,stone.inventory.manage']);
    $routes->get('inventory', 'Admin\InventoryController::index', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/stock', 'Admin\InventoryController::stock', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/warehouses', 'Admin\InventoryController::warehouses', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/warehouses/create', 'Admin\InventoryController::createWarehouse', ['filter' => 'permission:inventory.settings.manage']);
    $routes->get('inventory/adjustments', 'Admin\InventoryController::adjustments', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/adjustments/create', 'Admin\InventoryController::createAdjustment', ['filter' => 'permission:inventory.settings.manage']);
    $routes->get('inventory/transactions', 'Admin\InventoryController::transactions', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/categories', 'Admin\InventoryController::categories', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/categories/create', 'Admin\InventoryController::createCategory', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/categories', 'Admin\InventoryController::storeCategory', ['filter' => 'permission:inventory.settings.manage']);
    $routes->get('inventory/categories/(:num)/edit', 'Admin\InventoryController::editCategory/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/categories/(:num)/update', 'Admin\InventoryController::updateCategory/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/categories/(:num)/delete', 'Admin\InventoryController::deleteCategory/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->get('inventory/products', 'Admin\InventoryController::products', ['filter' => 'permission:inventory.settings.read']);
    $routes->get('inventory/products/create', 'Admin\InventoryController::createProduct', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/products', 'Admin\InventoryController::storeProduct', ['filter' => 'permission:inventory.settings.manage']);
    $routes->get('inventory/products/(:num)/edit', 'Admin\InventoryController::editProduct/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/products/(:num)/update', 'Admin\InventoryController::updateProduct/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/products/(:num)/delete', 'Admin\InventoryController::deleteProduct/$1', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/locations', 'Admin\InventoryController::addLocation', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/bins', 'Admin\InventoryController::addBin', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/adjust', 'Admin\InventoryController::adjust', ['filter' => 'permission:inventory.settings.manage']);
    $routes->post('inventory/transfer', 'Admin\InventoryController::transfer', ['filter' => 'permission:inventory.settings.manage']);

    $routes->get('issuements', 'Admin\IssuementController::index', ['filter' => 'permission:issuements.read']);
    $routes->get('issuements/create', 'Admin\IssuementController::create', ['filter' => 'permission:issuements.create']);
    $routes->get('issuements/view/(:segment)', 'Admin\IssuementController::show/$1', ['filter' => 'permission:issuements.read']);
    $routes->get('issuements/voucher/(:segment)', 'Admin\IssuementController::voucher/$1', ['filter' => 'permission:issuements.print']);
    $routes->post('issuements', 'Admin\IssuementController::store', ['filter' => 'permission:issuements.create']);

    $routes->get('diamond-inventory/items', 'Admin\DiamondInventory\ItemsController::index', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/items/create', 'Admin\DiamondInventory\ItemsController::create', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/items', 'Admin\DiamondInventory\ItemsController::store', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->get('diamond-inventory/items/(:num)/edit', 'Admin\DiamondInventory\ItemsController::edit/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/items/(:num)/update', 'Admin\DiamondInventory\ItemsController::update/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/items/(:num)/delete', 'Admin\DiamondInventory\ItemsController::delete/$1', ['filter' => 'permission:diamond.inventory.manage']);

    $routes->get('diamond-inventory/purchases', 'Admin\DiamondInventory\PurchasesController::index', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/purchases/create', 'Admin\DiamondInventory\PurchasesController::create', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/purchases', 'Admin\DiamondInventory\PurchasesController::store', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->get('diamond-inventory/purchases/view/(:num)', 'Admin\DiamondInventory\PurchasesController::view/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/purchases/(:num)/edit', 'Admin\DiamondInventory\PurchasesController::edit/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/purchases/(:num)/update', 'Admin\DiamondInventory\PurchasesController::update/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/purchases/(:num)/delete', 'Admin\DiamondInventory\PurchasesController::delete/$1', ['filter' => 'permission:diamond.inventory.manage']);

    $routes->get('diamond-inventory/issues', 'Admin\DiamondInventory\IssuesController::index', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/issues/create', 'Admin\DiamondInventory\IssuesController::create', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/issues', 'Admin\DiamondInventory\IssuesController::store', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->get('diamond-inventory/issues/view/(:num)', 'Admin\DiamondInventory\IssuesController::view/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/issues/voucher/(:num)', 'Admin\DiamondInventory\IssuesController::voucher/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/issues/(:num)/edit', 'Admin\DiamondInventory\IssuesController::edit/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/issues/(:num)/update', 'Admin\DiamondInventory\IssuesController::update/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/issues/(:num)/delete', 'Admin\DiamondInventory\IssuesController::delete/$1', ['filter' => 'permission:diamond.inventory.manage']);

    $routes->get('diamond-inventory/returns', 'Admin\DiamondInventory\ReturnsController::index', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/returns/create', 'Admin\DiamondInventory\ReturnsController::create', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/returns', 'Admin\DiamondInventory\ReturnsController::store', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->get('diamond-inventory/returns/view/(:num)', 'Admin\DiamondInventory\ReturnsController::view/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/returns/receipt/(:num)', 'Admin\DiamondInventory\ReturnsController::receipt/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/returns/(:num)/edit', 'Admin\DiamondInventory\ReturnsController::edit/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/returns/(:num)/update', 'Admin\DiamondInventory\ReturnsController::update/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/returns/(:num)/delete', 'Admin\DiamondInventory\ReturnsController::delete/$1', ['filter' => 'permission:diamond.inventory.manage']);

    $routes->get('diamond-inventory/adjustments', 'Admin\DiamondInventory\AdjustmentsController::index', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/adjustments/create', 'Admin\DiamondInventory\AdjustmentsController::create', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/adjustments', 'Admin\DiamondInventory\AdjustmentsController::store', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->get('diamond-inventory/adjustments/view/(:num)', 'Admin\DiamondInventory\AdjustmentsController::view/$1', ['filter' => 'permission:diamond.inventory.read']);
    $routes->get('diamond-inventory/adjustments/(:num)/edit', 'Admin\DiamondInventory\AdjustmentsController::edit/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/adjustments/(:num)/update', 'Admin\DiamondInventory\AdjustmentsController::update/$1', ['filter' => 'permission:diamond.inventory.manage']);
    $routes->post('diamond-inventory/adjustments/(:num)/delete', 'Admin\DiamondInventory\AdjustmentsController::delete/$1', ['filter' => 'permission:diamond.inventory.manage']);

    $routes->get('diamond-inventory/stock', 'Admin\DiamondInventory\StockController::index', ['filter' => 'permission:diamond.inventory.read']);

    $routes->get('stone-inventory/items', 'Admin\StoneInventory\ItemsController::index', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/items/create', 'Admin\StoneInventory\ItemsController::create', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/items', 'Admin\StoneInventory\ItemsController::store', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('stone-inventory/items/(:num)/edit', 'Admin\StoneInventory\ItemsController::edit/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/items/(:num)/update', 'Admin\StoneInventory\ItemsController::update/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/items/(:num)/delete', 'Admin\StoneInventory\ItemsController::delete/$1', ['filter' => 'permission:stone.inventory.manage']);

    $routes->get('stone-inventory/purchases', 'Admin\StoneInventory\PurchasesController::index', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/purchases/create', 'Admin\StoneInventory\PurchasesController::create', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/purchases', 'Admin\StoneInventory\PurchasesController::store', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('stone-inventory/purchases/view/(:num)', 'Admin\StoneInventory\PurchasesController::view/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/purchases/(:num)/edit', 'Admin\StoneInventory\PurchasesController::edit/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/purchases/(:num)/update', 'Admin\StoneInventory\PurchasesController::update/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/purchases/(:num)/delete', 'Admin\StoneInventory\PurchasesController::delete/$1', ['filter' => 'permission:stone.inventory.manage']);

    $routes->get('stone-inventory/issues', 'Admin\StoneInventory\IssuesController::index', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/issues/create', 'Admin\StoneInventory\IssuesController::create', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/issues', 'Admin\StoneInventory\IssuesController::store', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('stone-inventory/issues/view/(:num)', 'Admin\StoneInventory\IssuesController::view/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/issues/voucher/(:num)', 'Admin\StoneInventory\IssuesController::voucher/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/issues/(:num)/edit', 'Admin\StoneInventory\IssuesController::edit/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/issues/(:num)/update', 'Admin\StoneInventory\IssuesController::update/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/issues/(:num)/delete', 'Admin\StoneInventory\IssuesController::delete/$1', ['filter' => 'permission:stone.inventory.manage']);

    $routes->get('stone-inventory/returns', 'Admin\StoneInventory\ReturnsController::index', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/returns/create', 'Admin\StoneInventory\ReturnsController::create', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/returns', 'Admin\StoneInventory\ReturnsController::store', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('stone-inventory/returns/view/(:num)', 'Admin\StoneInventory\ReturnsController::view/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/returns/receipt/(:num)', 'Admin\StoneInventory\ReturnsController::receipt/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/returns/(:num)/edit', 'Admin\StoneInventory\ReturnsController::edit/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/returns/(:num)/update', 'Admin\StoneInventory\ReturnsController::update/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/returns/(:num)/delete', 'Admin\StoneInventory\ReturnsController::delete/$1', ['filter' => 'permission:stone.inventory.manage']);

    $routes->get('stone-inventory/adjustments', 'Admin\StoneInventory\AdjustmentsController::index', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/adjustments/create', 'Admin\StoneInventory\AdjustmentsController::create', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/adjustments', 'Admin\StoneInventory\AdjustmentsController::store', ['filter' => 'permission:stone.inventory.manage']);
    $routes->get('stone-inventory/adjustments/view/(:num)', 'Admin\StoneInventory\AdjustmentsController::view/$1', ['filter' => 'permission:stone.inventory.read']);
    $routes->get('stone-inventory/adjustments/(:num)/edit', 'Admin\StoneInventory\AdjustmentsController::edit/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/adjustments/(:num)/update', 'Admin\StoneInventory\AdjustmentsController::update/$1', ['filter' => 'permission:stone.inventory.manage']);
    $routes->post('stone-inventory/adjustments/(:num)/delete', 'Admin\StoneInventory\AdjustmentsController::delete/$1', ['filter' => 'permission:stone.inventory.manage']);

    $routes->get('stone-inventory/stock', 'Admin\StoneInventory\StockController::index', ['filter' => 'permission:stone.inventory.read']);

    $routes->get('gold-inventory/purchases', 'Admin\GoldInventory\PurchasesController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/purchases/create', 'Admin\GoldInventory\PurchasesController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purchases', 'Admin\GoldInventory\PurchasesController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/purchases/view/(:num)', 'Admin\GoldInventory\PurchasesController::view/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/purchases/(:num)/edit', 'Admin\GoldInventory\PurchasesController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purchases/(:num)/update', 'Admin\GoldInventory\PurchasesController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purchases/(:num)/delete', 'Admin\GoldInventory\PurchasesController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);

    $routes->get('gold-inventory/issues', 'Admin\GoldInventory\IssuesController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/issues/create', 'Admin\GoldInventory\IssuesController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/issues', 'Admin\GoldInventory\IssuesController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/issues/view/(:num)', 'Admin\GoldInventory\IssuesController::view/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/issues/voucher/(:num)', 'Admin\GoldInventory\IssuesController::voucher/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/issues/(:num)/edit', 'Admin\GoldInventory\IssuesController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/issues/(:num)/update', 'Admin\GoldInventory\IssuesController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/issues/(:num)/delete', 'Admin\GoldInventory\IssuesController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);

    $routes->get('gold-inventory/returns', 'Admin\GoldInventory\ReturnsController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/returns/create', 'Admin\GoldInventory\ReturnsController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/returns', 'Admin\GoldInventory\ReturnsController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/returns/view/(:num)', 'Admin\GoldInventory\ReturnsController::view/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/returns/receipt/(:num)', 'Admin\GoldInventory\ReturnsController::receipt/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/returns/(:num)/edit', 'Admin\GoldInventory\ReturnsController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/returns/(:num)/update', 'Admin\GoldInventory\ReturnsController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/returns/(:num)/delete', 'Admin\GoldInventory\ReturnsController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);

    $routes->get('gold-inventory/adjustments', 'Admin\GoldInventory\AdjustmentsController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/adjustments/create', 'Admin\GoldInventory\AdjustmentsController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/adjustments', 'Admin\GoldInventory\AdjustmentsController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/adjustments/view/(:num)', 'Admin\GoldInventory\AdjustmentsController::view/$1', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/adjustments/(:num)/edit', 'Admin\GoldInventory\AdjustmentsController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/adjustments/(:num)/update', 'Admin\GoldInventory\AdjustmentsController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/adjustments/(:num)/delete', 'Admin\GoldInventory\AdjustmentsController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);

    $routes->get('gold-inventory/stock', 'Admin\GoldInventory\StockController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/ledger', 'Admin\GoldInventory\LedgerController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/purities', 'Admin\GoldInventory\PurityController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/purities/create', 'Admin\GoldInventory\PurityController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purities', 'Admin\GoldInventory\PurityController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/purities/(:num)/edit', 'Admin\GoldInventory\PurityController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purities/(:num)/update', 'Admin\GoldInventory\PurityController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/purities/(:num)/delete', 'Admin\GoldInventory\PurityController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/products', 'Admin\GoldInventory\ProductsController::index', ['filter' => 'permission:gold.inventory.read']);
    $routes->get('gold-inventory/products/create', 'Admin\GoldInventory\ProductsController::create', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/products', 'Admin\GoldInventory\ProductsController::store', ['filter' => 'permission:gold.inventory.manage']);
    $routes->get('gold-inventory/products/(:num)/edit', 'Admin\GoldInventory\ProductsController::edit/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/products/(:num)/update', 'Admin\GoldInventory\ProductsController::update/$1', ['filter' => 'permission:gold.inventory.manage']);
    $routes->post('gold-inventory/products/(:num)/delete', 'Admin\GoldInventory\ProductsController::delete/$1', ['filter' => 'permission:gold.inventory.manage']);

    $routes->get('orders', 'Admin\OrderController::index', ['filter' => 'permission:orders.read']);
    $routes->get('orders/dashboard', 'Admin\OrderController::dashboard', ['filter' => 'permission:orders.read']);
    $routes->get('orders/followups', 'Admin\OrderController::followups', ['filter' => 'permission:orders.read']);
    $routes->get('orders/fresh', 'Admin\OrderController::fresh', ['filter' => 'permission:orders.read']);
    $routes->get('orders/ready', 'Admin\OrderController::ready', ['filter' => 'permission:orders.read']);
    $routes->get('orders/repair', 'Admin\OrderController::repair', ['filter' => 'permission:orders.read']);
    $routes->get('orders/create', 'Admin\OrderController::create', ['filter' => 'permission:orders.create']);
    $routes->get('orders/repair/create', 'Admin\OrderController::createRepair', ['filter' => 'permission:orders.create']);
    $routes->post('orders', 'Admin\OrderController::store', ['filter' => 'permission:orders.create']);
    $routes->get('orders/(:num)/edit', 'Admin\OrderController::edit/$1', ['filter' => 'permission:orders.edit']);
    $routes->post('orders/(:num)/update', 'Admin\OrderController::update/$1', ['filter' => 'permission:orders.edit']);
    $routes->get('orders/ready-image/(:num)', 'Admin\JewelleryInventoryController::image/$1', ['filter' => 'permission:orders.read']);
    $routes->get('orders/(:num)/timeline', 'Admin\OrderController::timeline/$1', ['filter' => 'permission:orders.read']);
    $routes->get('orders/(:num)', 'Admin\OrderController::show/$1', ['filter' => 'permission:orders.read']);
    $routes->post('orders/(:num)/status', 'Admin\OrderController::updateStatus/$1', ['filter' => 'permission:orders.status']);
    $routes->post('orders/(:num)/cancel', 'Admin\OrderController::cancel/$1', ['filter' => 'permission:orders.status']);
    $routes->post('orders/(:num)/followups', 'Admin\OrderController::addFollowup/$1', ['filter' => 'permission:orders.followup']);
    $routes->post('orders/(:num)/attachments', 'Admin\OrderController::addAttachment/$1', ['filter' => 'permission:orders.documents']);
    $routes->post('orders/(:num)/receive', 'Admin\OrderController::addReceive/$1', ['filter' => 'permission:orders.receive']);
    $routes->post('orders/(:num)/assign', 'Admin\OrderController::assignKarigar/$1', ['filter' => 'permission:orders.assign']);
    $routes->get('orders/(:num)/packing-list/generate', 'Admin\OrderController::generatePackingList/$1', ['filter' => 'permission:orders.documents']);
    $routes->get('orders/(:num)/packing-list/html', 'Admin\OrderController::packingListHtml/$1', ['filter' => 'permission:orders.documents']);
    $routes->get('orders/(:num)/ornament-details', 'Admin\OrderController::ornamentDetails/$1', ['filter' => 'permission:orders.documents']);
    $routes->get('orders/(:num)/delivery-challan', 'Admin\OrderController::deliveryChallan/$1', ['filter' => 'permission:orders.documents']);
    $routes->post('orders/(:num)/finish-photo', 'Admin\OrderController::uploadFinishPhoto/$1', ['filter' => 'permission:orders.documents']);
    $routes->get('karigars/(:num)/summary', 'Admin\OrderController::karigarSummary/$1', ['filter' => 'permission:orders.assign']);
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
    $routes->get('documents/labour-bill/(:num)', 'Api\DocumentsController::labourBill/$1');
    $routes->get('documents/orders/(:num)/packing-list', 'Api\DocumentsController::packingListByOrder/$1');
    $routes->get('documents/orders/(:num)/delivery-challan', 'Api\DocumentsController::deliveryChallan/$1');
    $routes->get('documents/mobile/diamond/issues/(:num)', 'Api\DocumentsController::mobileDiamondIssue/$1');
    $routes->get('documents/mobile/diamond/returns/(:num)', 'Api\DocumentsController::mobileDiamondReturn/$1');
    $routes->get('documents/mobile/gold/issues/(:num)', 'Api\DocumentsController::mobileGoldIssue/$1');
    $routes->get('documents/mobile/gold/returns/(:num)', 'Api\DocumentsController::mobileGoldReturn/$1');
    $routes->get('documents/mobile/stone/issues/(:num)', 'Api\DocumentsController::mobileStoneIssue/$1');
    $routes->get('documents/mobile/stone/returns/(:num)', 'Api\DocumentsController::mobileStoneReturn/$1');
    $routes->get('documents/invoice/(:num)', 'Api\DocumentsController::invoice/$1');
    $routes->get('documents/ledger/(:num)', 'Api\DocumentsController::ledgerStatement/$1');

    $routes->post('demo/full-flow', 'Api\DemoController::run');

    $routes->group('mobile', static function ($routes): void {
        $routes->post('login', 'Api\Mobile\AuthController::login');
        $routes->get('me', 'Api\Mobile\AuthController::me');
        $routes->post('logout', 'Api\Mobile\AuthController::logout');
        $routes->get('tasks', 'Api\Mobile\TasksController::index');
        $routes->post('tasks', 'Api\Mobile\TasksController::create');
        $routes->post('tasks/(:num)/delete', 'Api\Mobile\TasksController::delete/$1');
        $routes->get('notifications', 'Api\Mobile\NotificationsController::index');
        $routes->post('notifications/(:num)/done', 'Api\Mobile\NotificationsController::done/$1');

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

        $routes->get('stone/issues', 'Api\Mobile\InventoryController::stoneIssues');
        $routes->get('stone/returns', 'Api\Mobile\InventoryController::stoneReturns');
        $routes->get('stone/purchases', 'Api\Mobile\InventoryController::stonePurchases');

        $routes->get('lookups/karigars', 'Api\Mobile\LookupsController::karigars');
        $routes->get('lookups/vendors', 'Api\Mobile\LookupsController::vendors');
        $routes->get('lookups/locations', 'Api\Mobile\LookupsController::locations');
        $routes->get('lookups/diamond-items', 'Api\Mobile\LookupsController::diamondItems');
        $routes->get('lookups/gold-items', 'Api\Mobile\LookupsController::goldItems');
        $routes->get('lookups/stone-items', 'Api\Mobile\LookupsController::stoneItems');
        $routes->get('lookups/diamond-issues', 'Api\Mobile\LookupsController::diamondIssues');
        $routes->get('lookups/gold-issues', 'Api\Mobile\LookupsController::goldIssues');
        $routes->get('lookups/stone-issues', 'Api\Mobile\LookupsController::stoneIssues');

        $routes->post('diamond/purchases', 'Api\Mobile\TransactionsController::createDiamondPurchase');
        $routes->get('diamond/purchases/(:num)', 'Api\Mobile\TransactionsController::diamondPurchaseDetail/$1');
        $routes->post('diamond/issues', 'Api\Mobile\TransactionsController::createDiamondIssue');
        $routes->get('diamond/issues/(:num)', 'Api\Mobile\TransactionsController::diamondIssueDetail/$1');
        $routes->get('diamond/issues/(:num)/pdf', 'Api\Mobile\TransactionsController::diamondIssuePdf/$1');
        $routes->post('diamond/returns', 'Api\Mobile\TransactionsController::createDiamondReturn');
        $routes->get('diamond/returns/(:num)', 'Api\Mobile\TransactionsController::diamondReturnDetail/$1');
        $routes->get('diamond/returns/(:num)/pdf', 'Api\Mobile\TransactionsController::diamondReturnPdf/$1');

        $routes->post('gold/purchases', 'Api\Mobile\TransactionsController::createGoldPurchase');
        $routes->get('gold/purchases/(:num)', 'Api\Mobile\TransactionsController::goldPurchaseDetail/$1');
        $routes->post('gold/issues', 'Api\Mobile\TransactionsController::createGoldIssue');
        $routes->get('gold/issues/(:num)', 'Api\Mobile\TransactionsController::goldIssueDetail/$1');
        $routes->get('gold/issues/(:num)/pdf', 'Api\Mobile\TransactionsController::goldIssuePdf/$1');
        $routes->post('gold/returns', 'Api\Mobile\TransactionsController::createGoldReturn');
        $routes->get('gold/returns/(:num)', 'Api\Mobile\TransactionsController::goldReturnDetail/$1');
        $routes->get('gold/returns/(:num)/pdf', 'Api\Mobile\TransactionsController::goldReturnPdf/$1');

        $routes->post('stone/purchases', 'Api\Mobile\TransactionsController::createStonePurchase');
        $routes->get('stone/purchases/(:num)', 'Api\Mobile\TransactionsController::stonePurchaseDetail/$1');
        $routes->post('stone/issues', 'Api\Mobile\TransactionsController::createStoneIssue');
        $routes->get('stone/issues/(:num)', 'Api\Mobile\TransactionsController::stoneIssueDetail/$1');
        $routes->get('stone/issues/(:num)/pdf', 'Api\Mobile\TransactionsController::stoneIssuePdf/$1');
        $routes->post('stone/returns', 'Api\Mobile\TransactionsController::createStoneReturn');
        $routes->get('stone/returns/(:num)', 'Api\Mobile\TransactionsController::stoneReturnDetail/$1');
        $routes->get('stone/returns/(:num)/pdf', 'Api\Mobile\TransactionsController::stoneReturnPdf/$1');
    });
});
