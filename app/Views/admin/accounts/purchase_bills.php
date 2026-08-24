<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$billCount = count($rows ?? []);
$totalValue = 0.0;
$totalPaid = 0.0;
$totalPending = 0.0;
foreach (($rows ?? []) as $summaryRow) {
    if (($summaryRow['amount_available'] ?? true) === false) {
        continue;
    }
    $totalValue += (float) ($summaryRow['amount'] ?? 0);
    $totalPaid += (float) ($summaryRow['paid_amount'] ?? 0);
    $totalPending += (float) ($summaryRow['pending_amount'] ?? 0);
}
?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Accounts payable</span>
        <h4 class="mb-1">Purchase Bills</h4>
        <p class="mb-0">Gold, diamond and stone invoices with GST, attachments and payment position.</p>
    </div>
    <a href="<?= site_url('admin/accounts/production-purchase-register') ?>" class="btn btn-outline-primary">
        <i class="fe fe-download me-1"></i>Download Verified Excel
    </a>
</div>

<div class="erp-finance-summary mb-3">
    <div class="erp-finance-metric blue"><i class="fe fe-file-text"></i><span><small>Total Bills</small><strong><?= number_format($billCount) ?></strong></span></div>
    <div class="erp-finance-metric"><i class="fe fe-shopping-bag"></i><span><small>Invoice Value</small><strong>₹<?= number_format($totalValue, 2) ?></strong></span></div>
    <div class="erp-finance-metric success"><i class="fe fe-check-circle"></i><span><small>Paid</small><strong>₹<?= number_format($totalPaid, 2) ?></strong></span></div>
    <div class="erp-finance-metric danger"><i class="fe fe-clock"></i><span><small>Pending</small><strong>₹<?= number_format($totalPending, 2) ?></strong></span></div>
</div>

<?php if (! $paymentTableEnabled): ?>
    <div class="alert alert-warning">Purchase payment table not available. Run migration to enable payment updates.</div>
<?php endif; ?>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Purchase Date</th>
                        <th>Purchase Category</th>
                        <th>Qty</th>
                        <th>Total Weight</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Days Left</th>
                        <th>Payment Status</th>
                        <th>Bill Attachment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No purchase bills found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $category = (string) ($row['category'] ?? '-');
                            $badgeClass = 'bg-secondary';
                            if ($category === 'Gold') {
                                $badgeClass = 'bg-warning text-dark';
                            } elseif ($category === 'Diamond') {
                                $badgeClass = 'bg-primary';
                            } elseif ($category === 'Stone') {
                                $badgeClass = 'bg-info text-dark';
                            }
                            $status = (string) ($row['payment_status'] ?? 'Pending');
                            $statusClass = 'bg-warning text-dark';
                            if ($status === 'Paid') {
                                $statusClass = 'bg-success';
                            } elseif ($status === 'Partial') {
                                $statusClass = 'bg-info text-dark';
                            }
                            $attachment = is_array($row['attachment'] ?? null) ? $row['attachment'] : null;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc((string) ($row['supplier_name'] ?? '-')) ?></div>
                                <?php if (! empty($row['vendor_gstin'])): ?>
                                    <div class="small text-muted">GSTIN: <?= esc((string) $row['vendor_gstin']) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($row['vendor_address'])): ?>
                                    <div class="small text-muted" style="max-width: 260px"><?= esc((string) $row['vendor_address']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= esc((string) ($row['purchase_date'] ?: '-')) ?></div>
                                <?php if (! empty($row['invoice_no'])): ?>
                                    <div class="small text-muted">Invoice: <?= esc((string) $row['invoice_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= esc($badgeClass) ?>"><?= esc($category) ?></span></td>
                            <td><?= number_format((float) ($row['qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['weight_value'] ?? 0), 3) ?> <?= esc((string) ($row['weight_unit'] ?? '')) ?></td>
                            <td>
                                <?php if (($row['amount_available'] ?? true) === false): ?>
                                    <span class="text-muted">Not supplied</span>
                                <?php else: ?>
                                    <div class="fw-semibold">₹ <?= number_format((float) ($row['amount'] ?? 0), 2) ?></div>
                                    <?php if (array_key_exists('taxable_amount', $row)): ?>
                                        <div class="small text-muted">
                                            Taxable: ₹<?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?><br>
                                            <?php if ((float) ($row['cgst_amount'] ?? 0) !== 0.0): ?>CGST: ₹<?= number_format((float) $row['cgst_amount'], 2) ?><br><?php endif; ?>
                                            <?php if ((float) ($row['sgst_amount'] ?? 0) !== 0.0): ?>SGST: ₹<?= number_format((float) $row['sgst_amount'], 2) ?><br><?php endif; ?>
                                            <?php if ((float) ($row['igst_amount'] ?? 0) !== 0.0): ?>IGST: ₹<?= number_format((float) $row['igst_amount'], 2) ?><br><?php endif; ?>
                                            <?php if ((float) ($row['round_off_amount'] ?? 0) !== 0.0): ?>Round off: ₹<?= number_format((float) $row['round_off_amount'], 2) ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= esc((string) (($row['due_date'] ?? '') !== '' ? $row['due_date'] : '-')) ?></td>
                            <td><?= esc((string) ($row['days_left'] ?? '-')) ?></td>
                            <td>
                                <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                                <?php if (($row['amount_available'] ?? true) === false): ?>
                                    <div class="small text-muted mt-1"><?= esc((string) ($row['reconciliation_status'] ?? 'Amount not supplied in source')) ?></div>
                                <?php else: ?>
                                    <div class="small text-muted mt-1">
                                        Paid: ₹<?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?><br>
                                        Pending: ₹<?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($attachment !== null && (($attachment['file_path'] ?? '') !== '' || ($attachment['url'] ?? '') !== '')): ?>
                                    <?php $attachmentUrl = ($attachment['url'] ?? '') !== '' ? (string) $attachment['url'] : base_url((string) $attachment['file_path']); ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= esc($attachmentUrl) ?>" target="_blank">
                                        <i class="fe fe-paperclip me-1"></i>Open<?= (int) ($attachment['count'] ?? 0) > 1 ? ' (+' . ((int) $attachment['count'] - 1) . ')' : '' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if (! empty($row['view_url'])): ?>
                                        <a href="<?= esc((string) $row['view_url']) ?>" class="btn btn-sm btn-outline-info" title="View Bill"><i class="fe fe-eye"></i></a>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success js-purchase-payment-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#purchasePaymentModal"
                                        data-source-type="<?= esc((string) ($row['source_type'] ?? '')) ?>"
                                        data-source-id="<?= esc((string) ($row['source_id'] ?? 0)) ?>"
                                        data-supplier="<?= esc((string) ($row['supplier_name'] ?? '-')) ?>"
                                        data-category="<?= esc($category) ?>"
                                        data-amount="<?= esc((string) number_format((float) ($row['amount'] ?? 0), 2, '.', '')) ?>"
                                        data-paid="<?= esc((string) number_format((float) ($row['paid_amount'] ?? 0), 2, '.', '')) ?>"
                                        <?= ((float) ($row['pending_amount'] ?? 0) <= 0 || ! $paymentTableEnabled) ? 'disabled' : '' ?>
                                        title="Update Payment">
                                        <i class="fe fe-credit-card"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="purchasePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Purchase Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= site_url('admin/accounts/purchase-bills/payment') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="source_type" id="pb-source-type">
                <input type="hidden" name="source_id" id="pb-source-id">
                <div class="modal-body">
                    <div class="mb-2 small text-muted">Supplier: <strong id="pb-supplier">-</strong></div>
                    <div class="mb-3 small text-muted">Category: <strong id="pb-category">-</strong></div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Bill Amount</label>
                            <input type="text" class="form-control" id="pb-bill-amount" readonly>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Pending Amount</label>
                            <input type="text" class="form-control" id="pb-pending-amount" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="pb-payment-amount" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" maxlength="80" placeholder="UTR/Cheque/Txn Ref">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        document.addEventListener('click', function (event) {
            const btn = event.target instanceof Element ? event.target.closest('.js-purchase-payment-btn') : null;
            if (!btn) return;

            const sourceType = btn.getAttribute('data-source-type') || '';
            const sourceId = btn.getAttribute('data-source-id') || '';
            const supplier = btn.getAttribute('data-supplier') || '-';
            const category = btn.getAttribute('data-category') || '-';
            const amount = Number(btn.getAttribute('data-amount') || 0);
            const paid = Number(btn.getAttribute('data-paid') || 0);
            const pending = Math.max(0, amount - paid);

            const sourceTypeEl = document.getElementById('pb-source-type');
            const sourceIdEl = document.getElementById('pb-source-id');
            const supplierEl = document.getElementById('pb-supplier');
            const categoryEl = document.getElementById('pb-category');
            const billAmountEl = document.getElementById('pb-bill-amount');
            const pendingAmountEl = document.getElementById('pb-pending-amount');
            const paymentAmountEl = document.getElementById('pb-payment-amount');

            if (sourceTypeEl) sourceTypeEl.value = sourceType;
            if (sourceIdEl) sourceIdEl.value = sourceId;
            if (supplierEl) supplierEl.textContent = supplier;
            if (categoryEl) categoryEl.textContent = category;
            if (billAmountEl) billAmountEl.value = amount.toFixed(2);
            if (pendingAmountEl) pendingAmountEl.value = pending.toFixed(2);
            if (paymentAmountEl) {
                paymentAmountEl.max = pending.toFixed(2);
                paymentAmountEl.value = pending.toFixed(2);
            }
        });
    })();
</script>
<?= $this->endSection() ?>
