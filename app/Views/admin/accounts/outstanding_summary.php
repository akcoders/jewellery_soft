<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Customer Outstanding</small>
            <h4 class="mb-0">Rs <?= number_format((float) ($summary['customer_outstanding'] ?? 0), 2) ?></h4>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Vendor Outstanding</small>
            <h4 class="mb-0">Rs <?= number_format((float) ($summary['vendor_outstanding'] ?? 0), 2) ?></h4>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block">Karigar Outstanding</small>
            <h4 class="mb-0">Rs <?= number_format((float) ($summary['karigar_outstanding'] ?? 0), 2) ?></h4>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Customer Receivables</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead><tr><th>Customer</th><th>Bills</th><th>Amount</th><th>Received</th><th>Outstanding</th></tr></thead>
                <tbody>
                <?php if ($customerRows === []): ?><tr><td colspan="5" class="text-center text-muted">No customer outstanding found.</td></tr><?php endif; ?>
                <?php foreach ($customerRows as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                        <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['paid'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Vendor Payables</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead><tr><th>Vendor</th><th>Bills</th><th>Amount</th><th>Paid</th><th>Outstanding</th></tr></thead>
                <tbody>
                <?php if ($vendorRows === []): ?><tr><td colspan="5" class="text-center text-muted">No vendor outstanding found.</td></tr><?php endif; ?>
                <?php foreach ($vendorRows as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                        <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['paid'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Karigar Payables</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead><tr><th>Karigar</th><th>Bills</th><th>Amount</th><th>Paid</th><th>Outstanding</th></tr></thead>
                <tbody>
                <?php if ($karigarRows === []): ?><tr><td colspan="5" class="text-center text-muted">No karigar outstanding found.</td></tr><?php endif; ?>
                <?php foreach ($karigarRows as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                        <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['paid'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
