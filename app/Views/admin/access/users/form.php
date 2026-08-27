<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$user = is_array($user ?? null) ? $user : [];
$selectedRoleIds = array_map('intval', $selectedRoleIds ?? []);
$permissionOverrides = is_array($permissionOverrides ?? null) ? $permissionOverrides : [];
$canManage = admin_can('access.users.manage');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">User Details & Access</h4>
        <p class="text-muted mb-0">Review account details, roles, permission overrides and password security.</p>
    </div>
    <a href="<?= site_url('admin/access/users') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/access/users/' . (int) ($user['id'] ?? 0) . '/update')) ?>" method="post">
    <?= csrf_field() ?>
    <fieldset <?= $canManage ? '' : 'disabled' ?>>
    <div class="row">
        <div class="col-xl-4 d-flex">
            <div class="card w-100">
                <div class="card-header"><h5 class="card-title mb-0">Admin Summary</h5></div>
                <div class="card-body">
                    <div class="small text-muted">Admin User</div>
                    <div class="fw-semibold mb-2"><?= esc((string) ($user['name'] ?? '-')) ?></div>
                    <div class="small text-muted">Email</div>
                    <div class="fw-semibold mb-2"><?= esc((string) ($user['email'] ?? '-')) ?></div>
                    <div class="small text-muted">Employee</div>
                    <div class="fw-semibold mb-2"><?= esc((string) ($user['employee_name'] ?? '-')) ?></div>
                    <div class="small text-muted">Department / Designation</div>
                    <div class="fw-semibold mb-2"><?= esc((string) ($user['department_name'] ?? '-')) ?> / <?= esc((string) ($user['designation_name'] ?? '-')) ?></div>
                    <div class="small text-muted">Status</div>
                    <div class="fw-semibold mb-2"><span class="badge <?= (int) ($user['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($user['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></div>
                    <div class="small text-muted">Created</div>
                    <div class="fw-semibold mb-2"><?= ! empty($user['created_at']) ? esc(date('d M Y, h:i A', strtotime((string) $user['created_at']))) : '-' ?></div>
                    <div class="small text-muted">Last Updated</div>
                    <div class="fw-semibold"><?= ! empty($user['updated_at']) ? esc(date('d M Y, h:i A', strtotime((string) $user['updated_at']))) : '-' ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Assigned Roles</h5></div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($roles as $role): ?>
                            <div class="col-lg-6 mb-2">
                                <label class="d-flex align-items-start gap-2 border rounded p-3 h-100">
                                    <input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" class="mt-1" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
                                    <span>
                                        <span class="d-block fw-semibold"><?= esc((string) $role['name']) ?></span>
                                        <span class="d-block small text-muted"><?= esc((string) ($role['role_code'] ?? '-')) ?></span>
                                        <span class="d-block small text-muted"><?= esc((string) ($role['description'] ?? '-')) ?></span>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Direct Permission Overrides</h5>
                    <span class="text-muted small">Use only for one-off allow or deny exceptions.</span>
                </div>
                <div class="card-body">
                    <?php foreach ($groupedPermissions as $group => $permissions): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2"><?= esc((string) $group) ?></div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Permission</th>
                                            <th>Code</th>
                                            <th style="width:180px;">Override</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($permissions as $permission): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= esc((string) $permission['name']) ?></div>
                                                    <div class="small text-muted"><?= esc((string) ($permission['description'] ?? '-')) ?></div>
                                                </td>
                                                <td><?= esc((string) ($permission['code'] ?? '-')) ?></td>
                                                <td>
                                                    <select name="override_type[<?= (int) $permission['id'] ?>]" class="form-select">
                                                        <option value="">Inherit Role</option>
                                                        <option value="allow" <?= ($permissionOverrides[(int) $permission['id']] ?? '') === 'allow' ? 'selected' : '' ?>>Allow</option>
                                                        <option value="deny" <?= ($permissionOverrides[(int) $permission['id']] ?? '') === 'deny' ? 'selected' : '' ?>>Deny</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($canManage): ?><button type="submit" class="btn btn-primary"><i class="fe fe-save me-1"></i> Save Access Control</button><?php else: ?><div class="alert alert-light border mb-0"><i class="fe fe-eye me-1"></i> View-only access</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

<?php if ($canManage): ?>
<div class="card mt-3">
    <div class="card-header"><h5 class="card-title mb-0">Password Security</h5></div>
    <div class="card-body">
        <div class="alert alert-light border mb-3"><i class="fe fe-shield me-1"></i> Existing passwords cannot be viewed. Updating a password also revokes remember-me and Flutter mobile sessions for this user.</div>
        <form action="<?= site_url('admin/access/users/' . (int) ($user['id'] ?? 0) . '/password') ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-5"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required><small class="text-muted">Minimum 8 characters.</small></div>
                <div class="col-md-5"><label class="form-label">Confirm Password</label><input type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fe fe-key me-1"></i> Update</button></div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
