<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Total Staff</small>
            <strong><?= (int) ($cards['total'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Active Staff</small>
            <strong><?= (int) ($cards['active'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Inactive Staff</small>
            <strong><?= (int) ($cards['inactive'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Locations</small>
            <strong><?= (int) ($cards['locations'] ?? 0) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($departments ?? []) as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $department['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Designation</label>
                <select name="designation_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($designations ?? []) as $designation): ?>
                        <option value="<?= (int) $designation['id'] ?>" <?= (int) ($filters['designation_id'] ?? 0) === (int) $designation['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $designation['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" value="<?= esc((string) ($filters['location'] ?? '')) ?>" placeholder="Branch / Office">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/staff-directory') ?>" class="btn btn-light">Reset</a>
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
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Reporting Manager</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No staff records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['employee_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['full_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['department_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['designation_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['reporting_manager_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['mobile'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['email'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['work_location'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['joining_date'] ?? '-')) ?></td>
                            <td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
