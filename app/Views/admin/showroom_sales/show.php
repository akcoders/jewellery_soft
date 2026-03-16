<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Sale Summary</h5>
                <div class="d-flex gap-2">
                    <?php if ((int) ($sale['invoice_id'] ?? 0) > 0): ?>
                        <a href="<?= site_url('api/documents/invoice/' . (int) $sale['invoice_id']) ?>?download=1" target="_blank" data-loader-off="1" class="btn btn-outline-primary"><i class="fe fe-download"></i> Invoice PDF</a>
                    <?php endif; ?>
                    <a href="<?= site_url('admin/showroom-sales') ?>" class="btn btn-light">Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-3"><small class="text-muted d-block">Sale No</small><strong><?= esc((string) ($sale['sale_no'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Invoice</small><strong><?= esc((string) ($sale['invoice_no'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Sale Date</small><strong><?= esc((string) ($sale['sale_date'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Payment</small><strong><?= esc((string) ($sale['payment_status'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Showroom</small><strong><?= esc((string) ($sale['showroom_name'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Counter</small><strong><?= esc((string) ($sale['counter_name'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Customer</small><strong><?= esc((string) ($sale['customer_name'] ?? '-')) ?></strong></div>
                    <div class="col-md-3"><small class="text-muted d-block">Salesperson</small><strong><?= esc((string) ($sale['salesperson_name'] ?? '-')) ?></strong></div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Taxable</small><strong><?= number_format((float) ($sale['taxable_amount'] ?? 0), 2) ?></strong></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">GST</small><strong><?= number_format((float) ($sale['gst_amount'] ?? 0), 2) ?></strong></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Total</small><strong><?= number_format((float) ($sale['total_amount'] ?? 0), 2) ?></strong></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Received</small><strong><?= number_format((float) ($sale['received_amount'] ?? 0), 2) ?></strong></div></div></div>
                </div>
                <?php if (! empty($sale['notes'])): ?>
                    <div class="alert alert-light border mt-3 mb-0"><?= esc((string) $sale['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Receipts</h5></div>
            <div class="card-body">
                <?php if (($receipts ?? []) === []): ?>
                    <p class="text-muted mb-0">No receipt posted for this sale.</p>
                <?php else: ?>
                    <?php foreach ($receipts as $receipt): ?>
                        <div class="border rounded p-2 mb-2">
                            <strong><?= esc((string) ($receipt['receipt_no'] ?? '-')) ?></strong><br>
                            <small class="text-muted"><?= esc((string) ($receipt['receipt_date'] ?? '-')) ?> / <?= esc((string) ($receipt['payment_mode'] ?? '-')) ?></small>
                            <div class="mt-1">Amount: <?= number_format((float) ($receipt['amount'] ?? 0), 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Sale Line Items</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Gross</th>
                        <th>Net Gold</th>
                        <th>Diamond</th>
                        <th>Stone</th>
                        <th>Rate</th>
                        <th>Amount</th>
                        <th>GST</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($items ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['description'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['diamond_cts'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['stone_wt'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['rate'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
