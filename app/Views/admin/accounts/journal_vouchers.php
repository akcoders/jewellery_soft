<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $partyLists = [
        'customer' => ['label' => 'Customer', 'rows' => $customers ?? []],
        'vendor' => ['label' => 'Vendor', 'rows' => $vendors ?? []],
        'karigar' => ['label' => 'Karigar', 'rows' => $karigars ?? []],
    ];
    $oldVoucherType = old('voucher_type', 'party_to_party');
    $oldFromType = old('from_party_type', 'vendor');
    $oldToType = old('to_party_type', 'karigar');
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0">Journal Vouchers</h4>
        <small class="text-muted">Record party-to-party adjustments and expenditure entries in the accounts ledger.</small>
    </div>
    <a href="<?= site_url('admin/accounts') ?>" class="btn btn-light">Back to Dashboard</a>
</div>

<?php if (! $tableEnabled): ?>
    <div class="alert alert-warning">Journal voucher table not available. Run migration to enable this module.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">New Journal Voucher</h5></div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/accounts/journal-vouchers') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Voucher Type</label>
                    <select name="voucher_type" id="journal-voucher-type" class="form-select" required>
                        <option value="party_to_party" <?= $oldVoucherType === 'party_to_party' ? 'selected' : '' ?>>Party-to-Party</option>
                        <option value="expenditure" <?= $oldVoucherType === 'expenditure' ? 'selected' : '' ?>>Expenditure</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Voucher Date</label>
                    <input type="date" name="voucher_date" class="form-control" value="<?= esc((string) old('voucher_date', date('Y-m-d'))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= esc((string) old('amount', '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <?php $oldStatus = old('status', 'Posted'); ?>
                    <select name="status" class="form-select">
                        <option value="Posted" <?= $oldStatus === 'Posted' ? 'selected' : '' ?>>Posted</option>
                        <option value="Draft" <?= $oldStatus === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>
            </div>

            <div id="party-to-party-fields" class="row g-3 mt-1">
                <div class="col-md-2">
                    <label class="form-label">From Type</label>
                    <select name="from_party_type" id="journal-from-type" class="form-select journal-party-type">
                        <?php foreach ($partyLists as $value => $meta): ?>
                            <option value="<?= esc($value) ?>" <?= $oldFromType === $value ? 'selected' : '' ?>><?= esc((string) $meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">From Party</label>
                    <?php foreach ($partyLists as $value => $meta): ?>
                        <select name="from_party_id" class="form-select journal-party-select journal-from-select <?= $oldFromType === $value ? '' : 'd-none' ?>" data-side="from" data-type="<?= esc($value) ?>" <?= $oldFromType === $value ? '' : 'disabled' ?>>
                            <option value="">Select <?= esc((string) $meta['label']) ?></option>
                            <?php foreach (($meta['rows'] ?? []) as $party): ?>
                                <option value="<?= (int) ($party['id'] ?? 0) ?>" <?= (string) old('from_party_id', '') === (string) ($party['id'] ?? '') && $oldFromType === $value ? 'selected' : '' ?>><?= esc((string) ($party['name'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Type</label>
                    <select name="to_party_type" id="journal-to-type" class="form-select journal-party-type">
                        <?php foreach ($partyLists as $value => $meta): ?>
                            <option value="<?= esc($value) ?>" <?= $oldToType === $value ? 'selected' : '' ?>><?= esc((string) $meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Party</label>
                    <?php foreach ($partyLists as $value => $meta): ?>
                        <select name="to_party_id" class="form-select journal-party-select journal-to-select <?= $oldToType === $value ? '' : 'd-none' ?>" data-side="to" data-type="<?= esc($value) ?>" <?= $oldToType === $value ? '' : 'disabled' ?>>
                            <option value="">Select <?= esc((string) $meta['label']) ?></option>
                            <?php foreach (($meta['rows'] ?? []) as $party): ?>
                                <option value="<?= (int) ($party['id'] ?? 0) ?>" <?= (string) old('to_party_id', '') === (string) ($party['id'] ?? '') && $oldToType === $value ? 'selected' : '' ?>><?= esc((string) ($party['name'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="expenditure-fields" class="row g-3 mt-1 d-none">
                <div class="col-md-6">
                    <label class="form-label">Expense Head</label>
                    <input type="text" name="expense_head" id="journal-expense-head" class="form-control" value="<?= esc((string) old('expense_head', '')) ?>" maxlength="120" placeholder="Rent, salary, electricity, courier, repair">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expense Note</label>
                    <input type="text" class="form-control" value="This posts into the expense ledger and dashboard expenditure." readonly>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <?php $oldMode = old('payment_mode', ''); ?>
                    <select name="payment_mode" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['Cash', 'Bank Transfer', 'UPI', 'Cheque', 'Other'] as $mode): ?>
                            <option value="<?= esc($mode) ?>" <?= $oldMode === $mode ? 'selected' : '' ?>><?= esc($mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="reference_no" class="form-control" value="<?= esc((string) old('reference_no', '')) ?>" maxlength="80" placeholder="Voucher/UTR/Cheque Ref">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= esc((string) old('notes', '')) ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" <?= ! $tableEnabled ? 'disabled' : '' ?>>Save Voucher</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Voucher History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To / Expense</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="10" class="text-center text-muted">No journal vouchers found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <?php
                        $isExpense = (string) ($row['voucher_type'] ?? '') === 'expenditure';
                        $fromLabel = $isExpense ? '-' : ucfirst((string) ($row['from_party_type'] ?? '')) . ' - ' . (string) ($row['from_party_name'] ?? '-');
                        $toLabel = $isExpense
                            ? (string) (($row['expense_head'] ?? '') ?: 'Expenditure')
                            : ucfirst((string) ($row['to_party_type'] ?? '')) . ' - ' . (string) ($row['to_party_name'] ?? '-');
                    ?>
                    <tr>
                        <td><?= esc((string) ($row['voucher_no'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['voucher_date'] ?? '-')) ?></td>
                        <td><?= $isExpense ? 'Expenditure' : 'Party to Party' ?></td>
                        <td><?= esc($fromLabel) ?></td>
                        <td><?= esc($toLabel) ?></td>
                        <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td><?= esc((string) (($row['payment_mode'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['reference_no'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['status'] ?? '') ?: '-')) ?></td>
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
        const voucherType = document.getElementById('journal-voucher-type');
        const transferFields = document.getElementById('party-to-party-fields');
        const expenseFields = document.getElementById('expenditure-fields');
        const expenseHead = document.getElementById('journal-expense-head');
        const fromType = document.getElementById('journal-from-type');
        const toType = document.getElementById('journal-to-type');

        function updatePartySelect(side, type) {
            document.querySelectorAll('.journal-' + side + '-select').forEach((select) => {
                const active = select.getAttribute('data-type') === type && voucherType.value === 'party_to_party';
                select.classList.toggle('d-none', !active);
                select.disabled = !active;
                select.required = active;
            });
        }

        function updateVoucherMode() {
            const isTransfer = voucherType.value === 'party_to_party';
            transferFields.classList.toggle('d-none', !isTransfer);
            expenseFields.classList.toggle('d-none', isTransfer);
            if (fromType) fromType.disabled = !isTransfer;
            if (toType) toType.disabled = !isTransfer;
            if (expenseHead) expenseHead.required = !isTransfer;
            updatePartySelect('from', fromType ? fromType.value : 'vendor');
            updatePartySelect('to', toType ? toType.value : 'karigar');
        }

        if (voucherType) voucherType.addEventListener('change', updateVoucherMode);
        if (fromType) fromType.addEventListener('change', function () { updatePartySelect('from', fromType.value); });
        if (toType) toType.addEventListener('change', function () { updatePartySelect('to', toType.value); });
        updateVoucherMode();
    })();
</script>
<?= $this->endSection() ?>
