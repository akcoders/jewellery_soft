<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc($dateFrom ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc($dateTo ?? '') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="<?= site_url('admin/accounts/gst-report') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Sales Taxable</small>
            <h5 class="mb-0">Rs <?= number_format((float) ($summary['sales_taxable'] ?? 0), 2) ?></h5>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Output GST</small>
            <h5 class="mb-0">Rs <?= number_format((float) ($summary['sales_gst'] ?? 0), 2) ?></h5>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Input GST</small>
            <h5 class="mb-0">Rs <?= number_format((float) ($summary['purchase_gst'] ?? 0), 2) ?></h5>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Net GST Payable</small>
            <h5 class="mb-0">Rs <?= number_format((float) ($summary['net_gst_payable'] ?? 0), 2) ?></h5>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">GST Adjustments</h5></div>
            <div class="card-body">
                <div class="small text-muted mb-1">Sales debit notes GST: Rs <?= number_format((float) ($summary['sales_debit_gst'] ?? 0), 2) ?></div>
                <div class="small text-muted mb-1">Sales credit notes GST: Rs <?= number_format((float) ($summary['sales_credit_gst'] ?? 0), 2) ?></div>
                <div class="small text-muted mb-1">Purchase debit notes GST: Rs <?= number_format((float) ($summary['purchase_debit_gst'] ?? 0), 2) ?></div>
                <div class="small text-muted">Purchase credit notes GST: Rs <?= number_format((float) ($summary['purchase_credit_gst'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">GST Working Formula</h5></div>
            <div class="card-body">
                <div class="small text-muted">Net GST = (Output GST + Customer Debit Notes GST - Customer Credit Notes GST) - (Input GST + Vendor Credit Notes GST - Vendor Debit Notes GST)</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Sales GST Register</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>GSTIN</th>
                        <th>Sale No</th>
                        <th>Taxable</th>
                        <th>GST</th>
                        <th>Total</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($salesRows === []): ?><tr><td colspan="9" class="text-center text-muted">No sales GST records found.</td></tr><?php endif; ?>
                    <?php foreach ($salesRows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['invoice_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['invoice_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['customer_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['gstin'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['sale_no'] ?? '-')) ?></td>
                            <td>Rs <?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['received_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Purchase GST Register</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>GSTIN</th>
                        <th>Taxable</th>
                        <th>GST %</th>
                        <th>GST</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($purchaseRows === []): ?><tr><td colspan="9" class="text-center text-muted">No purchase GST records found.</td></tr><?php endif; ?>
                    <?php foreach ($purchaseRows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['source_label'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['invoice_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['invoice_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['gstin'] ?? '-')) ?></td>
                            <td>Rs <?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['gst_percent'] ?? 0), 2) ?>%</td>
                            <td>Rs <?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Debit / Credit Note GST Register</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Note Type</th>
                        <th>Note No</th>
                        <th>Date</th>
                        <th>Party Type</th>
                        <th>Party</th>
                        <th>Taxable</th>
                        <th>GST %</th>
                        <th>GST</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($adjustmentRows === []): ?><tr><td colspan="9" class="text-center text-muted">No GST adjustments found.</td></tr><?php endif; ?>
                    <?php foreach ($adjustmentRows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['note_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['note_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['note_date'] ?? '-')) ?></td>
                            <td><?= esc(ucfirst((string) ($row['party_type'] ?? '-'))) ?></td>
                            <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                            <td>Rs <?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['gst_percent'] ?? 0), 2) ?>%</td>
                            <td>Rs <?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
