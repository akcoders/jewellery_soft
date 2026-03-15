<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Departments</small>
            <strong><?= (int) ($cards['departments'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Total Staff</small>
            <strong><?= (int) ($cards['total_staff'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Active Staff</small>
            <strong><?= (int) ($cards['active_staff'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Manager Roles Mapped</small>
            <strong><?= (int) ($cards['managers'] ?? 0) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Department Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-md-9">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/department-staff') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Total Staff</th>
                        <th>Active Staff</th>
                        <th>Inactive Staff</th>
                        <th>Manager Roles Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No department records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['department_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['name'] ?? '-')) ?></td>
                            <td><?= (int) ($row['total_staff'] ?? 0) ?></td>
                            <td><?= (int) ($row['active_staff'] ?? 0) ?></td>
                            <td><?= (int) ($row['inactive_staff'] ?? 0) ?></td>
                            <td><?= (int) ($row['managers'] ?? 0) ?></td>
                            <td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
