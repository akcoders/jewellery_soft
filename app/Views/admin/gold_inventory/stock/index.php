<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Stock Summary</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/gold-inventory/purities') ?>" class="btn btn-outline-secondary"><i class="fe fe-percent"></i> Purity Master</a>
        <a href="<?= site_url('admin/gold-inventory/products') ?>" class="btn btn-outline-secondary"><i class="fe fe-package"></i> Product Master</a>
        <a href="<?= site_url('admin/gold-inventory/purchases/create') ?>" class="btn btn-outline-primary"><i class="fe fe-plus"></i> Purchase</a>
        <a href="<?= site_url('admin/gold-inventory/issues/create') ?>" class="btn btn-outline-primary"><i class="fe fe-upload"></i> Issue</a>
        <a href="<?= site_url('admin/gold-inventory/returns/create') ?>" class="btn btn-outline-primary"><i class="fe fe-corner-up-left"></i> Return</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Purity</label>
                <select name="purity_code" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['purity_codes'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['purity_code'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Color</label>
                <select name="color_name" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['color_names'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['color_name'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Form</label>
                <select name="form_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['form_types'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['form_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" value="<?= esc((string) ($filters['location'] ?? '')) ?>" placeholder="Future use">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Apply</button>
                <a href="<?= site_url('admin/gold-inventory/stock') ?>" class="btn btn-light">Reset</a>
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
                        <th>Purity</th>
                        <th>Color</th>
                        <th>Form</th>
                        <th>Purity %</th>
                        <th>Weight Balance (gm)</th>
                        <th>Fine Balance (gm)</th>
                        <th>Avg Cost/gm</th>
                        <th>Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No stock records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= esc((string) ($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA')) ?></td>
                            <td><?= esc((string) ($row['color_name'] ?? 'NA')) ?></td>
                            <td><?= esc((string) ($row['form_type'] ?? 'Raw')) ?></td>
                            <td><?= number_format((float) ($row['purity_percent'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['weight_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['fine_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_cost_per_gm'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
