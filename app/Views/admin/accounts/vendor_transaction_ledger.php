<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Issue Receive Ledger</h4>
    <button type="button" class="btn btn-outline-success" data-export-table="#vendor-transaction-ledger-table" data-export-name="issue-receive-ledger.xls">
        <i class="fe fe-download me-1"></i> Export Excel
    </button>
</div>

<form method="get" action="<?= site_url('admin/accounts/vendor-transaction-ledger') ?>" class="card mb-3">
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
                <label class="form-label">Party Type</label>
                <select name="party_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['vendor' => 'Vendor', 'karigar' => 'Karigar'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= (string) ($filters['party_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
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
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($categories ?? []) as $category): ?>
                        <option value="<?= esc((string) $category) ?>" <?= (string) ($filters['category'] ?? '') === (string) $category ? 'selected' : '' ?>><?= esc((string) $category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Transaction</label>
                <select name="transaction_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($transactionTypes ?? []) as $type): ?>
                        <option value="<?= esc((string) $type) ?>" <?= (string) ($filters['transaction_type'] ?? '') === (string) $type ? 'selected' : '' ?>><?= esc((string) $type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Material</label>
                <select name="material_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($materialTypes ?? []) as $type): ?>
                        <option value="<?= esc((string) $type) ?>" <?= (string) ($filters['material_type'] ?? '') === (string) $type ? 'selected' : '' ?>><?= esc((string) $type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Reference No</label>
                <input type="text" name="reference_no" class="form-control" value="<?= esc((string) ($filters['reference_no'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Search All</label>
                <input type="text" name="search" class="form-control" value="<?= esc((string) ($filters['search'] ?? '')) ?>" placeholder="Party, order, reference, notes">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= site_url('admin/accounts/vendor-transaction-ledger') ?>" class="btn btn-light">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block mb-2">Gold (gm)</small><div class="d-flex justify-content-between gap-2"><span>Opening<br><strong><?= number_format((float) ($summary['opening_gold_gm'] ?? 0), 3) ?></strong></span><span>In<br><strong><?= number_format((float) ($summary['issue_gold_gm'] ?? 0), 3) ?></strong></span><span>Out<br><strong><?= number_format((float) ($summary['receive_gold_gm'] ?? 0), 3) ?></strong></span><span>Closing<br><strong><?= number_format((float) ($summary['closing_gold_gm'] ?? 0), 3) ?></strong></span></div></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block mb-2">Diamond / Stone (cts)</small><div class="d-flex justify-content-between gap-2"><span>Opening<br><strong><?= number_format((float) ($summary['opening_cts'] ?? 0), 3) ?></strong></span><span>In<br><strong><?= number_format((float) ($summary['issue_cts'] ?? 0), 3) ?></strong></span><span>Out<br><strong><?= number_format((float) ($summary['receive_cts'] ?? 0), 3) ?></strong></span><span>Closing<br><strong><?= number_format((float) ($summary['closing_cts'] ?? 0), 3) ?></strong></span></div></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block mb-2">Money (Rs) · <?= (int) ($summary['row_count'] ?? 0) ?> entries</small><div class="d-flex justify-content-between gap-2"><span>Opening<br><strong><?= number_format((float) ($summary['opening_amount'] ?? 0), 2) ?></strong></span><span>In<br><strong><?= number_format((float) ($summary['payable_amount'] ?? 0), 2) ?></strong></span><span>Out<br><strong><?= number_format((float) ($summary['paid_amount'] ?? 0), 2) ?></strong></span><span>Closing<br><strong><?= number_format((float) ($summary['closing_amount'] ?? 0), 2) ?></strong></span></div></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="vendor-transaction-ledger-table" class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="50">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Transaction</th>
                        <th>Material</th>
                        <th>Reference</th>
                        <th>Source</th>
                        <th>Order</th>
                        <th>Party Type</th>
                        <th>Party</th>
                        <th>Issue Gold gm</th>
                        <th>Receive Gold gm</th>
                        <th>Issue Pcs</th>
                        <th>Receive Pcs</th>
                        <th>Issue Cts</th>
                        <th>Receive Cts</th>
                        <th>Payable</th>
                        <th>Paid/Received</th>
                        <th>Balance</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>File</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="23" class="text-center text-muted">No transactions found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['transaction_date'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['category'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['transaction_type'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['material_type'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['reference_no'] ?? '-')) ?></td>
                        <td><?= esc((string) (($row['source_label'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['order_no'] ?? '') ?: '-')) ?></td>
                        <td><?= esc(ucfirst((string) ($row['party_type'] ?? '-'))) ?></td>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= number_format((float) ($row['issue_gold_gm'] ?? 0), 3) ?></td>
                        <td><?= number_format((float) ($row['receive_gold_gm'] ?? 0), 3) ?></td>
                        <td><?= number_format((float) ($row['issue_pcs'] ?? 0), 3) ?></td>
                        <td><?= number_format((float) ($row['receive_pcs'] ?? 0), 3) ?></td>
                        <td><?= number_format((float) ($row['issue_cts'] ?? 0), 3) ?></td>
                        <td><?= number_format((float) ($row['receive_cts'] ?? 0), 3) ?></td>
                        <td>Rs <?= number_format((float) ($row['payable_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['balance_amount'] ?? 0), 2) ?></td>
                        <td><?= esc((string) (($row['payment_mode'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['status'] ?? '') ?: '-')) ?></td>
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
            link.download = button.getAttribute('data-export-name') || 'issue-receive-ledger.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    })();
</script>
<?= $this->endSection() ?>
