<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Permission Master</h4>
        <p class="text-muted mb-0">Maintain the permission catalog used by roles, user overrides, menus, and route guards.</p>
    </div>
    <?php if (admin_can('access.permissions.manage')): ?>
        <a href="<?= site_url('admin/access/permissions/create') ?>" class="btn btn-primary">
            <i class="fe fe-plus me-1"></i> Add Permission
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Code</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No permissions found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc((string) ($row['name'] ?? '-')) ?></div>
                                <div class="small text-muted"><?= esc((string) ($row['description'] ?? '-')) ?></div>
                            </td>
                            <td><?= esc((string) ($row['code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['module_group'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['action_key'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['sort_order'] ?? '0')) ?></td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (admin_can('access.permissions.manage')): ?>
                                    <a href="<?= site_url('admin/access/permissions/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form action="<?= site_url('admin/access/permissions/' . (int) $row['id'] . '/status') ?>" method="post" class="d-inline">
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
