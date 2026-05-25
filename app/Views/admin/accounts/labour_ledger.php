<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Labour Ledger</h4>
    <button type="button" class="btn btn-outline-success" data-export-table="#labour-ledger-table" data-export-name="labour-ledger.xls">
        <i class="fe fe-download me-1"></i> Export Excel
    </button>
</div>

<form method="get" action="<?= site_url('admin/accounts/labour-ledger') ?>" class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $karigar['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <?php $status = (string) ($filters['status'] ?? 'all'); ?>
                <select name="status" class="form-select">
                    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'partial' => 'Partial', 'paid' => 'Paid'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Entry Type</label>
                <?php $entryType = (string) ($filters['entry_type'] ?? 'all'); ?>
                <select name="entry_type" class="form-select">
                    <?php foreach (['all' => 'All', 'bill' => 'Bills', 'payment' => 'Payments'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $entryType === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-primary" type="submit">Apply</button>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Bill Amount</small><h4 class="mb-0">Rs <?= number_format((float) ($summary['bill_amount'] ?? 0), 2) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Paid Amount</small><h4 class="mb-0">Rs <?= number_format((float) ($summary['payment_amount'] ?? 0), 2) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Open Balance</small><h4 class="mb-0">Rs <?= number_format((float) ($summary['pending_amount'] ?? 0), 2) ?></h4></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="labour-ledger-table" class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Karigar</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Order</th>
                        <th>Bill Amount</th>
                        <th>Payment</th>
                        <th>Open Bill Balance</th>
                        <th>Status</th>
                        <th>Reference File</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="11" class="text-center text-muted">No ledger records found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <?php
                        $type = (string) ($row['entry_type'] ?? '');
                        $statusLabel = (string) ($row['status'] ?? '-');
                        $statusClass = $statusLabel === 'Paid' ? 'bg-success' : ($statusLabel === 'Partial' ? 'bg-info text-dark' : 'bg-warning text-dark');
                    ?>
                    <tr>
                        <td><?= esc((string) ($row['entry_date'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                        <td><span class="badge <?= $type === 'Payment' ? 'bg-success' : 'bg-secondary' ?>"><?= esc($type) ?></span></td>
                        <td><?= esc((string) ($row['reference_no'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                        <td>Rs <?= number_format((float) ($row['bill_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['payment_amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?></td>
                        <td><span class="badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
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

            const clone = table.cloneNode(true);
            clone.querySelectorAll('script').forEach((node) => node.remove());
            const html = '<html><head><meta charset="utf-8"></head><body>' + clone.outerHTML + '</body></html>';
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = button.getAttribute('data-export-name') || 'export.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    })();
</script>
<?= $this->endSection() ?>
