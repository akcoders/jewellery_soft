<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($title ?? 'Customer Portal') ?> · Aabhushan</title>
    <link rel="stylesheet" href="<?= base_url('template/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('template/assets/plugins/feather/feather.css') ?>">
    <link rel="stylesheet" href="<?= base_url('template/assets/plugins/select2/css/select2.min.css') ?>">
    <style>
        :root { --portal-ink: #172033; --portal-muted: #687386; --portal-gold: #b8863b; --portal-gold-soft: #fff6e5; --portal-border: #e6e9ef; --portal-paper: #f5f6f9; }
        * { box-sizing: border-box; }
        body { background: radial-gradient(circle at 82% 2%, rgba(184, 134, 59, .1), transparent 24rem), var(--portal-paper); color: var(--portal-ink); min-height: 100vh; }
        .portal-nav { background: linear-gradient(120deg, #171d2c, #312431); box-shadow: 0 8px 28px rgba(20, 25, 40, .18); min-height: 72px; }
        .brand-mark { align-items: center; background: linear-gradient(135deg, #d6b26e, #a77028); border-radius: 12px; color: #fff; display: inline-flex; flex: 0 0 40px; font-weight: 800; height: 40px; justify-content: center; }
        .portal-nav-link { border-radius: 9px; color: rgba(255, 255, 255, .72); font-size: 13px; font-weight: 650; padding: 8px 11px; text-decoration: none; }
        .portal-nav-link:hover { background: rgba(255, 255, 255, .08); color: #fff; }
        .portal-user-chip { align-items: center; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .1); border-radius: 12px; display: flex; gap: 9px; padding: 7px 10px; }
        .portal-user-avatar { align-items: center; background: rgba(214, 178, 110, .2); border-radius: 9px; color: #e8c985; display: inline-flex; flex: 0 0 32px; height: 32px; justify-content: center; }
        .portal-user-chip strong, .portal-user-chip small { color: #fff; display: block; line-height: 1.2; }
        .portal-user-chip strong { font-size: 11px; }
        .portal-user-chip small { font-size: 9px; opacity: .58; text-transform: capitalize; }
        .portal-shell { margin: 0 auto; max-width: 1240px; padding: 30px 18px 60px; }
        .portal-card { border: 1px solid var(--portal-border); border-radius: 18px; box-shadow: 0 12px 32px rgba(28, 37, 56, .065); overflow: hidden; }
        .portal-card .card-header { background: linear-gradient(135deg, #fff, #fffcf6); border-color: var(--portal-border); padding: 17px 20px; }
        .portal-hero { align-items: center; background: linear-gradient(130deg, #fff, #fffaf0); border: 1px solid #ece3d3; border-left: 4px solid var(--portal-gold); border-radius: 18px; box-shadow: 0 12px 32px rgba(28, 37, 56, .055); display: flex; gap: 20px; justify-content: space-between; padding: 22px 24px; }
        .portal-hero h2 { color: var(--portal-ink); font-size: clamp(23px, 3vw, 31px); font-weight: 760; letter-spacing: -.03em; }
        .portal-hero p { color: var(--portal-muted); font-size: 13px; }
        .stat-card { background: #fff; border: 1px solid var(--portal-border); border-radius: 16px; box-shadow: 0 7px 20px rgba(28, 37, 56, .045); height: 100%; padding: 18px; }
        .stat-card i { color: var(--portal-gold); font-size: 18px; }
        .stat-card small { color: var(--portal-muted); display: block; font-size: 10px; font-weight: 750; letter-spacing: .06em; margin: 9px 0 3px; text-transform: uppercase; }
        .stat-card h3 { font-size: 27px; font-weight: 760; }
        .eyebrow { color: var(--portal-gold); font-size: .68rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        .status-pill { background: #eef4ff; border-radius: 999px; color: #315d9d; display: inline-flex; font-size: .75rem; font-weight: 750; padding: .4rem .72rem; white-space: nowrap; }
        .status-pill.success { background: #e8f7ed; color: #187b46; }
        .status-pill.warning { background: #fff4d8; color: #8a6610; }
        .status-pill.danger { background: #fdebec; color: #a91e2b; }
        .btn { border-radius: 10px; font-weight: 650; min-height: 40px; }
        .form-label { color: #344054; font-size: 12px; font-weight: 700; margin-bottom: 7px; }
        .form-control, .form-select, .select2-container .select2-selection--single { border-color: #dce1e9; border-radius: 10px; min-height: 44px; }
        .form-control:focus, .form-select:focus { border-color: rgba(184, 134, 59, .8); box-shadow: 0 0 0 3px rgba(184, 134, 59, .12); }
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { padding: 7px 10px; }
        .select2-container .select2-selection__arrow { height: 42px !important; }
        .select2-dropdown { border-color: #dce1e9; border-radius: 10px; overflow: hidden; }
        .select2-results__option { font-size: 13px; padding: 9px 11px; }
        .table-responsive { border-radius: 14px; }
        .table > :not(caption) > * > * { border-color: #edf0f4; padding: .9rem .85rem; vertical-align: middle; }
        .table thead th { background: #f7f8fa; color: #596579; font-size: 10px; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
        .alert { border: 0; border-left: 4px solid currentColor; border-radius: 12px; box-shadow: 0 5px 18px rgba(28, 37, 56, .05); }
        @media (max-width: 767px) {
            .form-control, .form-select { font-size: 16px; }
            .portal-shell { padding: 18px 12px 42px; }
            .portal-hero { align-items: stretch; flex-direction: column; padding: 19px; }
            .portal-hero .btn { width: 100%; }
            .portal-user-chip { background: transparent; border: 0; padding: 0; }
            .portal-scroll-shell { border: 1px solid var(--portal-border); border-radius: 0; overflow-x: auto; overscroll-behavior-inline: contain; }
            .portal-scroll-shell::before { background: #f7f8fa; border-bottom: 1px solid var(--portal-border); color: var(--portal-muted); content: 'Swipe horizontally to view all columns  →'; display: block; font-size: 10px; font-weight: 700; padding: 8px 10px; text-align: right; }
            .portal-scroll-table { min-width: 760px; table-layout: auto; width: 100%; }
            .portal-scroll-table thead { display: table-header-group; }
            .portal-scroll-table tbody { display: table-row-group; }
            .portal-scroll-table tr { display: table-row; }
            .portal-scroll-table th, .portal-scroll-table td { display: table-cell; font-size: 11px; padding: 10px 11px !important; text-align: left; vertical-align: middle; white-space: nowrap; }
            .portal-scroll-table th:first-child, .portal-scroll-table td:first-child { left: 0; position: sticky; }
            .portal-scroll-table th:first-child { box-shadow: 5px 0 10px -9px rgba(15, 23, 42, .9); z-index: 3; }
            .portal-scroll-table td:first-child { background: #fff; box-shadow: 5px 0 10px -9px rgba(15, 23, 42, .7); z-index: 2; }
            .portal-scroll-table td::before { content: none; display: none; }
        }
    </style>
</head>
<body>
<?php $roleLabel = str_replace('_', ' ', (string) session('customer_user_role')); ?>
<nav class="navbar navbar-dark portal-nav px-3 px-lg-4 py-3">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= site_url('customer/orders') ?>">
        <span class="brand-mark">A</span>
        <span><strong>Aabhushan</strong><small class="d-block opacity-75">Customer Order Portal</small></span>
    </a>
    <?php if (session('customer_user_logged_in')): ?>
        <div class="d-flex align-items-center gap-2 gap-lg-3 ms-auto">
            <div class="d-none d-lg-flex align-items-center gap-1">
                <a class="portal-nav-link" href="<?= site_url('customer/orders') ?>"><i class="fe fe-list me-1"></i>My Orders</a>
                <a class="portal-nav-link" href="<?= site_url('customer/orders/create') ?>"><i class="fe fe-plus-circle me-1"></i>Create Order</a>
            </div>
            <div class="portal-user-chip">
                <span class="portal-user-avatar"><i class="fe fe-user"></i></span>
                <span class="d-none d-sm-block"><strong><?= esc((string) session('customer_user_name')) ?></strong><small><?= esc($roleLabel) ?></small></span>
            </div>
            <a class="btn btn-sm btn-outline-light" href="<?= site_url('customer/logout') ?>"><i class="fe fe-log-out me-sm-1"></i><span class="d-none d-sm-inline">Logout</span></a>
        </div>
    <?php endif; ?>
</nav>
<main class="portal-shell">
    <?php if (session('success')): ?><div class="alert alert-success"><?= esc((string) session('success')) ?></div><?php endif; ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc((string) session('error')) ?></div><?php endif; ?>
    <?= $this->renderSection('content') ?>
</main>
<script src="<?= base_url('template/assets/js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('template/assets/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('template/assets/plugins/select2/js/select2.min.js') ?>"></script>
<script>
    jQuery(function ($) {
        $('.js-searchable-select').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) return;
            $(this).select2({
                width: '100%',
                placeholder: $(this).data('placeholder') || 'Search and select',
                allowClear: !this.required
            });
        });
    });
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
