<?php
$uri        = service('uri');
$segments   = $uri->getSegments();
$segment2   = (string) ($segments[1] ?? '');
$segment3   = (string) ($segments[2] ?? '');
$segment4   = (string) ($segments[3] ?? '');
$assetBasePath = ((string) ($segments[0] ?? '') === 'public') ? 'public/template/assets' : 'template/assets';
$assetBase = base_url($assetBasePath);
$isDash     = $segment2 === 'dashboard';
$isLeads    = $segment2 === 'leads';
$isCustomers= $segment2 === 'customers';
$isOrders   = $segment2 === 'orders';
$isOrdersAll   = $isOrders && ($segment3 === '' || ctype_digit($segment3));
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
$isKarigars = $segment2 === 'karigars';
$isReports  = $segment2 === 'reports';
$isReportsGoldLedger = $isReports && ($segment3 === '' || $segment3 === 'gold-ledger');
$isReportsDiamondLedger = $isReports && $segment3 === 'diamond-ledger';
$isReportsKarigarPerformance = $isReports && $segment3 === 'karigar-performance';
$isReportsInventory = $isReports && $segment3 === 'inventory';
$isAccounts = $segment2 === 'accounts';
$isAccountsPurchaseBills = $isAccounts && ($segment3 === '' || $segment3 === 'purchase-bills');
$isAccountsLabourBills = $isAccounts && $segment3 === 'labour-bills';
$isAccountsSaleBills = $isAccounts && $segment3 === 'sale-bills';
$isVendors  = $segment2 === 'vendors';
$isCompanySettings = $segment2 === 'company-settings';
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
        }
        .card {
            border: 1px solid #e8edf3;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(16, 24, 40, 0.04);
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
                        <li class="<?= $isDash ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/dashboard') ?>"><i class="fe fe-home"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="<?= $isLeads ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/leads') ?>"><i class="fe fe-phone-call"></i> <span>Leads</span></a>
                        </li>
                        <li class="<?= $isCustomers ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/customers') ?>"><i class="fe fe-users"></i> <span>Customers</span></a>
                        </li>
                        <li class="submenu <?= $isOrders ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-clipboard"></i> <span>Orders</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isOrders ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= ($isOrdersAll || $isOrdersCreate) ? 'active' : '' ?>" href="<?= site_url('admin/orders') ?>"><i class="fe fe-list"></i> All Orders</a></li>
                                <li><a class="<?= $isOrdersFresh ? 'active' : '' ?>" href="<?= site_url('admin/orders/fresh') ?>"><i class="fe fe-plus-square"></i> Fresh Orders</a></li>
                                <li><a class="<?= $isOrdersReady ? 'active' : '' ?>" href="<?= site_url('admin/orders/ready') ?>"><i class="fe fe-package"></i> Ready Orders</a></li>
                                <li><a class="<?= ($isOrdersRepair || $isOrdersRepairCreate) ? 'active' : '' ?>" href="<?= site_url('admin/orders/repair') ?>"><i class="fe fe-settings"></i> Repair Orders</a></li>
                                <li><a class="<?= $isOrdersFollowups ? 'active' : '' ?>" href="<?= site_url('admin/orders/followups') ?>"><i class="fe fe-calendar"></i> Followups</a></li>
                            </ul>
                        </li>
                        <li class="<?= $isIssuements ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/issuements') ?>"><i class="fe fe-share-2"></i> <span>Issuement</span></a>
                        </li>
                        <li class="submenu <?= $isReports ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-bar-chart-2"></i> <span>Reports</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isReports ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isReportsGoldLedger ? 'active' : '' ?>" href="<?= site_url('admin/reports/gold-ledger') ?>"><i class="fe fe-book"></i> Gold Ledger Report</a></li>
                                <li><a class="<?= $isReportsDiamondLedger ? 'active' : '' ?>" href="<?= site_url('admin/reports/diamond-ledger') ?>"><i class="fe fe-disc"></i> Diamond Ledger Report</a></li>
                                <li><a class="<?= $isReportsKarigarPerformance ? 'active' : '' ?>" href="<?= site_url('admin/reports/karigar-performance') ?>"><i class="fe fe-activity"></i> Karigar Performance</a></li>
                                <li><a class="<?= $isReportsInventory ? 'active' : '' ?>" href="<?= site_url('admin/reports/inventory') ?>"><i class="fe fe-layers"></i> Inventory Report</a></li>
                            </ul>
                        </li>
                        <li class="submenu <?= $isAccounts ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-file-text"></i> <span>Accounts</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isAccounts ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isAccountsPurchaseBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/purchase-bills') ?>"><i class="fe fe-shopping-bag"></i> Purchase Bills</a></li>
                                <li><a class="<?= $isAccountsLabourBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/labour-bills') ?>"><i class="fe fe-tool"></i> Labour Bills</a></li>
                                <li><a class="<?= $isAccountsSaleBills ? 'active' : '' ?>" href="<?= site_url('admin/accounts/sale-bills') ?>"><i class="fe fe-credit-card"></i> Sale Bills</a></li>
                            </ul>
                        </li>

                        <li class="menu-title"><span>Masters</span></li>
                        <li class="<?= $isDesigns ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/designs') ?>"><i class="fe fe-image"></i> <span>Design Master</span></a>
                        </li>
                        <li class="<?= $isKarigars ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/karigars') ?>"><i class="fe fe-user-check"></i> <span>Karigar Master</span></a>
                        </li>
                        <li class="submenu <?= $isStaffHierarchy ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-briefcase"></i> <span>Staff & Hierarchy</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isStaffHierarchy ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isDepartments ? 'active' : '' ?>" href="<?= site_url('admin/departments') ?>"><i class="fe fe-grid"></i> Department Master</a></li>
                                <li><a class="<?= $isDesignations ? 'active' : '' ?>" href="<?= site_url('admin/designations') ?>"><i class="fe fe-award"></i> Designation Master</a></li>
                                <li><a class="<?= $isEmployees ? 'active' : '' ?>" href="<?= site_url('admin/employees') ?>"><i class="fe fe-user"></i> Employee Master</a></li>
                                <li><a class="<?= $isEmployeeHierarchy ? 'active' : '' ?>" href="<?= site_url('admin/employee-hierarchy') ?>"><i class="fe fe-git-branch"></i> Employee Hierarchy</a></li>
                            </ul>
                        </li>
                        <li class="<?= $isVendors ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/vendors') ?>"><i class="fe fe-truck"></i> <span>Vendors</span></a>
                        </li>
                        <li class="<?= $isCompanySettings ? 'active' : '' ?>">
                            <a href="<?= site_url('admin/company-settings') ?>"><i class="fe fe-briefcase"></i> <span>Company Settings</span></a>
                        </li>
                        <li class="menu-title"><span>Inventory</span></li>
                        <li class="submenu <?= $isInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-settings"></i> <span>Inventory Settings</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isInventoryWarehouses ? 'active' : '' ?>" href="<?= site_url('admin/inventory/warehouses') ?>"><i class="fe fe-home"></i> Warehouse</a></li>
                            </ul>
                        </li>
                        <li class="submenu <?= $isDiamondInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fas fa-gem"></i> <span>Diamond Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isDiamondInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isDiamondInventoryItems ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/items') ?>"><i class="fe fe-tag"></i> Item Master</a></li>
                                <li><a class="<?= $isDiamondInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isDiamondInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isDiamondInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isDiamondInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/diamond-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock Summary</a></li>
                            </ul>
                        </li>
                        <li class="submenu <?= $isStoneInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-disc"></i> <span>Stone Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isStoneInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isStoneInventoryItems ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/items') ?>"><i class="fe fe-tag"></i> Item Master</a></li>
                                <li><a class="<?= $isStoneInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isStoneInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isStoneInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isStoneInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/stone-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock Summary</a></li>
                            </ul>
                        </li>
                        <li class="submenu <?= $isGoldInventory ? 'active' : '' ?>">
                            <a href="javascript:void(0);"><i class="fe fe-circle"></i> <span>Gold Inventory</span> <span class="menu-arrow"></span></a>
                            <ul style="<?= $isGoldInventory ? 'display:block;' : 'display:none;' ?>">
                                <li><a class="<?= $isGoldInventoryPurities ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/purities') ?>"><i class="fe fe-percent"></i> Purity Master</a></li>
                                <li><a class="<?= $isGoldInventoryProducts ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/products') ?>"><i class="fe fe-package"></i> Product Master</a></li>
                                <li><a class="<?= $isGoldInventoryPurchases ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/purchases') ?>"><i class="fe fe-shopping-bag"></i> Purchases</a></li>
                                <li><a class="<?= $isGoldInventoryReturns ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/returns') ?>"><i class="fe fe-corner-up-left"></i> Returns</a></li>
                                <li><a class="<?= $isGoldInventoryAdjustments ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/adjustments') ?>"><i class="fe fe-sliders"></i> Adjustments</a></li>
                                <li><a class="<?= $isGoldInventoryStock ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/stock') ?>"><i class="fe fe-layers"></i> Stock Summary</a></li>
                                <li><a class="<?= $isGoldInventoryLedger ? 'active' : '' ?>" href="<?= site_url('admin/gold-inventory/ledger') ?>"><i class="fe fe-book-open"></i> Ledger</a></li>
                            </ul>
                        </li>

                        <li class="menu-title"><span>Auth</span></li>
                        <li>
                            <a href="<?= site_url('admin/logout') ?>"><i class="fe fe-power"></i> <span>Logout</span></a>
                        </li>
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
                            lengthMenu: 'Show _MENU_ entries',
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
