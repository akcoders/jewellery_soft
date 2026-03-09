<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Labour Bills</h4>
</div>

<?php if (! $labourTableEnabled): ?>
    <div class="alert alert-warning">Labour bill tables not available. Run migration to enable labour billing.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Bill Date</th>
                        <th>Order Ref</th>
                        <th>Karigar</th>
                        <th>Gold (gm)</th>
                        <th>Rate/gm</th>
                        <th>Labour Amount</th>
                        <th>Other Bill</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Pending</th>
                        <th>Due Date</th>
                        <th>Days Left</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="15" class="text-center text-muted">No labour bills found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $status = (string) ($row['payment_status'] ?? 'Pending');
                            $statusClass = 'bg-warning text-dark';
                            if ($status === 'Paid') {
                                $statusClass = 'bg-success';
                            } elseif ($status === 'Partial') {
                                $statusClass = 'bg-info text-dark';
                            }
                        ?>
                        <tr>
                            <td><?= esc((string) ($row['bill_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['bill_date'] ?: '-')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?: '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?: '-')) ?></td>
                            <td><?= number_format((float) ($row['gold_weight_gm'] ?? 0), 3) ?></td>
                            <td>₹ <?= number_format((float) ($row['rate_per_gm'] ?? 0), 2) ?></td>
                            <td>₹ <?= number_format((float) ($row['labour_amount'] ?? 0), 2) ?></td>
                            <td>₹ <?= number_format((float) ($row['other_amount'] ?? 0), 2) ?></td>
                            <td>₹ <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            <td>₹ <?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?></td>
                            <td>₹ <?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?></td>
                            <td><?= esc((string) (($row['due_date'] ?? '') !== '' ? $row['due_date'] : '-')) ?></td>
                            <td><?= esc((string) ($row['days_left'] ?? '-')) ?></td>
                            <td><span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success js-labour-payment-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#labourPaymentModal"
                                    data-labour-bill-id="<?= esc((string) ($row['id'] ?? 0)) ?>"
                                    data-bill-no="<?= esc((string) ($row['bill_no'] ?? '-')) ?>"
                                    data-karigar="<?= esc((string) ($row['karigar_name'] ?? '-')) ?>"
                                    data-total="<?= esc((string) number_format((float) ($row['total_amount'] ?? 0), 2, '.', '')) ?>"
                                    data-paid="<?= esc((string) number_format((float) ($row['paid_amount'] ?? 0), 2, '.', '')) ?>"
                                    <?= ((float) ($row['pending_amount'] ?? 0) <= 0 || ! $labourTableEnabled) ? 'disabled' : '' ?>
                                    title="Update Payment">
                                    <i class="fe fe-credit-card"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="labourPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Labour Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= site_url('admin/accounts/labour-bills/payment') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="labour_bill_id" id="lb-bill-id">
                <div class="modal-body">
                    <div class="mb-2 small text-muted">Bill No: <strong id="lb-bill-no">-</strong></div>
                    <div class="mb-3 small text-muted">Karigar: <strong id="lb-karigar">-</strong></div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Bill Amount</label>
                            <input type="text" class="form-control" id="lb-total-amount" readonly>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Pending Amount</label>
                            <input type="text" class="form-control" id="lb-pending-amount" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="lb-payment-amount" class="form-control" required>
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
            const btn = event.target instanceof Element ? event.target.closest('.js-labour-payment-btn') : null;
            if (!btn) return;

            const billId = btn.getAttribute('data-labour-bill-id') || '';
            const billNo = btn.getAttribute('data-bill-no') || '-';
            const karigar = btn.getAttribute('data-karigar') || '-';
            const total = Number(btn.getAttribute('data-total') || 0);
            const paid = Number(btn.getAttribute('data-paid') || 0);
            const pending = Math.max(0, total - paid);

            const billIdEl = document.getElementById('lb-bill-id');
            const billNoEl = document.getElementById('lb-bill-no');
            const karigarEl = document.getElementById('lb-karigar');
            const totalEl = document.getElementById('lb-total-amount');
            const pendingEl = document.getElementById('lb-pending-amount');
            const paymentEl = document.getElementById('lb-payment-amount');

            if (billIdEl) billIdEl.value = billId;
            if (billNoEl) billNoEl.textContent = billNo;
            if (karigarEl) karigarEl.textContent = karigar;
            if (totalEl) totalEl.value = total.toFixed(2);
            if (pendingEl) pendingEl.value = pending.toFixed(2);
            if (paymentEl) {
                paymentEl.max = pending.toFixed(2);
                paymentEl.value = pending.toFixed(2);
            }
        });
    })();
</script>
<?= $this->endSection() ?>

