<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Stone Stock Summary</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/stone-inventory/purchases/create') ?>" class="btn btn-outline-primary"><i class="fe fe-plus"></i> Purchase</a>
        <a href="<?= site_url('admin/stone-inventory/issues/create') ?>" class="btn btn-outline-primary"><i class="fe fe-upload"></i> Issue</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Product Name</label>
                <select name="product_name" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['product_names'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['product_name'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Stone Type</label>
                <select name="stone_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($filterOptions['stone_types'] ?? []) as $value): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['stone_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Apply</button>
                <a href="<?= site_url('admin/stone-inventory/stock') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty Balance</th>
                        <th>Avg Rate</th>
                        <th>Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No stock records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= esc((string) ($row['product_name'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['stone_type'] ?? '') !== '' ? $row['stone_type'] : '-')) ?></td>
                            <td><?= number_format((float) ($row['qty_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_rate'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

