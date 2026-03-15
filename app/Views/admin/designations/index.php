<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Designation Master</h4>
        <p class="text-muted mb-0">Define job titles, levels, and default reporting lines for staff structure.</p>
    </div>
    <a href="<?= site_url('admin/designations/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus me-1"></i> Add Designation
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Reports To</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No designations found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['designation_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['department_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['level_no'] ?? '1')) ?></td>
                            <td><?= esc((string) ($row['parent_designation_name'] ?? '-')) ?></td>
                            <td>
                                <span class="badge <?= (int) ($row['can_manage_team'] ?? 0) === 1 ? 'bg-info text-dark' : 'bg-light text-dark' ?>">
                                    <?= (int) ($row['can_manage_team'] ?? 0) === 1 ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('admin/designations/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fe fe-edit"></i>
                                </a>
                                <form action="<?= site_url('admin/designations/' . (int) $row['id'] . '/status') ?>" method="post" class="d-inline">
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
