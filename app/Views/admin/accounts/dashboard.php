<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Sales Receivables</small>
                <h4 class="mb-1">Rs <?= number_format((float) ($cards['sales_pending'] ?? 0), 2) ?></h4>
                <div class="small text-muted">Sales: Rs <?= number_format((float) ($cards['sales_total'] ?? 0), 2) ?></div>
                <div class="small text-success">Received: Rs <?= number_format((float) ($cards['sales_received'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Purchase Payables</small>
                <h4 class="mb-1">Rs <?= number_format((float) ($cards['purchase_pending'] ?? 0), 2) ?></h4>
                <div class="small text-muted">Total bills: Rs <?= number_format((float) ($cards['purchase_total'] ?? 0), 2) ?></div>
                <div class="small text-danger">Overdue: Rs <?= number_format((float) ($cards['purchase_overdue'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Labour Payables</small>
                <h4 class="mb-1">Rs <?= number_format((float) ($cards['labour_pending'] ?? 0), 2) ?></h4>
                <div class="small text-muted">Total bills: Rs <?= number_format((float) ($cards['labour_total'] ?? 0), 2) ?></div>
                <div class="small text-danger">Overdue: Rs <?= number_format((float) ($cards['labour_overdue'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Net GST Payable</small>
                <h4 class="mb-1">Rs <?= number_format((float) ($cards['net_gst_payable'] ?? 0), 2) ?></h4>
                <div class="small text-muted">Output: Rs <?= number_format((float) ($cards['output_gst'] ?? 0), 2) ?></div>
                <div class="small text-primary">Input: Rs <?= number_format((float) ($cards['input_gst'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Customer Outstanding</small>
                <h5 class="mb-1">Rs <?= number_format((float) ($cards['customer_outstanding'] ?? 0), 2) ?></h5>
                <div class="small text-muted">Recovery focus</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Vendor Outstanding</small>
                <h5 class="mb-1">Rs <?= number_format((float) ($cards['vendor_outstanding'] ?? 0), 2) ?></h5>
                <div class="small text-muted">Purchase obligations</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Debit Notes</small>
                <h5 class="mb-1">Rs <?= number_format((float) ($cards['debit_total'] ?? 0), 2) ?></h5>
                <div class="small text-muted">Customer and vendor debit adjustments</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block">Credit Notes</small>
                <h5 class="mb-1">Rs <?= number_format((float) ($cards['credit_total'] ?? 0), 2) ?></h5>
                <div class="small text-muted">Returns, discounts, reversals</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Monthly Sales Trend</h5>
                <a href="<?= site_url('admin/accounts/sale-bills') ?>" class="btn btn-sm btn-outline-primary">View Sales Bills</a>
            </div>
            <div class="card-body">
                <?php if (($monthlySales ?? []) === []): ?>
                    <div class="text-muted">No showroom sales found.</div>
                <?php else: ?>
                    <?php $maxSales = 0.0; foreach ($monthlySales as $row) { $maxSales = max($maxSales, (float) ($row['total_amount'] ?? 0)); } ?>
                    <?php foreach ($monthlySales as $row): ?>
                        <?php $amount = (float) ($row['total_amount'] ?? 0); $received = (float) ($row['received_amount'] ?? 0); ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= esc((string) ($row['ym'] ?? '-')) ?></span>
                                <span>Rs <?= number_format($amount, 2) ?></span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $maxSales > 0 ? round(($amount / $maxSales) * 100, 2) : 0 ?>%"></div>
                            </div>
                            <div class="small text-muted mt-1">Received Rs <?= number_format($received, 2) ?> | Bills <?= (int) ($row['sale_count'] ?? 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Advanced Navigation</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/purchase-bills') ?>" class="btn btn-outline-primary w-100">Purchase Bills</a></div>
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/labour-bills') ?>" class="btn btn-outline-primary w-100">Labour Bills</a></div>
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/sale-bills') ?>" class="btn btn-outline-primary w-100">Sale Bills</a></div>
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/debit-notes') ?>" class="btn btn-outline-dark w-100">Debit Notes</a></div>
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/credit-notes') ?>" class="btn btn-outline-dark w-100">Credit Notes</a></div>
                    <div class="col-md-4"><a href="<?= site_url('admin/accounts/gst-report') ?>" class="btn btn-outline-dark w-100">GST Report</a></div>
                    <div class="col-md-6"><a href="<?= site_url('admin/accounts/outstanding-summary') ?>" class="btn btn-outline-secondary w-100">Outstanding Summary</a></div>
                    <div class="col-md-6"><a href="<?= site_url('admin/reports/transactions') ?>" class="btn btn-outline-secondary w-100">Transaction Report</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-3 col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Recent Purchase Bills</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Supplier</th><th>Pending</th></tr></thead>
                        <tbody>
                        <?php foreach (($purchaseRows ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['supplier_name'] ?? '-')) ?></td>
                                <td>Rs <?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($purchaseRows ?? []) === []): ?><tr><td colspan="2" class="text-center text-muted">No records</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Recent Labour Bills</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Bill</th><th>Pending</th></tr></thead>
                        <tbody>
                        <?php foreach (($labourRows ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['bill_no'] ?? '-')) ?></td>
                                <td>Rs <?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($labourRows ?? []) === []): ?><tr><td colspan="2" class="text-center text-muted">No records</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Recent Debit Notes</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Note</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach (($debitRows ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['note_no'] ?? '-')) ?></td>
                                <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($debitRows ?? []) === []): ?><tr><td colspan="2" class="text-center text-muted">No records</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Recent Credit Notes</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Note</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach (($creditRows ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['note_no'] ?? '-')) ?></td>
                                <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($creditRows ?? []) === []): ?><tr><td colspan="2" class="text-center text-muted">No records</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
