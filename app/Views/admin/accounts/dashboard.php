<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $vendorBalance = (float) ($summary['vendor_outstanding'] ?? 0);
    $karigarBalance = (float) ($summary['karigar_outstanding'] ?? 0);
    $customerBalance = (float) ($summary['customer_outstanding'] ?? 0);
    $expensePosted = (float) ($journalSummary['expenditure_amount'] ?? 0);
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <a href="<?= site_url('admin/accounts/party-balances/vendor') ?>" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Vendor Payable</small>
                    <h4 class="mb-1">Rs <?= number_format($vendorBalance, 2) ?></h4>
                    <div class="small text-muted">Purchase balance pending</div>
                    <div class="small text-primary mt-2">View pending vendors</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="<?= site_url('admin/accounts/party-balances/karigar') ?>" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Karigar Payable</small>
                    <h4 class="mb-1">Rs <?= number_format($karigarBalance, 2) ?></h4>
                    <div class="small text-muted">Labour balance pending</div>
                    <div class="small text-primary mt-2">View pending karigars</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="<?= site_url('admin/accounts/party-balances/customer') ?>" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Sales Receivable</small>
                    <h4 class="mb-1">Rs <?= number_format($customerBalance, 2) ?></h4>
                    <div class="small text-muted">Customer balance pending</div>
                    <div class="small text-primary mt-2">View pending customers</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="<?= site_url('admin/accounts/general-ledger?party_type=expense') ?>" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Expenditure Posted</small>
                    <h4 class="mb-1">Rs <?= number_format($expensePosted, 2) ?></h4>
                    <div class="small text-muted">Journal expense entries</div>
                    <div class="small text-primary mt-2">View expense ledger</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/journal-vouchers') ?>" class="btn btn-primary w-100">Journal Voucher</a></div>
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/payments') ?>" class="btn btn-outline-primary w-100">Payments</a></div>
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/general-ledger') ?>" class="btn btn-outline-primary w-100">All Ledger</a></div>
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/vendor-transaction-ledger') ?>" class="btn btn-outline-secondary w-100">Issue Receive</a></div>
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/gst-report') ?>" class="btn btn-outline-secondary w-100">GST Report</a></div>
            <div class="col-md-2 col-6"><a href="<?= site_url('admin/accounts/outstanding-summary') ?>" class="btn btn-outline-secondary w-100">Outstanding</a></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Vendor Pending</h5>
                <a href="<?= site_url('admin/accounts/party-balances/vendor') ?>" class="btn btn-sm btn-outline-primary">View</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Vendor</th><th>Bills</th><th>Balance</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorRows ?? []) as $row): ?>
                            <tr>
                                <td><a href="<?= site_url('admin/accounts/party-ledger/vendor/' . (int) ($row['party_id'] ?? 0)) ?>"><?= esc((string) ($row['party_name'] ?? '-')) ?></a></td>
                                <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                                <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorRows ?? []) === []): ?><tr><td colspan="3" class="text-center text-muted">No pending vendors</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Karigar Pending</h5>
                <a href="<?= site_url('admin/accounts/party-balances/karigar') ?>" class="btn btn-sm btn-outline-primary">View</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Karigar</th><th>Bills</th><th>Balance</th></tr></thead>
                        <tbody>
                        <?php foreach (($karigarRows ?? []) as $row): ?>
                            <tr>
                                <td><a href="<?= site_url('admin/accounts/party-ledger/karigar/' . (int) ($row['party_id'] ?? 0)) ?>"><?= esc((string) ($row['party_name'] ?? '-')) ?></a></td>
                                <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                                <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($karigarRows ?? []) === []): ?><tr><td colspan="3" class="text-center text-muted">No pending karigars</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Customer Pending</h5>
                <a href="<?= site_url('admin/accounts/party-balances/customer') ?>" class="btn btn-sm btn-outline-primary">View</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" data-dt-skip="1">
                        <thead><tr><th>Customer</th><th>Bills</th><th>Balance</th></tr></thead>
                        <tbody>
                        <?php foreach (($customerRows ?? []) as $row): ?>
                            <tr>
                                <td><a href="<?= site_url('admin/accounts/party-ledger/customer/' . (int) ($row['party_id'] ?? 0)) ?>"><?= esc((string) ($row['party_name'] ?? '-')) ?></a></td>
                                <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                                <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($customerRows ?? []) === []): ?><tr><td colspan="3" class="text-center text-muted">No pending customers</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
