<?php
$asset = rtrim((string) $assetBase, '/');
?>
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="light" data-sidebar-size="lg" data-sidebar-image="none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register</title>

    <link rel="shortcut icon" href="<?= esc($asset) ?>/img/favicon.png">
    <link rel="stylesheet" href="<?= esc($asset) ?>/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc($asset) ?>/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?= esc($asset) ?>/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= esc($asset) ?>/css/style.css">
    <script src="<?= esc($asset) ?>/js/layout.js"></script>
</head>
<body>
    <div class="main-wrapper login-body">
        <div class="login-wrapper">
            <div class="container">
                <img class="img-fluid logo-dark mb-2 logo-color" src="<?= esc($asset) ?>/img/logo2.png" alt="Logo">
                <img class="img-fluid logo-light mb-2" src="<?= esc($asset) ?>/img/logo2-white.png" alt="Logo">

                <div class="loginbox">
                    <div class="login-right">
                        <div class="login-right-wrap">
                            <h1>Register</h1>
                            <p class="account-subtitle">Create admin account</p>

                            <?php if (! empty($error)): ?>
                                <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
                            <?php endif; ?>

                            <?php if (! empty($success)): ?>
                                <div class="alert alert-success" role="alert"><?= esc($success) ?></div>
                            <?php endif; ?>

                            <form action="<?= site_url('admin/register') ?>" method="post">
                                <?= csrf_field() ?>
                                <div class="input-block mb-3">
                                    <label class="form-control-label">Name</label>
                                    <input class="form-control" type="text" name="name" value="<?= esc(old('name')) ?>" required>
                                </div>
                                <div class="input-block mb-3">
                                    <label class="form-control-label">Email Address</label>
                                    <input class="form-control" type="email" name="email" value="<?= esc(old('email')) ?>" required>
                                </div>
                                <div class="input-block mb-3">
                                    <label class="form-control-label">Password</label>
                                    <input class="form-control" type="password" name="password" required>
                                </div>
                                <div class="input-block mb-3">
                                    <label class="form-control-label">Confirm Password</label>
                                    <input class="form-control" type="password" name="password_confirm" required>
                                </div>
                                <div class="input-block mb-0">
                                    <button class="btn btn-lg btn-primary w-100" type="submit">Register</button>
                                </div>
                            </form>

                            <div class="text-center dont-have mt-3">Already have an account? <a href="<?= site_url('admin/login') ?>">Login</a></div>
                        </div>
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

