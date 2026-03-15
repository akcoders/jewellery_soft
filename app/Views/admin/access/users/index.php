<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">User Access Control</h4>
        <p class="text-muted mb-0">Assign reusable roles first, then use direct permission overrides only where exceptions are needed.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Admin User</th>
                        <th>Employee Link</th>
                        <th>Designation</th>
                        <th>Roles</th>
                        <th>Overrides</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No admin users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc((string) ($row['name'] ?? '-')) ?></div>
                                <div class="small text-muted"><?= esc((string) ($row['email'] ?? '-')) ?></div>
                            </td>
                            <td>
                                <div><?= esc((string) ($row['employee_name'] ?? '-')) ?></div>
                                <div class="small text-muted"><?= esc((string) ($row['employee_code'] ?? '-')) ?></div>
                            </td>
                            <td><?= esc((string) ($row['designation_name'] ?? '-')) ?></td>
                            <td><span class="badge bg-primary"><?= (int) ($row['role_count'] ?? 0) ?></span></td>
                            <td><span class="badge bg-warning"><?= (int) ($row['override_count'] ?? 0) ?></span></td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (admin_can('access.users.manage')): ?>
                                    <a href="<?= site_url('admin/access/users/' . (int) $row['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fe fe-shield"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
