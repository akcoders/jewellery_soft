<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Users & access</span>
        <h4 class="mb-1">Create User</h4>
        <p class="mb-0">Create a secure admin/mobile login and assign its initial role.</p>
    </div>
    <a href="<?= site_url('admin/access/users') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i> Back</a>
</div>

<form action="<?= site_url('admin/access/users') ?>" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Login Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="user-name">Full Name <span class="text-danger">*</span></label>
                            <input id="user-name" type="text" name="name" class="form-control" value="<?= esc((string) old('name')) ?>" maxlength="150" autocomplete="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user-email">Email <span class="text-danger">*</span></label>
                            <input id="user-email" type="email" name="email" class="form-control" value="<?= esc((string) old('email')) ?>" maxlength="191" autocomplete="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user-password">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="user-password" type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary js-password-toggle" type="button" data-target="user-password" aria-label="Show password"><i class="fe fe-eye"></i></button>
                            </div>
                            <small class="text-muted">Minimum 8 characters.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user-password-confirm">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="user-password-confirm" type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary js-password-toggle" type="button" data-target="user-password-confirm" aria-label="Show password"><i class="fe fe-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="user-active" name="is_active" value="1" <?= old('is_active', '1') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="user-active">Active login</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Initial Role <span class="text-danger">*</span></h5></div>
                <div class="card-body">
                    <p class="text-muted small">At least one role is required. More permissions can be adjusted from User Details after creation.</p>
                    <?php $oldRoles = array_map('intval', (array) old('role_ids', [])); ?>
                    <div class="d-grid gap-2">
                        <?php foreach (($roles ?? []) as $role): ?>
                            <label class="border rounded p-3 d-flex gap-2 align-items-start">
                                <input class="form-check-input mt-1" type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $oldRoles, true) ? 'checked' : '' ?>>
                                <span>
                                    <strong class="d-block"><?= esc((string) ($role['name'] ?? '-')) ?></strong>
                                    <small class="text-muted d-block"><?= esc((string) ($role['role_code'] ?? '-')) ?></small>
                                    <?php if (! empty($role['description'])): ?><small class="text-muted d-block mt-1"><?= esc((string) $role['description']) ?></small><?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= site_url('admin/access/users') ?>" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fe fe-user-plus me-1"></i> Create User</button>
    </div>
</form>

<script>
document.querySelectorAll('.js-password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.target || '');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.innerHTML = '<i class="fe ' + (show ? 'fe-eye-off' : 'fe-eye') + '"></i>';
    });
});
</script>
<?= $this->endSection() ?>
