<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Managers in View</small>
            <strong><?= (int) ($cards['managers'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Direct Reports</small>
            <strong><?= (int) ($cards['direct_reports'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Observation Coverage</small>
            <strong><?= (int) ($cards['observing_reports'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Approval Coverage</small>
            <strong><?= (int) (($cards['reviewing_reports'] ?? 0) + ($cards['approving_reports'] ?? 0)) ?></strong>
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
            <div class="col-md-4">
                <label class="form-label">Manager</label>
                <select name="manager_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($employees ?? []) as $employee): ?>
                        <option value="<?= (int) $employee['id'] ?>" <?= (int) ($filters['manager_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $employee['full_name']) ?><?= $employee['designation_name'] ? ' - ' . esc((string) $employee['designation_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/staff-hierarchy') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (($rows ?? []) === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">No hierarchy records found for the selected filters.</div>
    </div>
<?php endif; ?>

<?php foreach (($rows ?? []) as $row): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h6 class="mb-1"><?= esc((string) ($row['full_name'] ?? '-')) ?></h6>
                    <div class="text-muted small">
                        <?= esc((string) ($row['employee_code'] ?? '-')) ?> |
                        <?= esc((string) ($row['designation_name'] ?? '-')) ?> |
                        <?= esc((string) ($row['department_name'] ?? '-')) ?>
                        <?php if (! empty($row['work_location'])): ?>
                            | <?= esc((string) $row['work_location']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-primary">Direct: <?= (int) ($row['direct_reports'] ?? 0) ?></span>
                    <span class="badge bg-info text-dark">Observe: <?= (int) ($row['observing_reports'] ?? 0) ?></span>
                    <span class="badge bg-warning">Review: <?= (int) ($row['reviewing_reports'] ?? 0) ?></span>
                    <span class="badge bg-success">Approve: <?= (int) ($row['approving_reports'] ?? 0) ?></span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered mb-0" data-dt-skip="1">
                    <thead>
                        <tr>
                            <th>Direct Team Member</th>
                            <th>Department</th>
                            <th>Designation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($row['team_members'] ?? []) === []): ?>
                            <tr><td colspan="3" class="text-center text-muted">No direct reporting team members.</td></tr>
                        <?php endif; ?>
                        <?php foreach (($row['team_members'] ?? []) as $member): ?>
                            <tr>
                                <td><?= esc((string) ($member['employee_code'] ?? '-')) ?> - <?= esc((string) ($member['full_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($member['department_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($member['designation_name'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>
