<?php
$uri        = service('uri');
$segments   = $uri->getSegments();
$segment2   = (string) ($segments[1] ?? '');
$segment3   = (string) ($segments[2] ?? '');
$segment4   = (string) ($segments[3] ?? '');
$assetBasePath = ((string) ($segments[0] ?? '') === 'public') ? 'public/template/assets' : 'template/assets';
$assetBase = base_url($assetBasePath);
$isDash     = $segment2 === 'dashboard';
$isCustomers= $segment2 === 'customers';
$isOrders   = $segment2 === 'orders';
$isOrdersAll   = $isOrders && ($segment3 === '' || ctype_digit($segment3));
$isOrdersDashboard = $isOrders && $segment3 === 'dashboard';
$isOrdersFresh = $isOrders && $segment3 === 'fresh';
$isOrdersReady = $isOrders && $segment3 === 'ready';
$isOrdersRepair = $isOrders && $segment3 === 'repair';
$isOrdersFollowups = $isOrders && $segment3 === 'followups';
$isOrdersCreate = $isOrders && $segment3 === 'create';
$isOrdersRepairCreate = $isOrdersRepair && $segment4 === 'create';
$isIssuements = $segment2 === 'issuements';
$isDesigns  = $segment2 === 'designs';
$isDepartments = $segment2 === 'departments';
$isDesignations = $segment2 === 'designations';
$isEmployees = $segment2 === 'employees';
$isEmployeeHierarchy = $segment2 === 'employee-hierarchy';
$isStaffHierarchy = $isDepartments || $isDesignations || $isEmployees || $isEmployeeHierarchy;
$isShowrooms = $segment2 === 'showrooms';
$isShowroomCounters = $segment2 === 'showroom-counters';
$isShowroomStaff = $segment2 === 'showroom-staff';
$isShowroomStock = $segment2 === 'showroom-stock';
$isJewelleryInventory = $segment2 === 'jewellery-inventory';
$isShowroomSales = $segment2 === 'showroom-sales';
$isShowroomModule = $isShowrooms || $isShowroomCounters || $isShowroomStaff || $isShowroomStock || $isJewelleryInventory || $isShowroomSales;
$isPerformance = $segment2 === 'performance';
$isPerformanceDashboard = $isPerformance && ($segment3 === 'dashboard' || $segment3 === '');
$isPerformanceKpis = $isPerformance && $segment3 === 'kpis';
$isPerformanceTargets = $isPerformance && $segment3 === 'targets';
$isPerformanceIncentives = $isPerformance && $segment3 === 'incentives';
$isAccess = $segment2 === 'access';
$isAccessRoles = $isAccess && $segment3 === 'roles';
$isAccessPermissions = $isAccess && $segment3 === 'permissions';
$isAccessUsers = $isAccess && $segment3 === 'users';
$isKarigars = $segment2 === 'karigars';
$isReports  = $segment2 === 'reports';
$isReportsGoldLedger = $isReports && ($segment3 === '' || $segment3 === 'gold-ledger');
$isReportsDiamondLedger = $isReports && $segment3 === 'diamond-ledger';
$isReportsKarigarPerformance = $isReports && $segment3 === 'karigar-performance';
$isReportsInventory = $isReports && $segment3 === 'inventory';
$isReportsTransactions = $isReports && $segment3 === 'transactions';
$isReportsStaffDirectory = $isReports && $segment3 === 'staff-directory';
$isReportsDepartmentStaff = $isReports && $segment3 === 'department-staff';
$isReportsDesignationStaff = $isReports && $segment3 === 'designation-staff';
$isReportsStaffHierarchy = $isReports && $segment3 === 'staff-hierarchy';
$isAccounts = $segment2 === 'accounts';
$isAccountsDashboard = $isAccounts && $segment3 === '';
$isAccountsGeneralLedger = $isAccounts && $segment3 === 'general-ledger';
$isAccountsVendorTransactionLedger = $isAccounts && $segment3 === 'vendor-transaction-ledger';
$isAccountsJournalVouchers = $isAccounts && $segment3 === 'journal-vouchers';
$isAccountsPartyBalances = $isAccounts && $segment3 === 'party-balances';
$isAccountsPartyLedger = $isAccounts && $segment3 === 'party-ledger';
$isAccountsPurchaseBills = $isAccounts && $segment3 === 'purchase-bills';
$isAccountsLabourBills = $isAccounts && $segment3 === 'labour-bills';
$isAccountsLabourLedger = $isAccounts && $segment3 === 'labour-ledger';
$isAccountsPayments = $isAccounts && $segment3 === 'payments';
$isAccountsSaleBills = $isAccounts && $segment3 === 'sale-bills';
$isAccountsDebitNotes = $isAccounts && $segment3 === 'debit-notes';
$isAccountsCreditNotes = $isAccounts && $segment3 === 'credit-notes';
$isAccountsGstReport = $isAccounts && $segment3 === 'gst-report';
$isAccountsOutstanding = $isAccounts && $segment3 === 'outstanding-summary';
$isVendors  = $segment2 === 'vendors';
$isCompanySettings = $segment2 === 'company-settings';
$isDatabaseUpdate = $segment2 === 'system' && $segment3 === 'database-update';
$isProductionImport = $segment2 === 'system' && $segment3 === 'production-import';
$isInventory= $segment2 === 'inventory';
$isInventoryStock = $isInventory && ($segment3 === '' || $segment3 === 'stock');
$isInventoryWarehouses = $isInventory && $segment3 === 'warehouses';
$isInventoryAdjustments = $isInventory && $segment3 === 'adjustments';
$isInventoryTransactions = $isInventory && $segment3 === 'transactions';
$isInventoryCategories = $isInventory && $segment3 === 'categories';
$isInventoryProducts = $isInventory && $segment3 === 'products';
$isDiamondBags = $segment2 === 'diamond-bags';
$isDiamondInventory = $segment2 === 'diamond-inventory';
$isDiamondInventoryItems = $isDiamondInventory && $segment3 === 'items';
$isDiamondInventoryPurchases = $isDiamondInventory && $segment3 === 'purchases';
$isDiamondInventoryIssues = $isDiamondInventory && $segment3 === 'issues';
$isDiamondInventoryReturns = $isDiamondInventory && $segment3 === 'returns';
$isDiamondInventoryAdjustments = $isDiamondInventory && $segment3 === 'adjustments';
$isDiamondInventoryStock = $isDiamondInventory && $segment3 === 'stock';
$isStoneInventory = $segment2 === 'stone-inventory';
$isStoneInventoryItems = $isStoneInventory && $segment3 === 'items';
$isStoneInventoryPurchases = $isStoneInventory && $segment3 === 'purchases';
$isStoneInventoryIssues = $isStoneInventory && $segment3 === 'issues';
$isStoneInventoryReturns = $isStoneInventory && $segment3 === 'returns';
$isStoneInventoryAdjustments = $isStoneInventory && $segment3 === 'adjustments';
$isStoneInventoryStock = $isStoneInventory && $segment3 === 'stock';
$isGoldInventory = $segment2 === 'gold-inventory';
$isGoldInventoryPurchases = $isGoldInventory && $segment3 === 'purchases';
$isGoldInventoryIssues = $isGoldInventory && $segment3 === 'issues';
$isGoldInventoryReturns = $isGoldInventory && $segment3 === 'returns';
$isGoldInventoryAdjustments = $isGoldInventory && $segment3 === 'adjustments';
$isGoldInventoryStock = $isGoldInventory && $segment3 === 'stock';
$isGoldInventoryLedger = $isGoldInventory && $segment3 === 'ledger';
$isGoldInventoryPurities = $isGoldInventory && $segment3 === 'purities';
$isGoldInventoryProducts = $isGoldInventory && $segment3 === 'products';
$canDashboard = admin_can('dashboard.read');
$canCustomers = admin_can('customers.read');
$canOrders = admin_can('orders.read');
$canOrdersCreate = admin_can('orders.create');
$canIssuements = admin_can('issuements.read');
$canReports = admin_can('reports.read');
$canAccounts = admin_can('accounts.read');
$canDesigns = admin_can('masters.designs.read');
$canKarigars = admin_can('masters.karigars.read');
$canStaffHierarchy = admin_can_any(['organization.departments.read', 'organization.designations.read', 'organization.employees.read', 'organization.hierarchy.read']);
$canShowroomMasters = admin_can('showroom.masters.read');
$canShowroomStock = admin_can('showroom.stock.read');
$canShowroomSales = admin_can('showroom.sales.read');
$canPerformance = admin_can_any(['performance.dashboard.read', 'performance.kpis.read', 'performance.targets.read', 'performance.incentives.read']);
$canVendors = admin_can('masters.vendors.read');
$canCompanySettings = admin_can_any(['company-settings.read', 'company-settings.manage']);
$canInventorySettings = admin_can('inventory.settings.read');
$canDiamondInventory = admin_can('diamond.inventory.read');
$canStoneInventory = admin_can('stone.inventory.read');
$canGoldInventory = admin_can('gold.inventory.read');
$canAccessControl = admin_can_any(['access.roles.read', 'access.permissions.read', 'access.users.read']);
$canCrmOrdersMenu = $canCustomers || $canOrders;
$canProductionMenu = $canKarigars || $canIssuements || $canDesigns;
$canInventoryMenu = $canGoldInventory || $canDiamondInventory || $canStoneInventory || $canInventorySettings;
$canShowroomMenu = $canShowroomMasters || $canShowroomStock || $canShowroomSales;
$canAdminMenu = $canVendors || $canStaffHierarchy || $canPerformance || $canCompanySettings || $canAccessControl;
?>
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="light" data-sidebar-size="lg" data-sidebar-image="none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Admin') ?></title>

    <link rel="shortcut icon" href="<?= esc($assetBase) ?>/img/favicon.png">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/plugins/feather/feather.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/css/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/plugins/datatables/datatables.min.css">
    <link rel="stylesheet" href="<?= esc($assetBase) ?>/css/style.css">
    <script src="<?= esc($assetBase) ?>/js/layout.js"></script>
    <style>
        :root {
            --erp-red: #b3121f;
            --erp-red-dark: #8f0d18;
            --erp-red-soft: #fce9eb;
            --erp-gold: #c89b1e;
            --erp-gold-dark: #a67f14;
            --erp-gold-soft: #fff5d7;
            --erp-ink: #18202f;
            --erp-muted: #667085;
            --erp-border: #e6e9ef;
            --erp-bg: #f5f6f8;
            --erp-surface: #ffffff;
            --erp-shadow: 0 10px 30px rgba(31, 41, 55, 0.06);
        }
        body {
            background:
                radial-gradient(circle at 82% 5%, rgba(200, 155, 30, 0.08), transparent 24rem),
                var(--erp-bg);
            color: var(--erp-ink);
        }
        .header.header-one {
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--erp-border);
            box-shadow: 0 4px 20px rgba(31, 41, 55, 0.04);
            backdrop-filter: blur(12px);
        }
        .sidebar {
            border-right: 1px solid var(--erp-border);
            box-shadow: 8px 0 24px rgba(31, 41, 55, 0.035);
        }
        .page-wrapper {
            background: transparent;
        }
        .content.container-fluid {
            padding: 26px 28px 32px;
        }
        .page-header {
            margin-bottom: 18px;
        }
        .content-page-header {
            align-items: center;
        }
        .content-page-header h5 {
            color: var(--erp-ink);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 0;
            text-transform: uppercase;
        }
        .card {
            background: var(--erp-surface);
            border: 1px solid var(--erp-border);
            border-radius: 14px;
            box-shadow: var(--erp-shadow);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--erp-border);
            padding: 18px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .btn {
            border-radius: 9px;
            font-weight: 600;
            min-height: 38px;
            padding: 8px 15px;
            transition: transform 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .btn-sm {
            border-radius: 8px;
            min-height: 32px;
            padding: 5px 10px;
        }
        .form-control,
        .form-select,
        .select2-container--default .select2-selection--single {
            border-color: #d9dee8;
            border-radius: 9px;
            min-height: 42px;
        }
        textarea.form-control {
            min-height: auto;
        }
        .form-label {
            color: #344054;
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 7px;
        }
        .table > :not(caption) > * > * {
            border-bottom-color: #edf0f4;
            padding: 13px 14px;
            vertical-align: middle;
        }
        .table thead th {
            background: #f8f9fb;
            color: #475467;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.045em;
            text-transform: uppercase;
        }
        .table-hover > tbody > tr:hover > * {
            background: #fffaf0;
            color: var(--erp-ink);
        }
        .modal-content {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2);
        }
        .modal-header,
        .modal-footer {
            border-color: var(--erp-border);
            padding: 17px 20px;
        }
        .badge {
            border-radius: 999px;
            font-weight: 650;
            padding: 6px 10px;
        }
        .erp-page-toolbar {
            align-items: center;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid var(--erp-border);
            border-radius: 14px;
            box-shadow: 0 6px 22px rgba(31, 41, 55, 0.045);
            display: flex;
            gap: 18px;
            justify-content: space-between;
            padding: 18px 20px;
        }
        .erp-page-toolbar h4 {
            color: var(--erp-ink);
            font-size: 20px;
            font-weight: 750;
        }
        .erp-page-toolbar p {
            color: var(--erp-muted);
            font-size: 12px;
        }
        .erp-dashboard-hero {
            align-items: center;
            background:
                radial-gradient(circle at 88% 5%, rgba(255, 255, 255, 0.16), transparent 12rem),
                linear-gradient(125deg, #86101a 0%, var(--erp-red) 54%, #c58e15 150%);
            border-radius: 18px;
            box-shadow: 0 18px 42px rgba(143, 13, 24, 0.2);
            color: #fff;
            display: flex;
            justify-content: space-between;
            min-height: 166px;
            overflow: hidden;
            padding: 28px 30px;
            position: relative;
        }
        .erp-dashboard-hero h2 {
            color: #fff;
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 750;
        }
        .erp-dashboard-hero p {
            color: rgba(255, 255, 255, 0.76);
            max-width: 660px;
        }
        .erp-dashboard-hero .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.8) !important;
            color: #fff !important;
        }
        .erp-dashboard-hero .btn-outline-light:hover,
        .erp-dashboard-hero .btn-outline-light:focus {
            background: #fff !important;
            color: var(--erp-red-dark) !important;
        }
        .erp-eyebrow {
            color: var(--erp-gold-dark);
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .erp-dashboard-hero .erp-eyebrow {
            color: #f9d77a;
        }
        .erp-kpi-card {
            align-items: center;
            background: #fff;
            border: 1px solid var(--erp-border);
            border-radius: 14px;
            box-shadow: var(--erp-shadow);
            color: var(--erp-ink) !important;
            display: flex;
            gap: 15px;
            height: 100%;
            min-height: 132px;
            padding: 20px;
            position: relative;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .erp-kpi-card:hover {
            border-color: rgba(179, 18, 31, 0.24);
            box-shadow: 0 16px 36px rgba(31, 41, 55, 0.1);
            color: var(--erp-ink) !important;
            transform: translateY(-3px);
        }
        .erp-kpi-card > span:last-child {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .erp-kpi-card small {
            color: var(--erp-muted);
            font-size: 12px;
            font-style: normal;
            font-weight: 650;
        }
        .erp-kpi-card strong {
            color: var(--erp-ink);
            font-size: 28px;
            line-height: 1.18;
            margin: 4px 0 3px;
        }
        .erp-kpi-card em {
            color: #98a2b3;
            font-size: 11px;
            font-style: normal;
        }
        .erp-kpi-icon {
            align-items: center;
            border-radius: 13px;
            display: inline-flex;
            flex: 0 0 52px;
            font-size: 22px;
            height: 52px;
            justify-content: center;
        }
        .erp-kpi-danger .erp-kpi-icon { background: #fce9eb; color: var(--erp-red); }
        .erp-kpi-gold .erp-kpi-icon { background: #fff5d7; color: #9a7410; }
        .erp-kpi-blue .erp-kpi-icon { background: #e9f2ff; color: #2764b8; }
        .erp-kpi-green .erp-kpi-icon { background: #e9f8ef; color: #18834d; }
        .erp-metal-card {
            align-items: flex-start;
            background: linear-gradient(145deg, #fffdf7, #fff);
            border: 1px solid #eee4c7;
            border-radius: 14px;
            box-shadow: var(--erp-shadow);
            display: flex;
            gap: 15px;
            padding: 20px;
        }
        .erp-metal-icon,
        .erp-order-mark {
            align-items: center;
            background: var(--erp-gold-soft);
            border-radius: 11px;
            color: var(--erp-gold-dark);
            display: inline-flex;
            flex: 0 0 44px;
            height: 44px;
            justify-content: center;
        }
        .erp-metal-card span {
            color: var(--erp-muted);
            display: block;
            font-size: 12px;
            font-weight: 650;
        }
        .erp-metal-card strong {
            color: var(--erp-ink);
            display: block;
            font-size: 23px;
            line-height: 1.2;
            margin: 5px 0;
        }
        .erp-metal-card strong small {
            color: var(--erp-muted);
            font-size: 12px;
        }
        .erp-metal-card p {
            color: var(--erp-muted);
            font-size: 11px;
            margin: 0;
        }
        .erp-section-card {
            overflow: hidden;
        }
        .erp-section-card .card-header {
            align-items: center;
            display: flex;
            justify-content: space-between;
        }
        .erp-empty-state {
            align-items: center;
            color: var(--erp-muted);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 250px;
            padding: 30px;
            text-align: center;
        }
        .erp-empty-state i {
            color: #c3c9d2;
            font-size: 34px;
            margin-bottom: 10px;
        }
        .erp-empty-state strong {
            color: #344054;
            margin-bottom: 4px;
        }
        .erp-empty-state span {
            font-size: 12px;
        }
        .erp-data-link {
            color: var(--erp-red) !important;
            font-weight: 700;
            text-decoration: none;
        }
        .erp-activity-list {
            display: flex;
            flex-direction: column;
        }
        .erp-activity-item {
            align-items: center;
            border-bottom: 1px solid #edf0f4;
            color: var(--erp-ink) !important;
            display: flex;
            gap: 12px;
            padding: 14px 18px;
            text-decoration: none;
            transition: background-color 0.16s ease;
        }
        .erp-activity-item:last-child {
            border-bottom: 0;
        }
        .erp-activity-item:hover {
            background: #fffaf0;
            color: var(--erp-ink) !important;
        }
        .erp-activity-item strong,
        .erp-activity-item small {
            display: block;
        }
        .erp-activity-item small {
            color: var(--erp-muted);
            font-size: 11px;
            margin-top: 2px;
        }
        .erp-activity-item .badge {
            font-size: 10px;
            white-space: nowrap;
        }
        .table.datatable {
            width: 100% !important;
            border-color: #e3e9f2;
        }
        table.table.dataTable.table-bordered {
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            border: 1px solid #e3e9f2 !important;
        }
        table.table.dataTable.table-bordered > thead > tr > th,
        table.table.dataTable.table-bordered > thead > tr > td,
        table.table.dataTable.table-bordered > tbody > tr > th,
        table.table.dataTable.table-bordered > tbody > tr > td,
        table.table.dataTable.table-bordered > tfoot > tr > th,
        table.table.dataTable.table-bordered > tfoot > tr > td {
            border: 1px solid #e3e9f2 !important;
        }
        table.dataTable.no-footer {
            border-bottom: 1px solid #e3e9f2 !important;
        }
        .dataTable.table-bordered {
            border: 1px solid #e3e9f2;
        }
        .table.table-bordered > :not(caption) > * > * {
            border-color: #e3e9f2;
        }
        .table.datatable thead th {
            font-size: 13px;
            font-weight: 600;
            background: #f8fafc;
            border-bottom-width: 1px !important;
            color: #3c4858;
            white-space: nowrap;
        }
        .table.datatable tbody td {
            font-size: 13px;
            white-space: nowrap;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 10px;
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d9e1ee;
            border-radius: 8px;
            min-height: 34px;
        }
        .dataTables_wrapper .dataTables_info {
            font-size: 12px;
            color: #6b7280;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            margin-left: 2px;
        }
        @media (max-width: 767px) {
            html,
            body,
            .main-wrapper,
            .page-wrapper {
                max-width: 100%;
                overflow-x: hidden;
            }
            .page-wrapper {
                margin-left: 0;
                width: 100%;
            }
            .content.container-fluid {
                max-width: 100%;
                padding: 18px 14px 26px;
            }
            .erp-dashboard-hero {
                align-items: flex-start;
                flex-direction: column;
                gap: 20px;
                max-width: 100%;
                padding: 24px 20px;
                width: 100%;
            }
            .erp-dashboard-hero > div {
                max-width: 100%;
                min-width: 0;
            }
            .erp-dashboard-hero p {
                max-width: 100%;
                overflow-wrap: anywhere;
            }
            .erp-dashboard-hero > .d-flex {
                width: 100%;
            }
            .erp-page-toolbar {
                align-items: flex-start;
                flex-direction: column;
                max-width: 100%;
            }
            .erp-section-card .card-header {
                align-items: flex-start;
                gap: 12px;
            }
            .erp-activity-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .table.datatable thead th,
            .table.datatable tbody td {
                white-space: normal;
            }
        }
        .sidebar .sidebar-inner {
            height: calc(100vh - 60px);
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }
        .sidebar .sidebar-inner::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar .sidebar-inner::-webkit-scrollbar-thumb {
            background: rgba(179, 18, 31, 0.45);
            border-radius: 10px;
        }
        .sidebar .sidebar-menu ul li a,
        .sidebar .sidebar-menu ul li a span,
        .sidebar .sidebar-menu ul li a i,
        .sidebar .sidebar-menu ul li a svg,
        .sidebar .sidebar-menu ul li a .menu-arrow {
            color: #2f3a4a !important;
        }
        .sidebar .sidebar-menu ul li a:hover,
        .sidebar .sidebar-menu ul li a.active,
        .sidebar .sidebar-menu ul li a.subdrop,
        .sidebar .sidebar-menu ul li.active > a,
        .sidebar .sidebar-menu ul li.submenu ul li a:hover,
        .sidebar .sidebar-menu ul li.submenu ul li a.active,
        .sidebar .sidebar-menu ul li.submenu ul li.active > a {
            background: #e9edf3 !important;
            color: #2f3a4a !important;
            border-radius: 8px !important;
        }
        .sidebar .sidebar-menu ul li a:hover span,
        .sidebar .sidebar-menu ul li a.active span,
        .sidebar .sidebar-menu ul li a.subdrop span,
        .sidebar .sidebar-menu ul li.active > a span,
        .sidebar .sidebar-menu ul li a:hover i,
        .sidebar .sidebar-menu ul li a.active i,
        .sidebar .sidebar-menu ul li a.subdrop i,
        .sidebar .sidebar-menu ul li.active > a i,
        .sidebar .sidebar-menu ul li a:hover svg,
        .sidebar .sidebar-menu ul li a.active svg,
        .sidebar .sidebar-menu ul li a.subdrop svg,
        .sidebar .sidebar-menu ul li.active > a svg,
        .sidebar .sidebar-menu ul li a:hover .menu-arrow,
        .sidebar .sidebar-menu ul li a.active .menu-arrow,
        .sidebar .sidebar-menu ul li a.subdrop .menu-arrow,
        .sidebar .sidebar-menu ul li.active > a .menu-arrow {
            color: #2f3a4a !important;
        }
        .sidebar .sidebar-menu ul li a:hover img,
        .sidebar .sidebar-menu ul li a.active img,
        .sidebar .sidebar-menu ul li.active > a img {
            filter: none !important;
        }
        .sidebar .sidebar-menu ul li::before,
        .sidebar .sidebar-menu ul li::after,
        .sidebar .sidebar-menu ul li a::before,
        .sidebar .sidebar-menu ul li a::after,
        .sidebar .sidebar-menu ul li.submenu ul li::before,
        .sidebar .sidebar-menu ul li.submenu ul li::after,
        .sidebar .sidebar-menu ul li.submenu ul li a::before,
        .sidebar .sidebar-menu ul li.submenu ul li a::after {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        .sidebar .sidebar-menu ul li.active a::before,
        .sidebar .sidebar-menu ul li.active a::after,
        .sidebar .sidebar-menu ul li a.active::before,
        .sidebar .sidebar-menu ul li a.active::after,
        .sidebar .sidebar-menu ul li a:hover::before,
        .sidebar .sidebar-menu ul li a:hover::after,
        .sidebar .sidebar-menu ul li.submenu ul li a.active::after,
        .sidebar .sidebar-menu ul li.submenu ul li a:hover::after,
        .sidebar .nav-link.active::after {
            display: none !important;
            content: none !important;
            width: 0 !important;
            height: 0 !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .global-loader-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.35);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(1px);
        }
        .global-loader-overlay.active {
            display: flex;
        }
        .global-loader-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            min-width: 220px;
            padding: 16px 18px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.2);
            text-align: center;
        }
        .global-loader-card .spinner-border {
            width: 2rem;
            height: 2rem;
        }
        .global-loader-title {
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        /* Global Red + Gold Theme */
        a,
        .text-primary {
            color: var(--erp-red) !important;
        }
        a:hover,
        a:focus {
            color: var(--erp-red-dark) !important;
        }
        .btn-primary,
        .bg-primary {
            background-color: var(--erp-red) !important;
            border-color: var(--erp-red) !important;
            color: #fff !important;
        }
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--erp-red-dark) !important;
            border-color: var(--erp-red-dark) !important;
        }
        .btn-outline-primary {
            color: var(--erp-red) !important;
            border-color: var(--erp-red) !important;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active {
            background-color: var(--erp-red) !important;
            border-color: var(--erp-red) !important;
            color: #fff !important;
        }
        .btn-warning {
            background-color: var(--erp-gold) !important;
            border-color: var(--erp-gold) !important;
            color: #1b1200 !important;
        }
        .btn-warning:hover,
        .btn-warning:focus,
        .btn-warning:active {
            background-color: var(--erp-gold-dark) !important;
            border-color: var(--erp-gold-dark) !important;
            color: #1b1200 !important;
        }
        .page-item.active .page-link,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--erp-red) !important;
            border-color: var(--erp-red) !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--erp-red-soft) !important;
            border-color: #e8c4c8 !important;
            color: var(--erp-red-dark) !important;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--erp-red) !important;
            box-shadow: 0 0 0 0.2rem rgba(179, 18, 31, 0.12) !important;
        }
        .sidebar .sidebar-menu ul li a:hover,
        .sidebar .sidebar-menu ul li a.active,
        .sidebar .sidebar-menu ul li a.subdrop,
        .sidebar .sidebar-menu ul li.active > a,
        .sidebar .sidebar-menu ul li.submenu ul li a:hover,
        .sidebar .sidebar-menu ul li.submenu ul li a.active,
        .sidebar .sidebar-menu ul li.submenu ul li.active > a {
            background: linear-gradient(90deg, var(--erp-red-soft), var(--erp-gold-soft)) !important;
            color: var(--erp-red-dark) !important;
        }
        .badge.bg-success {
            background-color: #1e7a3c !important;
        }
        .badge.bg-warning {
            background-color: var(--erp-gold) !important;
            color: #1b1200 !important;
        }
        .badge.bg-primary {
            background-color: var(--erp-red) !important;
        }
        .order-layout-shell {
            border-left-color: var(--erp-red) !important;
        }
        .swal2-confirm {
            background-color: var(--erp-red) !important;
        }
    </style>
</head>
<body>
    <div id="globalLoaderOverlay" class="global-loader-overlay active" aria-hidden="false">
        <div class="global-loader-card">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
            <div class="global-loader-title">Loading...</div>
        </div>
    </div>
    <div class="main-wrapper">
        <div class="header header-one">
            <a href="<?= site_url('admin/dashboard') ?>" class="d-inline-flex d-sm-inline-flex align-items-center d-md-inline-flex d-lg-none align-items-center device-logo">
                <img src="<?= esc($assetBase) ?>/img/logo.png" class="img-fluid logo2" alt="Logo">
            </a>
            <div class="main-logo d-inline float-start d-lg-flex align-items-center d-none d-sm-none d-md-none">
                <div class="logo-white">
                    <a href="<?= site_url('admin/dashboard') ?>">
                        <img src="<?= esc($assetBase) ?>/img/logo-full-white.png" class="img-fluid logo-blue" alt="Logo">
                    </a>
                    <a href="<?= site_url('admin/dashboard') ?>">
                        <img src="<?= esc($assetBase) ?>/img/logo-small-white.png" class="img-fluid logo-small" alt="Logo">
                    </a>
                </div>
                <div class="logo-color">
                    <a href="<?= site_url('admin/dashboard') ?>">
                        <img src="<?= esc($assetBase) ?>/img/logo.png" class="img-fluid logo-blue" alt="Logo">
                    </a>
                    <a href="<?= site_url('admin/dashboard') ?>">
                        <img src="<?= esc($assetBase) ?>/img/logo-small.png" class="img-fluid logo-small" alt="Logo">
                    </a>
                </div>
            </div>

            <a href="javascript:void(0);" id="toggle_btn">
                <span class="toggle-bars">
                    <span class="bar-icons"></span>
                    <span class="bar-icons"></span>
                    <span class="bar-icons"></span>
                    <span class="bar-icons"></span>
                </span>
            </a>

            <div class="top-nav-search">
                <form>
                    <input type="text" class="form-control" placeholder="Search here">
                    <button class="btn" type="button"><img src="<?= esc($assetBase) ?>/img/icons/search.svg" alt="img"></button>
                </form>
            </div>

            <a class="mobile_btn" id="mobile_btn">
                <i class="fas fa-bars"></i>
            </a>

            <ul class="nav nav-tabs user-menu">
                <li class="nav-item dropdown">
                    <a href="javascript:void(0)" class="user-link nav-link" data-bs-toggle="dropdown">
                        <span class="user-img">
                            <img src="<?= esc($assetBase) ?>/img/profiles/avatar-07.jpg" alt="img" class="profilesidebar">
                            <span class="animate-circle"></span>
                        </span>
                        <span class="user-content">
                            <span class="user-details">Admin</span>
                            <span class="user-name"><?= esc((string) (session('admin_name') ?: 'Admin')) ?></span>
                        </span>
                    </a>
                    <div class="dropdown-menu menu-drop-user">
                        <div class="profilemenu">
                            <div class="subscription-menu">
                                <ul>
                                    <li><a class="dropdown-item" href="<?= site_url('admin/dashboard') ?>">Dashboard</a></li>
                                </ul>
                            </div>
                            <div class="subscription-logout">
                                <ul>
                                    <li class="pb-0"><a class="dropdown-item" href="<?= site_url('admin/logout') ?>">Log Out</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="sidebar" id="sidebar">
            <div class="sidebar-inner">
                <div id="sidebar-menu" class="sidebar-menu">
                    <ul class="sidebar-vertical">
                        <li class="menu-title"><span>Main</span></li>
                        <?php if ($canDashboard): ?>
                        <li class="<?= $isDash ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/dashboard') ?>"><i class="fe fe-home"></i> <span>Dashboard</span></a>
                        </li>
                        <?php endif; ?>

                        <?php if ($canCrmOrdersMenu): ?>
                        <li class="menu-title"><span>Customers & Orders</span></li>
                        <?php endif; ?>
                        <?php if ($canCustomers): ?>
                        <li class="<?= $isCustomers ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/customers') ?>"><i class="fe fe-users"></i> <span>Customers</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canOrders): ?>
                        <li class="submenu <?= $isOrders ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-clipboard"></i> <span>Orders</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isOrders ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isOrdersDashboard ? 'active' : '' ?>" href="<?= site_url('admin/orders/dashboard') ?>"><i class="fe fe-pie-chart"></i> Order Dashboard</a></li>
                                <li><a class="<?= ($isOrdersAll || $isOrdersCreate) ? 'active' : '' ?>" href="<?= site_url('admin/orders') ?>"><i class="fe fe-list"></i> All Orders</a></li>
                                <?php if ($canOrdersCreate): ?>
                                <li><a class="<?= $isOrdersFresh ? 'active' : '' ?>" href="<?= site_url('admin/orders/fresh') ?>"><i class="fe fe-plus-square"></i> Fresh Orders</a></li>
                                <?php endif; ?>
                                <li><a class="<?= $isOrdersReady ? 'active' : '' ?>" href="<?= site_url('admin/orders/ready') ?>"><i class="fe fe-package"></i> Ready Orders</a></li>
                                <?php if ($canOrdersCreate): ?>
                                <li><a class="<?= ($isOrdersRepair || $isOrdersRepairCreate) ? 'active' : '' ?>" href="<?= site_url('admin/orders/repair') ?>"><i class="fe fe-settings"></i> Repair Orders</a></li>
                                <?php endif; ?>
                                <li><a class="<?= $isOrdersFollowups ? 'active' : '' ?>" href="<?= site_url('admin/orders/followups') ?>"><i class="fe fe-calendar"></i> Followups</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($canProductionMenu): ?>
                        <li class="menu-title"><span>Production</span></li>
                        <?php endif; ?>
                        <?php if ($canKarigars): ?>
                        <li class="<?= $isKarigars ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/karigars') ?>"><i class="fe fe-user-check"></i> <span>Karigars</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canIssuements): ?>
                        <li class="<?= $isIssuements ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/issuements') ?>"><i class="fe fe-share-2"></i> <span>Material Issue</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canDesigns): ?>
                        <li class="<?= $isDesigns ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/designs') ?>"><i class="fe fe-image"></i> <span>Designs</span></a>
                        </li>
                        <?php endif; ?>

                        <?php if ($canInventoryMenu): ?>
                        <li class="menu-title"><span>Inventory</span></li>
                        <?php endif; ?>
                        <?php if ($canGoldInventory): ?>
                        <li class="submenu <?= $isGoldInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-circle"></i> <span>Gold Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isGoldInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isGoldInventoryPurities ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/purities') ?>"><i class="fe fe-percent"></i> Purity Master</a></li>
                                <li><a class="<?= $isGoldInventoryProducts ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/products') ?>"><i class="fe fe-package"></i> Product Master</a></li>
                                <li><a class="<?= $isGoldInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isGoldInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isGoldInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isGoldInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock</a></li>
                                <li><a class="<?= $isGoldInventoryLedger ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/ledger') ?>"><i class="fe fe-book-open"></i> Ledger</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($canDiamondInventory): ?>
                        <li class="submenu <?= $isDiamondInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fas fa-gem"></i> <span>Diamond Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isDiamondInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isDiamondInventoryItems ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/items') ?>"><i class="fe fe-tag"></i> Item Master</a></li>
                                <li><a class="<?= $isDiamondInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isDiamondInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isDiamondInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isDiamondInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($canStoneInventory): ?>
                        <li class="submenu <?= $isStoneInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-disc"></i> <span>Stone Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isStoneInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isStoneInventoryItems ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/items') ?>"><i class="fe fe-tag"></i> Item Master</a></li>
                                <li><a class="<?= $isStoneInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isStoneInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isStoneInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isStoneInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($canInventorySettings): ?>
                        <li class="submenu <?= $isInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-settings"></i> <span>Inventory Setup</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isInventoryWarehouses ? 'active' : '' ?>" href="<?= site_url('admin/inventory/warehouses') ?>"><i class="fe fe-home"></i> Warehouse</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($canShowroomMenu): ?>
                        <li class="menu-title"><span>Showroom</span></li>
                        <li class="submenu <?= $isShowroomModule ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-shopping-bag"></i> <span>Retail Showroom</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isShowroomModule ? 'display:block;' : 'display:none;' ?>">
                                <?php if ($canShowroomSales): ?><li><a class="<?= $isShowroomSales ? 'active' : '' ?>" href="<?= site_url('admin/showroom-sales') ?>"><i class="fe fe-credit-card"></i> Showroom Sales</a></li><?php endif; ?>
                                <?php if ($canShowroomStock): ?><li><a class="<?= $isShowroomStock ? 'active' : '' ?>" href="<?= site_url('admin/showroom-stock') ?>"><i class="fe fe-layers"></i> Showroom Stock</a></li><?php endif; ?>
                                <?php if ($canShowroomStock): ?><li><a class="<?= $isJewelleryInventory ? 'active' : '' ?>" href="<?= site_url('admin/jewellery-inventory') ?>"><i class="fas fa-gem"></i> Jewellery Inventory</a></li><?php endif; ?>
                                <?php if ($canShowroomMasters): ?><li><a class="<?= $isShowrooms ? 'active' : '' ?>" href="<?= site_url('admin/showrooms') ?>"><i class="fe fe-home"></i> Showrooms</a></li><?php endif; ?>
                                <?php if ($canShowroomMasters): ?><li><a class="<?= $isShowroomCounters ? 'active' : '' ?>" href="<?= site_url('admin/showroom-counters') ?>"><i class="fe fe-grid"></i> Counters</a></li><?php endif; ?>
                                <?php if ($canShowroomMasters): ?><li><a class="<?= $isShowroomStaff ? 'active' : '' ?>" href="<?= site_url('admin/showroom-staff') ?>"><i class="fe fe-users"></i> Staff Assignment</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($canAccounts): ?>
                        <li class="menu-title"><span>Accounts</span></li>
                        <li class="submenu <?= $isAccounts ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-file-text"></i> <span>Accounts</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isAccounts ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isAccountsDashboard ? 'active' : '' ?>" href="<?= site_url('admin/accounts') ?>"><i class="fe fe-grid"></i> Dashboard</a></li>
                                <li><a class="<?= $isAccountsJournalVouchers ? 'active' : '' ?>" href="<?= site_url('admin/accounts/journal-vouchers') ?>"><i class="fe fe-edit-3"></i> Journal Voucher</a></li>
                                <li><a class="<?= ($isAccountsPartyBalances || $isAccountsPartyLedger) ? 'active' : '' ?>" href="<?= site_url('admin/accounts/party-balances/vendor') ?>"><i class="fe fe-users"></i> Pending Parties</a></li>
                                <li><a class="<?= $isAccountsGeneralLedger ? 'active' : '' ?>" href="<?= site_url('admin/accounts/general-ledger') ?>"><i class="fe fe-list"></i> All Ledgers</a></li>
                                <li><a class="<?= $isAccountsVendorTransactionLedger ? 'active' : '' ?>" href="<?= site_url('admin/accounts/vendor-transaction-ledger') ?>"><i class="fe fe-repeat"></i> Issue Receive Ledger</a></li>
                                <li><a class="<?= $isAccountsPayments ? 'active' : '' ?>" href="<?= site_url('admin/accounts/payments') ?>"><i class="fe fe-send"></i> Payments</a></li>
                                <li><a class="<?= $isAccountsOutstanding ? 'active' : '' ?>" href="<?= site_url('admin/accounts/outstanding-summary') ?>"><i class="fe fe-bar-chart"></i> Outstanding Summary</a></li>
                                <li><a class="<?= $isAccountsPurchaseBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/purchase-bills') ?>"><i class="fe fe-shopping-bag"></i> Purchase Bills</a></li>
                                <li><a class="<?= $isAccountsLabourLedger ? 'active' : '' ?>" href="<?= site_url('admin/accounts/labour-ledger') ?>"><i class="fe fe-book-open"></i> Labour Ledger</a></li>
                                <li><a class="<?= $isAccountsLabourBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/labour-bills') ?>"><i class="fe fe-tool"></i> Labour Bills</a></li>
                                <li><a class="<?= $isAccountsSaleBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/sale-bills') ?>"><i class="fe fe-credit-card"></i> Sale Bills</a></li>
                                <li><a class="<?= $isAccountsDebitNotes ? 'active' : '' ?>" href="<?= site_url('admin/accounts/debit-notes') ?>"><i class="fe fe-corner-down-right"></i> Debit Notes</a></li>
                                <li><a class="<?= $isAccountsCreditNotes ? 'active' : '' ?>" href="<?= site_url('admin/accounts/credit-notes') ?>"><i class="fe fe-corner-up-left"></i> Credit Notes</a></li>
                                <li><a class="<?= $isAccountsGstReport ? 'active' : '' ?>" href="<?= site_url('admin/accounts/gst-report') ?>"><i class="fe fe-percent"></i> GST Report</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($canReports): ?>
                        <li class="menu-title"><span>Reports</span></li>
                        <li class="submenu <?= $isReports ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-bar-chart-2"></i> <span>Reports</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isReports ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isReportsTransactions ? 'active' : '' ?>" href="<?= site_url('admin/reports/transactions') ?>"><i class="fe fe-repeat"></i> All Transactions</a></li>
                                <li><a class="<?= $isReportsGoldLedger ? 'active' : '' ?>" href="<?= site_url('admin/reports/gold-ledger') ?>"><i class="fe fe-book"></i> Gold Ledger</a></li>
                                <li><a class="<?= $isReportsDiamondLedger ? 'active' : '' ?>" href="<?= site_url('admin/reports/diamond-ledger') ?>"><i class="fe fe-disc"></i> Diamond Ledger</a></li>
                                <li><a class="<?= $isReportsInventory ? 'active' : '' ?>" href="<?= site_url('admin/reports/inventory') ?>"><i class="fe fe-layers"></i> Inventory</a></li>
                                <li><a class="<?= $isReportsKarigarPerformance ? 'active' : '' ?>" href="<?= site_url('admin/reports/karigar-performance') ?>"><i class="fe fe-activity"></i> Karigar Performance</a></li>
                                <li><a class="<?= $isReportsStaffDirectory ? 'active' : '' ?>" href="<?= site_url('admin/reports/staff-directory') ?>"><i class="fe fe-users"></i> Staff Directory</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if ($canAdminMenu): ?>
                        <li class="menu-title"><span>Admin</span></li>
                        <?php endif; ?>
                        <?php if ($canVendors): ?>
                        <li class="<?= $isVendors ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/vendors') ?>"><i class="fe fe-truck"></i> <span>Vendors</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canStaffHierarchy): ?>
                        <li class="submenu <?= $isStaffHierarchy ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-briefcase"></i> <span>Staff & Organization</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isStaffHierarchy ? 'display:block;' : 'display:none;' ?>">
                                <?php if (admin_can('organization.departments.read')): ?><li><a class="<?= $isDepartments ? 'active' : '' ?>" href="<?= site_url('admin/departments') ?>"><i class="fe fe-grid"></i> Departments</a></li><?php endif; ?>
                                <?php if (admin_can('organization.designations.read')): ?><li><a class="<?= $isDesignations ? 'active' : '' ?>" href="<?= site_url('admin/designations') ?>"><i class="fe fe-award"></i> Designations</a></li><?php endif; ?>
                                <?php if (admin_can('organization.employees.read')): ?><li><a class="<?= $isEmployees ? 'active' : '' ?>" href="<?= site_url('admin/employees') ?>"><i class="fe fe-user"></i> Employees</a></li><?php endif; ?>
                                <?php if (admin_can('organization.hierarchy.read')): ?><li><a class="<?= $isEmployeeHierarchy ? 'active' : '' ?>" href="<?= site_url('admin/employee-hierarchy') ?>"><i class="fe fe-git-branch"></i> Employee Hierarchy</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($canPerformance): ?>
                        <li class="submenu <?= $isPerformance ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-trending-up"></i> <span>Performance</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isPerformance ? 'display:block;' : 'display:none;' ?>">
                                <?php if (admin_can('performance.dashboard.read')): ?><li><a class="<?= $isPerformanceDashboard ? 'active' : '' ?>" href="<?= site_url('admin/performance/dashboard') ?>"><i class="fe fe-bar-chart-2"></i> KPI Dashboard</a></li><?php endif; ?>
                                <?php if (admin_can('performance.kpis.read')): ?><li><a class="<?= $isPerformanceKpis ? 'active' : '' ?>" href="<?= site_url('admin/performance/kpis') ?>"><i class="fe fe-activity"></i> KPI Master</a></li><?php endif; ?>
                                <?php if (admin_can('performance.targets.read')): ?><li><a class="<?= $isPerformanceTargets ? 'active' : '' ?>" href="<?= site_url('admin/performance/targets') ?>"><i class="fe fe-target"></i> KPI Targets</a></li><?php endif; ?>
                                <?php if (admin_can('performance.incentives.read')): ?><li><a class="<?= $isPerformanceIncentives ? 'active' : '' ?>" href="<?= site_url('admin/performance/incentives') ?>"><i class="fe fe-award"></i> Incentive Rules</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($canCompanySettings): ?>
                        <li class="<?= $isCompanySettings ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/company-settings') ?>"><i class="fe fe-briefcase"></i> <span>Company Settings</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if (admin_can('company-settings.manage')): ?>
                        <li class="<?= $isDatabaseUpdate ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/system/database-update') ?>"><i class="fe fe-database"></i> <span>Database Update</span></a>
                        </li>
                        <li class="<?= $isProductionImport ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/system/production-import') ?>"><i class="fe fe-upload-cloud"></i> <span>Production Import</span></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canAccessControl): ?>
                        <li class="submenu <?= $isAccess ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-shield"></i> <span>Users & Access</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isAccess ? 'display:block;' : 'display:none;' ?>">
                                <?php if (admin_can('access.roles.read')): ?><li><a class="<?= $isAccessRoles ? 'active' : '' ?>" href="<?= site_url('admin/access/roles') ?>"><i class="fe fe-lock"></i> Roles</a></li><?php endif; ?>
                                <?php if (admin_can('access.permissions.read')): ?><li><a class="<?= $isAccessPermissions ? 'active' : '' ?>" href="<?= site_url('admin/access/permissions') ?>"><i class="fe fe-key"></i> Permissions</a></li><?php endif; ?>
                                <?php if (admin_can('access.users.read')): ?><li><a class="<?= $isAccessUsers ? 'active' : '' ?>" href="<?= site_url('admin/access/users') ?>"><i class="fe fe-users"></i> User Access</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="page-wrapper">
            <div class="content container-fluid pb-0">
                <div class="page-header">
                    <div class="content-page-header">
                        <h5><?= esc($title ?? 'Admin') ?></h5>
                    </div>
                </div>

                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>

    <script src="<?= esc($assetBase) ?>/js/jquery-3.7.1.min.js"></script>
    <script src="<?= esc($assetBase) ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?= esc($assetBase) ?>/plugins/datatables/datatables.min.js"></script>
    <script src="<?= esc($assetBase) ?>/plugins/select2/js/select2.min.js"></script>
    <script src="<?= esc($assetBase) ?>/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <script src="<?= esc($assetBase) ?>/plugins/moment/moment.min.js"></script>
    <script src="<?= esc($assetBase) ?>/js/bootstrap-datetimepicker.min.js"></script>
    <script src="<?= esc($assetBase) ?>/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="<?= esc($assetBase) ?>/js/theme-settings.js"></script>
    <script src="<?= esc($assetBase) ?>/js/greedynav.js"></script>
    <script src="<?= esc($assetBase) ?>/js/script.js"></script>
    <script>
        (function () {
            const overlay = document.getElementById('globalLoaderOverlay');
            function showLoader() {
                if (!overlay) return;
                overlay.classList.add('active');
                overlay.setAttribute('aria-hidden', 'false');
            }
            function hideLoader() {
                if (!overlay) return;
                overlay.classList.remove('active');
                overlay.setAttribute('aria-hidden', 'true');
            }

            window.AppLoader = {
                show: showLoader,
                hide: hideLoader,
            };

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                if (form.hasAttribute('data-loader-off')) return;
                showLoader();
            }, true);

            document.addEventListener('click', function (event) {
                const link = event.target instanceof Element ? event.target.closest('a') : null;
                if (!link) return;
                if (link.hasAttribute('data-loader-off')) return;
                const href = (link.getAttribute('href') || '').trim();
                if (href === '' || href === '#' || href.startsWith('javascript:')) return;
                if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return;
                if (link.hasAttribute('download')) return;

                try {
                    const targetUrl = new URL(href, window.location.href);
                    if (targetUrl.origin !== window.location.origin) return;
                    showLoader();
                } catch (e) {
                    showLoader();
                }
            }, true);

            window.addEventListener('pageshow', hideLoader);
            window.addEventListener('load', hideLoader);
        })();
    </script>
    <script>
        (function () {
            const flashes = [];
            <?php if (session('error')): ?>
            flashes.push({
                icon: 'error',
                title: 'Error',
                text: <?= json_encode((string) session('error')) ?>
            });
            <?php endif; ?>
            <?php if (session('warning')): ?>
            flashes.push({
                icon: 'warning',
                title: 'Warning',
                text: <?= json_encode((string) session('warning')) ?>
            });
            <?php endif; ?>
            <?php if (session('success')): ?>
            flashes.push({
                icon: 'success',
                title: 'Success',
                text: <?= json_encode((string) session('success')) ?>
            });
            <?php endif; ?>

            if (typeof Swal === 'undefined' || flashes.length === 0) {
                return;
            }

            const showFlash = function (index) {
                if (index >= flashes.length) return;
                Swal.fire({
                    icon: flashes[index].icon,
                    title: flashes[index].title,
                    text: flashes[index].text,
                    confirmButtonColor: '#b3121f'
                }).then(function () {
                    showFlash(index + 1);
                });
            };
            showFlash(0);
        })();
    </script>
    <script>
        (function () {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
                return;
            }

            function boolAttr(value, fallback) {
                if (value === undefined || value === null || value === '') {
                    return fallback;
                }
                return ['1', 'true', 'yes', 'on'].indexOf(String(value).toLowerCase()) !== -1;
            }

            jQuery(function ($) {
                $('table.table').each(function () {
                    const $table = $(this);
                    if (!$table.hasClass('table-borderless')) {
                        $table.addClass('table-bordered');
                    }
                    $table.addClass('table-striped align-middle');

                    // Keep entry/input grids stable; enable DataTable on the rest.
                    const hasFormControls = $table.find('tbody input, tbody select, tbody textarea, tbody button').length > 0;
                    const hasTabularHeader = $table.find('thead th').length > 0;
                    const skipAutoDatatable = boolAttr($table.attr('data-dt-skip'), false);
                    if (!hasFormControls && hasTabularHeader && !skipAutoDatatable && !$table.hasClass('datatable')) {
                        $table.addClass('datatable');
                    }
                });

                $('.datatable').each(function () {
                    const $table = $(this);
                    const searching = boolAttr($table.attr('data-dt-searching'), true);
                    const ordering = boolAttr($table.attr('data-dt-ordering'), true);
                    const paging = boolAttr($table.attr('data-dt-paging'), true);
                    const info = boolAttr($table.attr('data-dt-info'), true);
                    const pageLengthAttr = parseInt($table.attr('data-dt-page-length') || '10', 10);

                    if ($.fn.DataTable.isDataTable(this)) {
                        $table.DataTable().destroy();
                    }

                    $table.DataTable({
                        pageLength: Number.isNaN(pageLengthAttr) ? 10 : pageLengthAttr,
                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, 'All']
                        ],
                        searching: searching,
                        ordering: ordering,
                        paging: paging,
                        info: info,
                        order: [],
                        autoWidth: false,
                        dom:
                            "<'row align-items-center g-2 mb-2'<'col-md-6 col-sm-12'l><'col-md-6 col-sm-12'f>>" +
                            "<'row'<'col-sm-12'tr>>" +
                            "<'row align-items-center g-2 mt-2'<'col-md-5 col-sm-12'i><'col-md-7 col-sm-12'p>>",
                        language: {
                            search: '',
                            searchPlaceholder: 'Search records...',
                            lengthMenu: '_MENU_',
                            emptyTable: 'No records available'
                        }
                    });

                    const $wrapper = $table.closest('.dataTables_wrapper');
                    $wrapper.find('.dataTables_filter input').addClass('form-control form-control-sm');
                    $wrapper.find('.dataTables_length select').addClass('form-select form-select-sm');
                });
            });
        })();
    </script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
