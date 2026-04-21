<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $oldParty = old('party_type', 'customer'); ?>
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Create Credit Note</h5></div>
    <div class="card-body">
        <?php if (! $tableEnabled): ?>
            <div class="alert alert-warning mb-3">Credit note table not available. Run migration first.</div>
        <?php endif; ?>
        <form method="post" action="<?= site_url('admin/accounts/credit-notes') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="note_date" class="form-control" value="<?= esc(old('note_date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Party Type</label>
                <select name="party_type" class="form-select js-party-type" data-target-prefix="credit" required>
                    <option value="customer" <?= $oldParty === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor" <?= $oldParty === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                </select>
            </div>
            <div class="col-md-4 js-party-group" data-prefix="credit" data-party="customer">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= (int) $customer['id'] ?>" <?= (int) old('customer_id') === (int) $customer['id'] ? 'selected' : '' ?>><?= esc((string) $customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 js-party-group" data-prefix="credit" data-party="vendor">
                <label class="form-label">Vendor</label>
                <select name="vendor_id" class="form-select">
                    <option value="">Select vendor</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= (int) $vendor['id'] ?>" <?= (int) old('vendor_id') === (int) $vendor['id'] ? 'selected' : '' ?>><?= esc((string) $vendor['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select name="order_id" class="form-select">
                    <option value="">Optional</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= (int) $order['id'] ?>" <?= (int) old('order_id') === (int) $order['id'] ? 'selected' : '' ?>><?= esc((string) $order['order_no']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Invoice</label>
                <select name="invoice_id" class="form-select">
                    <option value="">Optional</option>
                    <?php foreach ($invoices as $invoice): ?>
                        <option value="<?= (int) $invoice['id'] ?>" <?= (int) old('invoice_id') === (int) $invoice['id'] ? 'selected' : '' ?>><?= esc((string) $invoice['invoice_no']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Reason</label>
                <input type="text" name="reason" class="form-control" value="<?= esc(old('reason')) ?>" placeholder="Discount, return, cancellation, reversal" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Reference No</label>
                <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no')) ?>" placeholder="Ref No">
            </div>
            <div class="col-md-2">
                <label class="form-label">Taxable Amount</label>
                <input type="number" step="0.01" min="0" name="taxable_amount" class="form-control js-note-taxable" data-prefix="credit" value="<?= esc(old('taxable_amount', '0.00')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">GST %</label>
                <input type="number" step="0.01" min="0" name="gst_percent" class="form-control js-note-gst-percent" data-prefix="credit" value="<?= esc(old('gst_percent', '3.00')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">GST Amount</label>
                <input type="number" step="0.01" min="0" name="gst_amount" class="form-control js-note-gst-amount" data-prefix="credit" value="<?= esc(old('gst_amount', '0.00')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Total Amount</label>
                <input type="number" step="0.01" min="0" name="total_amount" class="form-control js-note-total" data-prefix="credit" value="<?= esc(old('total_amount', '0.00')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Posted" <?= old('status', 'Posted') === 'Posted' ? 'selected' : '' ?>>Posted</option>
                    <option value="Draft" <?= old('status') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional internal note"><?= esc(old('notes')) ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary" <?= ! $tableEnabled ? 'disabled' : '' ?>>Save Credit Note</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Credit Notes Register</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Note No</th>
                        <th>Date</th>
                        <th>Party Type</th>
                        <th>Party</th>
                        <th>Order</th>
                        <th>Invoice</th>
                        <th>Reason</th>
                        <th>Taxable</th>
                        <th>GST</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No credit notes found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['note_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['note_date'] ?? '-')) ?></td>
                            <td><span class="badge bg-primary"><?= esc(ucfirst((string) ($row['party_type'] ?? '-'))) ?></span></td>
                            <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['invoice_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['reason'] ?? '-')) ?></td>
                            <td>Rs <?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            <td><span class="badge <?= (($row['status'] ?? '') === 'Draft') ? 'bg-warning text-dark' : 'bg-success' ?>"><?= esc((string) ($row['status'] ?? '-')) ?></span></td>
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
        function updatePartyGroups(prefix, partyType) {
            document.querySelectorAll('.js-party-group[data-prefix="' + prefix + '"]').forEach(function (element) {
                element.style.display = element.getAttribute('data-party') === partyType ? '' : 'none';
            });
        }

        function recalc(prefix) {
            const taxable = Number((document.querySelector('.js-note-taxable[data-prefix="' + prefix + '"]') || {}).value || 0);
            const gstPercent = Number((document.querySelector('.js-note-gst-percent[data-prefix="' + prefix + '"]') || {}).value || 0);
            const gstAmountEl = document.querySelector('.js-note-gst-amount[data-prefix="' + prefix + '"]');
            const totalEl = document.querySelector('.js-note-total[data-prefix="' + prefix + '"]');
            const gstAmount = +(taxable * gstPercent / 100).toFixed(2);
            if (gstAmountEl) gstAmountEl.value = gstAmount.toFixed(2);
            if (totalEl) totalEl.value = (taxable + gstAmount).toFixed(2);
        }

        document.querySelectorAll('.js-party-type').forEach(function (element) {
            const prefix = element.getAttribute('data-target-prefix') || '';
            updatePartyGroups(prefix, element.value);
            element.addEventListener('change', function () {
                updatePartyGroups(prefix, element.value);
            });
        });

        document.querySelectorAll('.js-note-taxable[data-prefix="credit"], .js-note-gst-percent[data-prefix="credit"]').forEach(function (element) {
            element.addEventListener('input', function () { recalc('credit'); });
        });
        recalc('credit');
    })();
</script>
<?= $this->endSection() ?>
