<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">Year</label><input type="number" min="2025" name="year" class="form-control" value="<?= esc((string) $year) ?>"></div>
            <div class="col-md-2"><label class="form-label">Month</label><input type="number" min="1" max="12" name="month" class="form-control" value="<?= esc((string) $month) ?>"></div>
            <div class="col-md-4"><label class="form-label">Employee</label><select name="employee_id" class="form-select select2"><option value="0">All employees</option><?php foreach (($employees ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>" <?= (int) $employeeId === (int) $row['id'] ? 'selected' : '' ?>><?= esc((string) ($row['full_name'] . ' / ' . ($row['designation_name'] ?? '-'))) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fe fe-filter"></i> Apply</button></div>
            <div class="col-md-2 text-md-end"><?php if (admin_can('performance.targets.manage')): ?><a href="<?= site_url('admin/performance/targets/create') ?>" class="btn btn-outline-primary w-100"><i class="fe fe-plus"></i> New Target</a><?php endif; ?></div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Targets</small><strong><?= (int) ($totals['target_count'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Employees</small><strong><?= (int) ($totals['employee_count'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Target Value</small><strong><?= number_format((float) ($totals['target_value'] ?? 0), 2) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Estimated Incentive</small><strong><?= number_format((float) ($totals['incentive_amount'] ?? 0), 2) ?></strong></div></div></div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Target vs Achievement</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>KPI</th>
                        <th>Target</th>
                        <th>Achieved</th>
                        <th>Achievement %</th>
                        <th>Incentive</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['full_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['designation_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['kpi_name'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['target_value'] ?? 0), 2) ?> <?= esc((string) ($row['unit'] ?? '')) ?></td>
                            <td><?= number_format((float) ($row['achieved_value'] ?? 0), 2) ?> <?= esc((string) ($row['unit'] ?? '')) ?></td>
                            <td><span class="badge <?= (float) ($row['achievement_percent'] ?? 0) >= 100 ? 'bg-success' : 'bg-warning text-dark' ?>"><?= number_format((float) ($row['achievement_percent'] ?? 0), 2) ?>%</span></td>
                            <td><?= number_format((float) ($row['incentive_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
