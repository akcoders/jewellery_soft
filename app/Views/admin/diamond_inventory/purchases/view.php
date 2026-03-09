<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Purchase #<?= (int) $purchase['id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/diamond-inventory/purchases/' . $purchase['id'] . '/edit') ?>" class="btn btn-outline-info">
            <i class="fe fe-edit"></i> Edit
        </a>
        <a href="<?= site_url('admin/diamond-inventory/purchases') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) $purchase['purchase_date']) ?></div>
            <div class="col-md-3"><strong>Supplier:</strong> <?= esc((string) ($purchase['vendor_name'] ?: $purchase['supplier_name'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Invoice:</strong> <?= esc((string) ($purchase['invoice_no'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Due Date:</strong> <?= esc((string) ($purchase['due_date'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Total Carat:</strong> <?= number_format((float) $totals['total_carat'], 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Total PCS:</strong> <?= number_format((float) $totals['total_pcs'], 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Subtotal:</strong> <?= number_format((float) $totals['total_value'], 2) ?></div>
            <div class="col-md-3 mt-2"><strong>Tax %:</strong> <?= number_format((float) ($purchase['tax_percentage'] ?? 0), 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Invoice Total:</strong> <?= number_format((float) ($purchase['invoice_total'] ?? $totals['total_value']), 2) ?></div>
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
                        <th>Item</th>
                        <th>Chalni</th>
                        <th>Color</th>
                        <th>Clarity</th>
                        <th>PCS</th>
                        <th>Carat</th>
                        <th>Rate/cts</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['diamond_type'] . ' ' . ($line['shape'] ? '(' . $line['shape'] . ')' : ''))) ?></td>
                            <td><?= esc(($line['chalni_from'] !== null && $line['chalni_to'] !== null) ? ($line['chalni_from'] . ' - ' . $line['chalni_to']) : 'NA') ?></td>
                            <td><?= esc((string) ($line['color'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['clarity'] ?? '-')) ?></td>
                            <td><?= number_format((float) $line['pcs'], 3) ?></td>
                            <td><?= number_format((float) $line['carat'], 3) ?></td>
                            <td><?= number_format((float) $line['rate_per_carat'], 2) ?></td>
                            <td><?= number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Attachments</h6></div>
    <div class="card-body">
        <?php if (($attachments ?? []) === []): ?>
            <div class="text-muted">No attachments uploaded.</div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach (($attachments ?? []) as $file): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= esc((string) ($file['file_name'] ?? 'File')) ?></span>
                        <a href="<?= base_url((string) ($file['file_path'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
