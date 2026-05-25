<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">General Ledger</h4>
    <button type="button" class="btn btn-outline-success" data-export-table="#general-ledger-table" data-export-name="general-ledger.xls">
        <i class="fe fe-download me-1"></i> Export Excel
    </button>
</div>

<form method="get" action="<?= site_url('admin/accounts/general-ledger') ?>" class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Transaction Type</label>
                <select name="transaction_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($transactionTypes ?? []) as $type): ?>
                        <option value="<?= esc((string) $type) ?>" <?= (string) ($filters['transaction_type'] ?? '') === (string) $type ? 'selected' : '' ?>><?= esc((string) $type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Party Type</label>
                <?php $partyType = (string) ($filters['party_type'] ?? ''); ?>
                <select name="party_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['customer' => 'Customer', 'vendor' => 'Vendor', 'karigar' => 'Karigar'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $partyType === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($customers ?? []) as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= esc((string) $customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vendor</label>
                <select name="vendor_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($vendors ?? []) as $vendor): ?>
                        <option value="<?= (int) $vendor['id'] ?>" <?= (int) ($filters['vendor_id'] ?? 0) === (int) $vendor['id'] ? 'selected' : '' ?>><?= esc((string) $vendor['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($statuses ?? []) as $status): ?>
                        <option value="<?= esc((string) $status) ?>" <?= (string) ($filters['status'] ?? '') === (string) $status ? 'selected' : '' ?>><?= esc((string) $status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Reference No</label>
                <input type="text" name="reference_no" class="form-control" value="<?= esc((string) ($filters['reference_no'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Search All</label>
                <input type="text" name="search" class="form-control" value="<?= esc((string) ($filters['search'] ?? '')) ?>" placeholder="Party, bill, order, notes">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= site_url('admin/accounts/general-ledger') ?>" class="btn btn-light">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-2"><small class="text-muted d-block">Rows</small><h5 class="mb-0"><?= (int) ($summary['row_count'] ?? 0) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-2"><small class="text-muted d-block">Debit</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['debit_amount'] ?? 0), 2) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-2"><small class="text-muted d-block">Credit</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['credit_amount'] ?? 0), 2) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-2"><small class="text-muted d-block">Open Balance</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['balance_amount'] ?? 0), 2) ?></h5></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="general-ledger-table" class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction Type</th>
                        <th>Reference No</th>
                        <th>Party Type</th>
                        <th>Party</th>
                        <th>Bill/Invoice</th>
                        <th>Order No</th>
                        <th>Material</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Open Balance</th>
                        <th>Status</th>
                        <th>Payment Mode</th>
                        <th>Details</th>
                        <th>File</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="16" class="text-center text-muted">No ledger entries found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['transaction_date'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['transaction_type'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['reference_no'] ?? '-')) ?></td>
                        <td><?= esc(ucfirst((string) ($row['party_type'] ?? '-'))) ?></td>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= esc((string) (($row['bill_no'] ?? '') !== '' ? $row['bill_no'] : '-')) ?></td>
                        <td><?= esc((string) (($row['order_no'] ?? '') !== '' ? $row['order_no'] : '-')) ?></td>
                        <td><?= esc((string) (($row['material_type'] ?? '') !== '' ? $row['material_type'] : '-')) ?></td>
                        <td>Rs <?= number_format((float) ($row['debit_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['credit_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['balance_amount'] ?? 0), 2) ?></td>
                        <td><?= esc((string) (($row['status'] ?? '') !== '' ? $row['status'] : '-')) ?></td>
                        <td><?= esc((string) (($row['payment_mode'] ?? '') !== '' ? $row['payment_mode'] : '-')) ?></td>
                        <td><?= esc((string) ($row['details'] ?? '')) ?></td>
                        <td>
                            <?php if (! empty($row['file_path'])): ?>
                                <a href="<?= base_url((string) $row['file_path']) ?>" target="_blank">Open</a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
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
            link.download = button.getAttribute('data-export-name') || 'general-ledger.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    })();
</script>
<?= $this->endSection() ?>
