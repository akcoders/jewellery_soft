<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Role Master</h4>
        <p class="text-muted mb-0">Create reusable role bundles from your permission catalog, then map users to those roles.</p>
    </div>
    <?php if (admin_can('access.roles.manage')): ?>
        <a href="<?= site_url('admin/access/roles/create') ?>" class="btn btn-primary">
            <i class="fe fe-plus me-1"></i> Add Role
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No roles found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc((string) ($row['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['role_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['description'] ?? '-')) ?></td>
                            <td><span class="badge bg-primary"><?= (int) ($row['permission_count'] ?? 0) ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= (int) ($row['user_count'] ?? 0) ?></span></td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (admin_can('access.roles.manage')): ?>
                                    <a href="<?= site_url('admin/access/roles/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form action="<?= site_url('admin/access/roles/' . (int) $row['id'] . '/status') ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Status">
                                            <i class="fe fe-refresh-cw"></i>
                                        </button>
                                    </form>
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
