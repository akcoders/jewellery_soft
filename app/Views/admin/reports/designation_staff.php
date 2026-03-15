<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Designations</small>
            <strong><?= (int) ($cards['designations'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Team-Lead Designations</small>
            <strong><?= (int) ($cards['team_designations'] ?? 0) ?></strong>
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
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
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
                <label class="form-label">Designation Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/designation-staff') ?>" class="btn btn-light">Reset</a>
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
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Reports To</th>
                        <th>Can Manage Team</th>
                        <th>Total Staff</th>
                        <th>Active Staff</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No designation records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['designation_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['department_name'] ?? '-')) ?></td>
                            <td><?= (int) ($row['level_no'] ?? 0) ?></td>
                            <td><?= esc((string) ($row['reports_to_designation_name'] ?? '-')) ?></td>
                            <td><span class="badge <?= (int) ($row['can_manage_team'] ?? 0) === 1 ? 'bg-success' : 'bg-light text-dark' ?>"><?= (int) ($row['can_manage_team'] ?? 0) === 1 ? 'Yes' : 'No' ?></span></td>
                            <td><?= (int) ($row['total_staff'] ?? 0) ?></td>
                            <td><?= (int) ($row['active_staff'] ?? 0) ?></td>
                            <td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
