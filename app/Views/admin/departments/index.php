<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Department Master</h4>
        <p class="text-muted mb-0">Manage core departments for staff, hierarchy, and future access control.</p>
    </div>
    <a href="<?= site_url('admin/departments/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus me-1"></i> Add Department
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No departments found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['department_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['sort_order'] ?? 0)) ?></td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= esc((string) ($row['notes'] ?? '-')) ?></td>
                            <td class="text-end">
                                <a href="<?= site_url('admin/departments/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fe fe-edit"></i>
                                </a>
                                <form action="<?= site_url('admin/departments/' . (int) $row['id'] . '/status') ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Status">
                                        <i class="fe fe-refresh-cw"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
