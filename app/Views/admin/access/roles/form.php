<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$row = is_array($row ?? null) ? $row : [];
$selectedPermissionIds = array_map('intval', $selectedPermissionIds ?? []);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1"><?= esc($title ?? 'Role') ?></h4>
        <p class="text-muted mb-0">Keep roles broad and reusable. Use direct user overrides only for exceptions.</p>
    </div>
    <a href="<?= site_url('admin/access/roles') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/access/roles')) ?>" method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-4 d-flex">
            <div class="card w-100">
                <div class="card-header"><h5 class="card-title mb-0">Role Details</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Role Code</label>
                        <input type="text" name="role_code" class="form-control" maxlength="40" required value="<?= esc(old('role_code', (string) ($row['role_code'] ?? ''))) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="name" class="form-control" maxlength="80" required value="<?= esc(old('name', (string) ($row['name'] ?? ''))) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5"><?= esc(old('description', (string) ($row['description'] ?? ''))) ?></textarea>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (string) old('is_active', (string) ($row['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active Role</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fe fe-save me-1"></i> Save Role
                    </button>
                </div>
            </div>
        </div>
        <div class="col-xl-8 d-flex">
            <div class="card w-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Permission Matrix</h5>
                    <span class="text-muted small">Select all operations this role should control.</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($groupedPermissions as $group => $permissions): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="border rounded h-100 p-3">
                                    <div class="fw-semibold mb-2"><?= esc((string) $group) ?></div>
                                    <?php foreach ($permissions as $permission): ?>
                                        <label class="d-flex align-items-start gap-2 border rounded p-2 mb-2">
                                            <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" class="mt-1" <?= in_array((int) $permission['id'], $selectedPermissionIds, true) ? 'checked' : '' ?>>
                                            <span>
                                                <span class="d-block fw-semibold"><?= esc((string) $permission['name']) ?></span>
                                                <span class="d-block text-muted small"><?= esc((string) ($permission['code'] ?? '')) ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
