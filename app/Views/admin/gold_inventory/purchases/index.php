<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Purchases</h4>
    <a href="<?= site_url('admin/gold-inventory/purchases/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Purchase
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
                <a href="<?= site_url('admin/gold-inventory/purchases') ?>" class="btn btn-light">Reset</a>
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
                        <th>Supplier</th>
                        <th>Invoice</th>
                        <th>Location</th>
                        <th>Lines</th>
                        <th>Total Weight</th>
                        <th>Total Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($purchases ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No purchase records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($purchases ?? []) as $purchase): ?>
                        <tr>
                            <td><?= (int) $purchase['id'] ?></td>
                            <td><?= esc((string) $purchase['purchase_date']) ?></td>
                            <td><?= esc((string) ($purchase['supplier_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($purchase['invoice_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($purchase['location_name'] ?? '-')) ?></td>
                            <td><?= (int) $purchase['line_count'] ?></td>
                            <td><?= number_format((float) $purchase['total_weight'], 3) ?> gm</td>
                            <td><?= number_format((float) $purchase['total_value'], 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/gold-inventory/purchases/view/' . $purchase['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/delete') ?>" onsubmit="return confirm('Delete this purchase?');">
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

