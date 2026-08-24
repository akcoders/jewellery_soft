<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Payments</h4>
</div>

<?php if (! $tableEnabled): ?>
    <div class="alert alert-warning">Payment table not available. Run migration to enable this module.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">New Payment</h5></div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/accounts/payments') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Pay To</label>
                    <select name="party_type" id="payment-party-type" class="form-select" required>
                        <option value="karigar">Karigar</option>
                        <option value="vendor">Vendor</option>
                    </select>
                </div>
                <div class="col-md-5 payment-party payment-party-karigar">
                    <label class="form-label">Karigar</label>
                    <select name="karigar_id" id="payment-karigar-id" class="form-select">
                        <option value="">Select Karigar</option>
                        <?php foreach (($karigars ?? []) as $karigar): ?>
                            <option value="<?= (int) $karigar['id'] ?>" data-balance="<?= esc((string) number_format((float) ($karigar['balance_amount'] ?? 0), 2, '.', '')) ?>">
                                <?= esc((string) $karigar['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 payment-party payment-party-vendor d-none">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" id="payment-vendor-id" class="form-select">
                        <option value="">Select Vendor</option>
                        <?php foreach (($vendors ?? []) as $vendor): ?>
                            <option value="<?= (int) $vendor['id'] ?>" data-balance="<?= esc((string) number_format((float) ($vendor['balance_amount'] ?? 0), 2, '.', '')) ?>">
                                <?= esc((string) $vendor['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Balance</label>
                    <input type="text" id="payment-balance" class="form-control" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select name="payment_mode" class="form-select">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="reference_no" class="form-control" maxlength="80" placeholder="UTR/Cheque/Txn Ref">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference File</label>
                    <input type="file" name="reference_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bill Being Paid <span class="text-muted">(optional)</span></label>
                    <select name="bill_ref" id="payment-bill-ref" class="form-select">
                        <option value="">No specific bill</option>
                        <?php foreach (($labourBills ?? []) as $bill): ?>
                            <option
                                value="labour:<?= (int) $bill['id'] ?>"
                                data-party-type="karigar"
                                data-party-id="<?= (int) ($bill['karigar_id'] ?? 0) ?>"
                                data-pending="<?= esc((string) number_format((float) ($bill['pending_amount'] ?? 0), 2, '.', '')) ?>">
                                <?= esc((string) ($bill['bill_no'] ?? '-')) ?> - <?= esc((string) ($bill['karigar_name'] ?? '-')) ?> - Rs <?= number_format((float) ($bill['pending_amount'] ?? 0), 2) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php foreach (($purchaseBills ?? []) as $bill): ?>
                            <?php if ((int) ($bill['vendor_id'] ?? 0) <= 0) { continue; } ?>
                            <option
                                value="purchase:<?= esc((string) $bill['source_type']) ?>:<?= (int) $bill['source_id'] ?>"
                                data-party-type="vendor"
                                data-party-id="<?= (int) ($bill['vendor_id'] ?? 0) ?>"
                                data-pending="<?= esc((string) number_format((float) ($bill['pending_amount'] ?? 0), 2, '.', '')) ?>">
                                <?= esc((string) ($bill['category'] ?? 'Purchase')) ?> #<?= (int) $bill['source_id'] ?> - <?= esc((string) ($bill['supplier_name'] ?? '-')) ?> - Rs <?= number_format((float) ($bill['pending_amount'] ?? 0), 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" <?= ! $tableEnabled ? 'disabled' : '' ?>>Save Payment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Payment History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Payment No</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Reference</th>
                        <th>Bill</th>
                        <th>File</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?><tr><td colspan="9" class="text-center text-muted">No payments found.</td></tr><?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <?php
                        $party = (string) ($row['party_type'] ?? '') === 'vendor'
                            ? (string) ($row['vendor_name'] ?? '-')
                            : (string) ($row['karigar_name'] ?? '-');
                        $billLabel = '-';
                        if ((string) ($row['bill_type'] ?? '') === 'labour') {
                            $billLabel = (string) (($row['bill_no'] ?? '') ?: ('Labour #' . (int) ($row['labour_bill_id'] ?? 0)));
                        } elseif ((string) ($row['bill_type'] ?? '') === 'purchase') {
                            $billLabel = ucfirst((string) ($row['bill_source_type'] ?? 'purchase')) . ' #' . (int) ($row['bill_source_id'] ?? 0);
                        }
                    ?>
                    <tr>
                        <td><?= esc((string) ($row['payment_no'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row['payment_date'] ?? '-')) ?></td>
                        <td><span class="badge bg-secondary me-1"><?= esc(ucfirst((string) ($row['party_type'] ?? ''))) ?></span><?= esc($party) ?></td>
                        <td><?= ($row['amount_available'] ?? true) === false ? '<span class="text-muted">Not supplied</span>' : 'Rs ' . number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td><?= esc((string) (($row['payment_mode'] ?? '') ?: '-')) ?></td>
                        <td><?= esc((string) (($row['reference_no'] ?? '') ?: '-')) ?></td>
                        <td><?= esc($billLabel) ?></td>
                        <td>
                            <?php if (! empty($row['reference_file_path'])): ?>
                                <a href="<?= base_url((string) $row['reference_file_path']) ?>" target="_blank">Open</a>
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
        const partyType = document.getElementById('payment-party-type');
        const karigar = document.getElementById('payment-karigar-id');
        const vendor = document.getElementById('payment-vendor-id');
        const balance = document.getElementById('payment-balance');
        const billRef = document.getElementById('payment-bill-ref');

        function activeSelect() {
            return partyType && partyType.value === 'vendor' ? vendor : karigar;
        }

        function updatePartyVisibility() {
            const type = partyType ? partyType.value : 'karigar';
            document.querySelectorAll('.payment-party').forEach((node) => node.classList.add('d-none'));
            document.querySelectorAll('.payment-party-' + type).forEach((node) => node.classList.remove('d-none'));
            updateBalance();
            filterBills();
        }

        function updateBalance() {
            const select = activeSelect();
            const selected = select && select.options[select.selectedIndex];
            if (balance) balance.value = selected ? (selected.getAttribute('data-balance') || '0.00') : '0.00';
        }

        function filterBills() {
            if (!billRef || !partyType) return;
            const type = partyType.value;
            const select = activeSelect();
            const partyId = select ? select.value : '';
            Array.from(billRef.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                option.hidden = option.getAttribute('data-party-type') !== type || option.getAttribute('data-party-id') !== partyId;
            });
            if (billRef.selectedOptions.length && billRef.selectedOptions[0].hidden) {
                billRef.value = '';
            }
        }

        if (partyType) partyType.addEventListener('change', updatePartyVisibility);
        if (karigar) karigar.addEventListener('change', function () { updateBalance(); filterBills(); });
        if (vendor) vendor.addEventListener('change', function () { updateBalance(); filterBills(); });
        if (billRef) billRef.addEventListener('change', function () {
            const selected = billRef.options[billRef.selectedIndex];
            if (selected && selected.value && balance) {
                balance.value = selected.getAttribute('data-pending') || balance.value;
            } else {
                updateBalance();
            }
        });
        updatePartyVisibility();
    })();
</script>
<?= $this->endSection() ?>
