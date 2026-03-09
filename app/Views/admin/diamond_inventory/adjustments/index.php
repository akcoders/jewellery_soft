<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Diamond Stock Adjustments</h4>
    <a href="<?= site_url('admin/diamond-inventory/adjustments/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Adjustment
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from" class="form-control" value="<?= esc((string) ($from ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to" class="form-control" value="<?= esc((string) ($to ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/diamond-inventory/adjustments') ?>" class="btn btn-light">Reset</a>
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
                        <th>ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Lines</th>
                        <th>Total Carat</th>
                        <th>Total Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($adjustments ?? []) === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No adjustment records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($adjustments ?? []) as $adj): ?>
                        <tr>
                            <td><?= (int) $adj['id'] ?></td>
                            <td><?= esc((string) $adj['adjustment_date']) ?></td>
                            <td><span class="badge bg-<?= ($adj['adjustment_type'] ?? 'add') === 'subtract' ? 'danger' : 'success' ?>-light"><?= esc(ucfirst((string) ($adj['adjustment_type'] ?? 'add'))) ?></span></td>
                            <td><?= esc((string) ($adj['location_name'] ?? '-')) ?></td>
                            <td><?= (int) $adj['line_count'] ?></td>
                            <td><?= number_format((float) $adj['total_carat'], 3) ?> cts</td>
                            <td><?= number_format((float) $adj['total_value'], 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/diamond-inventory/adjustments/view/' . $adj['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= site_url('admin/diamond-inventory/adjustments/' . $adj['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/diamond-inventory/adjustments/' . $adj['id'] . '/delete') ?>" onsubmit="return confirm('Delete this adjustment?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

