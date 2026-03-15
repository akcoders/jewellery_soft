<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Employee Master</h4>
        <p class="text-muted mb-0">Manage internal staff records, admin login linkage, and hierarchy entry points.</p>
    </div>
    <a href="<?= site_url('admin/employees/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus me-1"></i> Add Employee
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Location</th>
                        <th>Admin Login</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No employees found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['employee_code'] ?? '-')) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc((string) ($row['full_name'] ?? '-')) ?></div>
                                <div class="text-muted small"><?= esc((string) ($row['mobile'] ?? '-')) ?></div>
                            </td>
                            <td><?= esc((string) ($row['department_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['designation_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['work_location'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['admin_user_name'] ?? '-')) ?></td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('admin/employees/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fe fe-edit"></i>
                                </a>
                                <a href="<?= site_url('admin/employee-hierarchy?employee_id=' . (int) $row['id']) ?>" class="btn btn-sm btn-outline-info" title="Manage Hierarchy">
                                    <i class="fe fe-git-branch"></i>
                                </a>
                                <form action="<?= site_url('admin/employees/' . (int) $row['id'] . '/status') ?>" method="post" class="d-inline">
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
