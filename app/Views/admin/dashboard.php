<?php
$asset = rtrim((string) $assetBase, '/');
?>
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="light" data-sidebar-size="lg" data-sidebar-image="none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link rel="shortcut icon" href="<?= esc($asset) ?>/img/favicon.png">
    <link rel="stylesheet" href="<?= esc($asset) ?>/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc($asset) ?>/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?= esc($asset) ?>/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= esc($asset) ?>/css/style.css">
    <script src="<?= esc($asset) ?>/js/layout.js"></script>
</head>
<body>
    <div class="main-wrapper">
        <div class="header header-one">
            <div class="container-fluid d-flex align-items-center justify-content-between py-2">
                <a href="<?= site_url('admin/dashboard') ?>" class="d-inline-flex align-items-center">
                    <img src="<?= esc($asset) ?>/img/logo.png" class="img-fluid" alt="Logo" style="max-height: 36px;">
                </a>
                <a href="<?= site_url('admin/logout') ?>" class="btn btn-primary">Logout</a>
            </div>
        </div>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="row justify-content-center">
                    <div class="col-xl-6 col-lg-8 col-md-10">
                        <div class="card">
                            <div class="card-body text-center p-5">
                                <h3 class="mb-2">Welcome, <?= esc($adminName) ?></h3>
                                <p class="text-muted mb-4"><?= esc($adminEmail) ?></p>
                                <p class="mb-0">Admin login system is active. Share the next requirement and I will continue from here.</p>
                            </div>
                        </div>

                        <?php if (! empty($success)): ?>
                            <div class="alert alert-success mt-3" role="alert"><?= esc($success) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= esc($asset) ?>/js/jquery-3.7.1.min.js"></script>
    <script src="<?= esc($asset) ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?= esc($asset) ?>/js/feather.min.js"></script>
    <script src="<?= esc($asset) ?>/js/script.js"></script>
</body>
</html>

