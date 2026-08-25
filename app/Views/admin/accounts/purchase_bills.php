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
<?= $this->section('styles') ?>
<style>
    .purchase-register-card { overflow: hidden; }
    .purchase-bills-table { min-width: 1120px; }
    .purchase-bills-table tbody td { padding: 15px 14px; vertical-align: middle; }
    .purchase-bills-table tbody tr { border-left: 3px solid transparent; }
    .purchase-bills-table tbody tr:hover { border-left-color: var(--erp-gold); }
    .purchase-supplier { color: #202939; font-size: 12px; font-weight: 800; line-height: 1.35; }
    .purchase-invoice-meta { align-items: center; color: #667085; display: flex; flex-wrap: wrap; font-size: 10px; gap: 6px; margin-top: 7px; }
    .purchase-invoice-meta span + span::before { color: #c0c7d2; content: '\2022'; margin-right: 6px; }
    .purchase-address { color: #98a2b3; display: -webkit-box; font-size: 9px; line-height: 1.35; margin-top: 4px; max-width: 330px; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .purchase-material { display: grid; gap: 7px; }
    .purchase-material-line { color: #475467; font-size: 10px; }
    .purchase-material-line strong { color: #202939; font-size: 12px; margin-right: 3px; }
    .purchase-tax-stack { color: #667085; display: grid; font-size: 10px; gap: 3px; min-width: 150px; }
    .purchase-tax-stack strong { color: #344054; font-weight: 750; }
    .purchase-total { color: #18202f; font-size: 13px; font-weight: 850; white-space: nowrap; }
    .purchase-payment { min-width: 190px; }
    .purchase-payment-values { color: #667085; display: grid; font-size: 10px; gap: 3px; margin-top: 8px; }
    .purchase-payment-values strong { color: #344054; }
    .purchase-progress { background: #eef1f5; border-radius: 99px; height: 5px; margin-top: 8px; overflow: hidden; }
    .purchase-progress span { background: linear-gradient(90deg, #278b50, #48ad70); display: block; height: 100%; }
    .purchase-due { align-items: center; color: #667085; display: flex; font-size: 9px; gap: 5px; margin-top: 7px; }
    .purchase-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; min-width: 140px; }
    .purchase-actions .btn { white-space: nowrap; }
    @media (max-width: 767px) {
        .purchase-bills-table { min-width: 0; }
        .purchase-bills-table tbody td:first-child { display: block !important; text-align: left !important; }
        .purchase-bills-table tbody td:first-child::before { display: block; margin-bottom: 9px; width: 100%; }
        .purchase-bills-table tbody td:first-child > .erp-mobile-value { max-width: 100%; text-align: left; }
        .purchase-address { margin-left: 0; max-width: none; }
        .purchase-invoice-meta { justify-content: flex-start; }
        .purchase-material { justify-items: end; }
        .purchase-actions { justify-content: flex-end; min-width: 0; }
        .purchase-payment { min-width: 0; }
    }
</style>
<?= $this->endSection() ?>

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

<div class="card erp-table-card purchase-register-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover align-middle mb-0 purchase-bills-table" data-dt-page-length="10">
                <thead>
                    <tr>
                        <th>Supplier &amp; Invoice</th>
                        <th>Material</th>
                        <th>Tax &amp; GST</th>
                        <th>Bill Value</th>
                        <th>Payment Position</th>
                        <th class="text-end">Documents &amp; Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No purchase bills found.</td></tr>
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
                            $amount = (float) ($row['amount'] ?? 0);
                            $paidAmount = (float) ($row['paid_amount'] ?? 0);
                            $pendingAmount = (float) ($row['pending_amount'] ?? 0);
                            $paidPercent = $amount > 0 ? min(100, max(0, ($paidAmount / $amount) * 100)) : 0;
                        ?>
                        <tr>
                            <td data-order="<?= esc((string) ($row['supplier_name'] ?? ''), 'attr') ?>">
                                <div class="purchase-supplier"><?= esc((string) ($row['supplier_name'] ?? '-')) ?></div>
                                <?php if (! empty($row['vendor_gstin'])): ?>
                                    <div class="small text-muted mt-1">GSTIN: <?= esc((string) $row['vendor_gstin']) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($row['vendor_address'])): ?>
                                    <div class="purchase-address" title="<?= esc((string) $row['vendor_address'], 'attr') ?>"><?= esc((string) $row['vendor_address']) ?></div>
                                <?php endif; ?>
                                <div class="purchase-invoice-meta">
                                    <span><i class="fe fe-calendar me-1"></i><?= esc((string) ($row['purchase_date'] ?: '-')) ?></span>
                                    <span><i class="fe fe-file-text me-1"></i><?= esc((string) (($row['invoice_no'] ?? '') ?: 'No invoice no.')) ?></span>
                                </div>
                            </td>
                            <td data-order="<?= esc($category, 'attr') ?>">
                                <div class="purchase-material">
                                    <span class="badge <?= esc($badgeClass) ?>"><?= esc($category) ?></span>
                                    <div class="purchase-material-line"><strong><?= number_format((float) ($row['weight_value'] ?? 0), 3) ?></strong><?= esc((string) ($row['weight_unit'] ?? '')) ?></div>
                                    <div class="purchase-material-line">Quantity: <strong><?= number_format((float) ($row['qty'] ?? 0), 3) ?></strong></div>
                                </div>
                            </td>
                            <td data-order="<?= esc((string) ($row['taxable_amount'] ?? 0), 'attr') ?>">
                                <?php if (($row['amount_available'] ?? true) === false): ?>
                                    <span class="text-muted small">Tax details not supplied</span>
                                <?php else: ?>
                                    <div class="purchase-tax-stack">
                                        <span>Taxable <strong>₹<?= number_format((float) ($row['taxable_amount'] ?? 0), 2) ?></strong></span>
                                        <?php if ((float) ($row['cgst_amount'] ?? 0) !== 0.0): ?><span>CGST <strong>₹<?= number_format((float) $row['cgst_amount'], 2) ?></strong></span><?php endif; ?>
                                        <?php if ((float) ($row['sgst_amount'] ?? 0) !== 0.0): ?><span>SGST <strong>₹<?= number_format((float) $row['sgst_amount'], 2) ?></strong></span><?php endif; ?>
                                        <?php if ((float) ($row['igst_amount'] ?? 0) !== 0.0): ?><span>IGST <strong>₹<?= number_format((float) $row['igst_amount'], 2) ?></strong></span><?php endif; ?>
                                        <?php if ((float) ($row['round_off_amount'] ?? 0) !== 0.0): ?><span>Round off <strong>₹<?= number_format((float) $row['round_off_amount'], 2) ?></strong></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-order="<?= esc((string) $amount, 'attr') ?>">
                                <?php if (($row['amount_available'] ?? true) === false): ?>
                                    <span class="text-muted">Not supplied</span>
                                <?php else: ?>
                                    <div class="purchase-total">₹<?= number_format($amount, 2) ?></div>
                                    <div class="small text-muted mt-1">Invoice total</div>
                                <?php endif; ?>
                            </td>
                            <td data-order="<?= esc((string) $pendingAmount, 'attr') ?>">
                                <div class="purchase-payment">
                                    <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                                <?php if (($row['amount_available'] ?? true) === false): ?>
                                    <div class="small text-muted mt-1"><?= esc((string) ($row['reconciliation_status'] ?? 'Amount not supplied in source')) ?></div>
                                <?php else: ?>
                                    <div class="purchase-progress"><span style="width:<?= esc(number_format($paidPercent, 2, '.', ''), 'attr') ?>%"></span></div>
                                    <div class="purchase-payment-values">
                                        <span>Paid <strong>₹<?= number_format($paidAmount, 2) ?></strong></span>
                                        <span>Pending <strong>₹<?= number_format($pendingAmount, 2) ?></strong></span>
                                    </div>
                                    <div class="purchase-due"><i class="fe fe-clock"></i><span>Due: <?= esc((string) (($row['due_date'] ?? '') !== '' ? $row['due_date'] : 'Not set')) ?><?= ($row['days_left'] ?? '-') !== '-' ? ' · ' . esc((string) $row['days_left']) : '' ?></span></div>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="purchase-actions">
                                    <?php if ($attachment !== null && (($attachment['file_path'] ?? '') !== '' || ($attachment['url'] ?? '') !== '')): ?>
                                        <?php $attachmentUrl = ($attachment['url'] ?? '') !== '' ? (string) $attachment['url'] : base_url((string) $attachment['file_path']); ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= esc($attachmentUrl) ?>" target="_blank" rel="noopener">
                                            <i class="fe fe-paperclip me-1"></i>Bill<?= (int) ($attachment['count'] ?? 0) > 1 ? ' +' . ((int) $attachment['count'] - 1) : '' ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (! empty($row['view_url'])): ?>
                                        <a href="<?= esc((string) $row['view_url']) ?>" class="btn btn-sm btn-outline-secondary" title="View Bill"><i class="fe fe-eye me-1"></i>View</a>
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
                                        <i class="fe fe-credit-card me-1"></i>Pay
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
