<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Stone Item Master</h4>
    <a href="<?= site_url('admin/stone-inventory/items/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Item
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc((string) ($q ?? '')) ?>" class="form-control" placeholder="Product name / stone type / remarks">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-search"></i> Search</button>
                <a href="<?= site_url('admin/stone-inventory/items') ?>" class="btn btn-light">Reset</a>
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
                        <th>Default Rate</th>
                        <th>Qty Balance</th>
                        <th>Avg Rate</th>
                        <th>Stock Value</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($items ?? []) === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No items found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($items ?? []) as $item): ?>
                        <tr>
                            <td><?= (int) $item['id'] ?></td>
                            <td><?= esc((string) ($item['product_name'] ?? '-')) ?></td>
                            <td><?= esc((string) (($item['stone_type'] ?? '') !== '' ? $item['stone_type'] : '-')) ?></td>
                            <td><?= number_format((float) ($item['default_rate'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($item['qty_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($item['avg_rate'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($item['stock_value'] ?? 0), 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/stone-inventory/items/' . $item['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('admin/stone-inventory/items/' . $item['id'] . '/delete') ?>" onsubmit="return confirm('Delete this item?');">
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

