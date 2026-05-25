<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $type = (string) ($type ?? '');
    $label = ['vendor' => 'Vendor', 'karigar' => 'Karigar', 'customer' => 'Customer'][$type] ?? ucfirst($type);
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0"><?= esc((string) ($partyName ?? '-')) ?></h4>
        <small class="text-muted"><?= esc($label) ?> ledger with purchases, sales, payments, credit/debit notes and journal entries.</small>
    </div>
    <a href="<?= site_url('admin/accounts/party-balances/' . $type) ?>" class="btn btn-light">Back to Pending</a>
</div>

<form method="get" action="<?= site_url('admin/accounts/party-ledger/' . $type . '/' . (int) ($partyId ?? 0)) ?>" class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= site_url('admin/accounts/party-ledger/' . $type . '/' . (int) ($partyId ?? 0)) ?>" class="btn btn-light">Reset</a>
                <button type="button" class="btn btn-outline-success" data-export-table="#party-ledger-table" data-export-name="<?= esc($type) ?>-ledger.xls">Export Excel</button>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Entries</small><h5 class="mb-0"><?= (int) ($summary['row_count'] ?? 0) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Debit</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['debit_amount'] ?? 0), 2) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Credit</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['credit_amount'] ?? 0), 2) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Open Balance</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['balance_amount'] ?? 0), 2) ?></h5></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="party-ledger-table" class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Bill/Invoice</th>
                        <th>Order</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Open Balance</th>
                        <th>Status</th>
                        <th>Mode</th>
                        <th>Details</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="12" class="text-center text-muted">No ledger entries found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['transaction_date'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['transaction_type'] ?? '-')) ?></td>
                        <td><?= esc((string) (($row['reference_no'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['bill_no'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['order_no'] ?? '') ?: '-')) ?></td>
                        <td>Rs <?= number_format((float) ($row['debit_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['credit_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['balance_amount'] ?? 0), 2) ?></td>
                        <td><?= esc((string) (($row['status'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['payment_mode'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) ($row['details'] ?? '')) ?></td>
                        <td><?= esc((string) ($row['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        document.addEventListener('click', function (event) {
            const button = event.target instanceof Element ? event.target.closest('[data-export-table]') : null;
            if (!button) return;

            const table = document.querySelector(button.getAttribute('data-export-table'));
            if (!table) return;

            const html = '<html><head><meta charset="utf-8"></head><body>' + table.outerHTML + '</body></html>';
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = button.getAttribute('data-export-name') || 'party-ledger.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    })();
</script>
<?= $this->endSection() ?>
