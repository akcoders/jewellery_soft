<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Delivered Orders</small>
            <strong><?= (int) ($cards['orders'] ?? 0) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Delivered Gold (gm)</small>
            <strong><?= number_format((float) ($cards['delivered_gm'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Delivered Fine (gm)</small>
            <strong><?= number_format((float) ($cards['delivered_fine_gm'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Average TAT (days)</small>
            <strong><?= number_format((float) ($cards['avg_days'] ?? 0), 2) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mode</label>
                <select name="mode" class="form-select">
                    <option value="month" <?= ($filters['mode'] ?? 'month') === 'month' ? 'selected' : '' ?>>Month Wise</option>
                    <option value="custom" <?= ($filters['mode'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $karigar['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/karigar-performance') ?>" class="btn btn-light">Reset</a>
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
                        <th>Karigar</th>
                        <th>Period</th>
                        <th>Orders</th>
                        <th>Avg Days</th>
                        <th>Delivered gm</th>
                        <th>Delivered Fine gm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['period'] ?? '-')) ?></td>
                            <td><?= (int) ($row['orders'] ?? 0) ?></td>
                            <td><?= number_format((float) ($row['avg_days'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['delivered_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['delivered_fine_gm'] ?? 0), 3) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
