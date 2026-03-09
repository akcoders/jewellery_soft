<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Purchase #<?= (int) $purchase['id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/edit') ?>" class="btn btn-outline-info">
            <i class="fe fe-edit"></i> Edit
        </a>
        <a href="<?= site_url('admin/gold-inventory/purchases') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) $purchase['purchase_date']) ?></div>
            <div class="col-md-3"><strong>Supplier:</strong> <?= esc((string) ($purchase['supplier_name'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Invoice:</strong> <?= esc((string) ($purchase['invoice_no'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Location:</strong> <?= esc((string) ($purchase['location_name'] ?? '-')) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Weight:</strong> <?= number_format((float) $totals['total_weight'], 3) ?> gm</div>
            <div class="col-md-3 mt-2"><strong>Total Fine:</strong> <?= number_format((float) $totals['total_fine'], 3) ?> gm</div>
            <div class="col-md-3 mt-2"><strong>Total Value:</strong> <?= number_format((float) $totals['total_value'], 2) ?></div>
            <div class="col-md-6 mt-2"><strong>Notes:</strong> <?= esc((string) ($purchase['notes'] ?: '-')) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Purity</th>
                        <th>Color</th>
                        <th>Form</th>
                        <th>Weight (gm)</th>
                        <th>Fine (gm)</th>
                        <th>Rate/gm</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['master_purity_code'] ?: $line['purity_code'] ?: 'NA')) ?></td>
                            <td><?= esc((string) ($line['color_name'] ?? 'NA')) ?></td>
                            <td><?= esc((string) ($line['form_type'] ?? 'Raw')) ?></td>
                            <td><?= number_format((float) $line['weight_gm'], 3) ?></td>
                            <td><?= number_format((float) $line['fine_weight_gm'], 3) ?></td>
                            <td><?= number_format((float) $line['rate_per_gm'], 2) ?></td>
                            <td><?= number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

