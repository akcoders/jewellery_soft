<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Product Master</h4>
    <a href="<?= site_url('admin/gold-inventory/products/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Product
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc((string) ($q ?? '')) ?>" class="form-control" placeholder="Purity / color / form / percent">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-search"></i> Search</button>
                <a href="<?= site_url('admin/gold-inventory/products') ?>" class="btn btn-light">Reset</a>
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
                        <th>Purity %</th>
                        <th>Color</th>
                        <th>Form/Product</th>
                        <th>Weight Bal.</th>
                        <th>Fine Bal.</th>
                        <th>Avg Cost/gm</th>
                        <th>Stock Value</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No products found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= esc((string) ($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA')) ?></td>
                            <td><?= number_format((float) ($row['purity_percent'] ?? $row['master_purity_percent'] ?? 0), 3) ?></td>
                            <td><?= esc((string) ($row['color_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['form_type'] ?? 'Raw')) ?></td>
                            <td><?= number_format((float) ($row['weight_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['fine_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_cost_per_gm'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/gold-inventory/products/' . $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('admin/gold-inventory/products/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Delete this product?');">
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

