<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Customer Portal') ?> · Aabhushan</title>
    <link rel="stylesheet" href="<?= base_url('template/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('template/assets/plugins/select2/css/select2.min.css') ?>">
    <style>
        :root{--ink:#172033;--muted:#687386;--gold:#b8863b;--paper:#f6f7fb}
        body{background:var(--paper);color:var(--ink);min-height:100vh}
        .portal-nav{background:linear-gradient(120deg,#171d2c,#2f2330);box-shadow:0 8px 28px rgba(20,25,40,.18)}
        .brand-mark{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#d6b26e,#a77028);color:#fff;font-weight:800}
        .portal-shell{max-width:1240px;margin:0 auto;padding:28px 18px 60px}
        .portal-card{border:0;border-radius:18px;box-shadow:0 12px 32px rgba(28,37,56,.07)}
        .stat-card{border:1px solid #e8eaf0;border-radius:16px;background:#fff;padding:18px}
        .eyebrow{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--gold);font-weight:800}
        .status-pill{padding:.38rem .7rem;border-radius:999px;background:#eef4ff;color:#315d9d;font-size:.78rem;font-weight:700;white-space:nowrap}
        .table-responsive{border-radius:14px}.table>:not(caption)>*>*{padding:.9rem .8rem;vertical-align:middle}
        .select2-container{width:100%!important}.select2-container .select2-selection--single{height:42px;border:1px solid #dee2e6;border-radius:.375rem;padding:6px 8px}.select2-selection__arrow{height:40px!important}
        @media(max-width:767px){.portal-shell{padding:18px 12px 42px}.mobile-card-table thead{display:none}.mobile-card-table,.mobile-card-table tbody,.mobile-card-table tr,.mobile-card-table td{display:block;width:100%}.mobile-card-table tr{margin-bottom:14px;border:1px solid #e5e8ef;border-radius:14px;padding:10px;background:#fff}.mobile-card-table td{border:0!important;padding:6px 8px}.mobile-card-table td:before{content:attr(data-label);display:block;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);font-weight:700}}
    </style>
</head>
<body>
<nav class="navbar navbar-dark portal-nav px-3 px-lg-4 py-3">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= site_url('customer/orders') ?>"><span class="brand-mark">A</span><span><strong>Aabhushan</strong><small class="d-block opacity-75">Customer Order Portal</small></span></a>
    <?php if (session('customer_user_logged_in')): ?><div class="d-flex align-items-center gap-2 text-white"><span class="d-none d-md-inline small"><?= esc((string) session('customer_user_name')) ?></span><a class="btn btn-sm btn-outline-light" href="<?= site_url('customer/logout') ?>">Logout</a></div><?php endif; ?>
</nav>
<main class="portal-shell">
    <?php if (session('success')): ?><div class="alert alert-success"><?= esc((string) session('success')) ?></div><?php endif; ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc((string) session('error')) ?></div><?php endif; ?>
    <?= $this->renderSection('content') ?>
</main>
<script src="<?= base_url('template/assets/js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('template/assets/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('template/assets/plugins/select2/js/select2.min.js') ?>"></script>
<script>jQuery(function($){$('.js-searchable-select').select2({width:'100%',placeholder:function(){return $(this).data('placeholder')||'Search and select';},allowClear:true});});</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
