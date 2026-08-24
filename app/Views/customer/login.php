<?= $this->extend('customer/layout') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center py-lg-5">
    <div class="col-md-7 col-lg-5">
        <div class="portal-card card overflow-hidden">
            <div class="card-body p-4 p-lg-5">
                <span class="eyebrow">Secure access</span>
                <h2 class="mt-2 mb-2">Welcome back</h2>
                <p class="text-muted mb-4">Login to create orders and track their current status.</p>
                <form method="post" action="<?= site_url('customer/login') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Email address</label><input type="email" name="email" class="form-control form-control-lg" value="<?= esc(old('email')) ?>" autocomplete="email" required></div>
                    <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required></div>
                    <button class="btn btn-dark btn-lg w-100">Login to Portal</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
