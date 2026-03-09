<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Diamond Stock Summary</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/diamond-inventory/purchases/create') ?>" class="btn btn-outline-primary"><i class="fe fe-plus"></i> Purchase</a>
        <a href="<?= site_url('admin/diamond-inventory/issues/create') ?>" class="btn btn-outline-primary"><i class="fe fe-upload"></i> Issue</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="diamond_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['diamond_types'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['diamond_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Shape</label>
                <select name="shape" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['shapes'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['shape'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Color</label>
                <select name="color" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['colors'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['color'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Clarity</label>
                <select name="clarity" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['clarities'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['clarity'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">From</label>
                <input type="text" name="chalni_from" class="form-control" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) ($filters['chalni_from'] ?? '')) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">To</label>
                <input type="text" name="chalni_to" class="form-control" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) ($filters['chalni_to'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Location (optional)</label>
                <input type="text" name="location" class="form-control" value="<?= esc((string) ($filters['location'] ?? '')) ?>" placeholder="Future use">
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Apply</button>
                <a href="<?= site_url('admin/diamond-inventory/stock') ?>" class="btn btn-light">Reset</a>
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
                        <th>Type</th>
                        <th>Shape</th>
                        <th>Chalni</th>
                        <th>Color</th>
                        <th>Clarity</th>
                        <th>Cut</th>
                        <th>PCS Balance</th>
                        <th>Carat Balance</th>
                        <th>Avg Cost/cts</th>
                        <th>Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No stock records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= esc((string) $row['diamond_type']) ?></td>
                            <td><?= esc((string) ($row['shape'] ?? '-')) ?></td>
                            <td><?= esc(($row['chalni_from'] !== null && $row['chalni_to'] !== null) ? ($row['chalni_from'] . ' - ' . $row['chalni_to']) : 'NA') ?></td>
                            <td><?= esc((string) ($row['color'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['clarity'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['cut'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['pcs_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['carat_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_cost_per_carat'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
