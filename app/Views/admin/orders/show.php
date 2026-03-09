<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .order-layout-shell {
        border-left: 3px solid #7c5cff;
        padding-left: 14px;
    }

    .order-page-title {
        font-size: 32px;
        font-weight: 700;
        color: #1d2144;
        margin-bottom: 14px;
    }

    .order-head-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .order-head-code {
        font-size: 22px;
        font-weight: 700;
        color: #1d2144;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .order-head-actions .btn {
        border-radius: 7px;
        padding: 0.35rem 0.75rem;
        font-weight: 600;
        box-shadow: none !important;
        transform: none !important;
    }

    .super-admin-list-head.order-summary {
        border-bottom: 0;
        margin-bottom: 18px;
    }

    .super-admin-list-head.order-summary .grid-info-item {
        justify-content: flex-start;
        align-items: center;
        gap: 16px;
    }

    .super-admin-list-head.order-summary .grid-info-item .grid-head-icon {
        width: 90px;
        height: 90px;
        font-size: 38px;
    }

    .super-admin-list-head.order-summary .grid-info-item span {
        font-size: 14px;
        color: #2b3150;
        font-weight: 600;
    }

    .super-admin-list-head.order-summary .grid-info-item h4 {
        font-size: 34px;
        line-height: 1.2;
        margin-top: 2px;
        margin-bottom: 0;
        color: #1d2144;
        font-weight: 700;
    }

    .order-section-title {
        font-size: 26px;
        font-weight: 700;
        color: #2a2f4a;
        margin: 0;
    }

    .order-photo-box {
        min-height: 160px;
        border-radius: 10px;
        border: 1px solid #e5e7ef;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .order-photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .order-metric-card {
        border-radius: 8px;
        padding: 12px;
    }

    .metric-gold {
        background: #d8f3df;
    }

    .metric-diamond {
        background: #d8e5f8;
    }

    .card {
        border: 1px solid #e5e7ef;
        border-radius: 10px;
        box-shadow: none;
    }

    .card-header {
        background: #fff;
        border-bottom: 1px solid #eceef5;
        padding: 12px 14px;
    }

    .table {
        border-radius: 8px;
        overflow: hidden;
    }

    .table thead th {
        background: #f7f8fc;
        font-weight: 600;
        font-size: 12px;
        color: #555b79;
        text-transform: none;
    }

    .table tbody td {
        font-size: 12px;
        color: #3f4563;
    }

    .order-dot {
        display: none;
    }

    .status-timeline {
        list-style: none;
        padding-left: 1.5rem;
        border-left: 2px solid #e5e7ef;
        margin-left: 0.5rem;
    }

    .status-timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }

    .status-timeline-item:last-child {
        padding-bottom: 0;
    }

    .status-timeline-item:before {
        content: '';
        position: absolute;
        left: -1.85rem;
        top: 0.2rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #7c5cff;
        border: 1px solid #7c5cff;
    }

    .form-control,
    .form-select {
        border-radius: 6px;
        border: 1px solid #d9ddea;
    }

    .budget-warning {
        background: #fff4e5;
        border-left: 3px solid #f59e0b;
    }

    .order-grid-card {
        margin-bottom: 16px;
    }

    .fs-24 {
        font-size: 2rem;
    }

    .bg-soft-primary {
        background: #f7f8fc;
        border: 1px solid #eceef5;
    }

    @media (max-width: 991px) {
        .order-page-title {
            font-size: 26px;
        }

        .order-head-code {
            font-size: 18px;
        }

        .super-admin-list-head.order-summary .grid-info-item h4 {
            font-size: 24px;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const receiveModal = document.getElementById('receiveModal');
        if (!receiveModal) return;

        function num(v) {
            const n = parseFloat(String(v === undefined || v === null ? '' : v));
            return Number.isFinite(n) ? n : 0;
        }

        function createRowHtml(kind) {
            if (kind === 'dia') {
                return '<tr>'
                    + '<td><input type="text" name="studded_diamond_type[]" class="form-control"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="0"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0" readonly></td>'
                    + '<td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                    + '</tr>';
            }
            if (kind === 'stone') {
                return '<tr>'
                    + '<td><input type="text" name="stone_type[]" class="form-control"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0" readonly></td>'
                    + '<td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="0.00" readonly></td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                    + '</tr>';
            }
            return '<tr>'
                + '<td><input type="text" name="other_desc[]" class="form-control"></td>'
                + '<td><input type="number" step="0.001" min="0" name="other_pcs[]" class="form-control js-other-pcs" value="0"></td>'
                + '<td><input type="number" step="0.001" min="0" name="other_weight_line_gm[]" class="form-control js-other-weight" value="0"></td>'
                + '<td><input type="number" step="0.01" min="0" name="other_price[]" class="form-control js-other-price" value="0"></td>'
                + '<td><input type="text" name="other_total[]" class="form-control js-other-total" value="0.00" readonly></td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                + '</tr>';
        }

        function recalcReceiveModal() {
            let diaCts = 0;
            receiveModal.querySelectorAll('.js-dia-weight').forEach(function (el) { diaCts += num(el.value); });
            let stoneCts = 0;
            receiveModal.querySelectorAll('.js-stone-weight').forEach(function (el) { stoneCts += num(el.value); });
            let otherGm = 0;
            receiveModal.querySelectorAll('.js-other-weight').forEach(function (el) { otherGm += num(el.value); });

            receiveModal.querySelectorAll('tr').forEach(function (row) {
                const diaW = row.querySelector('.js-dia-weight');
                const diaR = row.querySelector('.js-dia-rate');
                const diaT = row.querySelector('.js-dia-total');
                if (diaW && diaR && diaT) diaT.value = (num(diaW.value) * num(diaR.value)).toFixed(2);

                const stW = row.querySelector('.js-stone-weight');
                const stR = row.querySelector('.js-stone-rate');
                const stT = row.querySelector('.js-stone-total');
                if (stW && stR && stT) stT.value = (num(stW.value) * num(stR.value)).toFixed(2);

                const oP = row.querySelector('.js-other-price');
                const oT = row.querySelector('.js-other-total');
                if (oP && oT) oT.value = num(oP.value).toFixed(2);
            });

            const gross = num((receiveModal.querySelector('.js-gross-weight') || {}).value);
            const purityPercent = num((receiveModal.querySelector('.js-purity-percent') || {}).value);
            const diaGm = diaCts * 0.2;
            const stoneGm = stoneCts * 0.2;
            const net = gross - (diaGm + stoneGm + otherGm);
            const pure = net * (purityPercent / 100);
            const labourRate = num((receiveModal.querySelector('.js-labour-rate') || {}).value);
            const goldRate = num((receiveModal.querySelector('.js-gold-rate') || {}).value);
            const labourTotal = Math.max(net, 0) * labourRate;
            const goldTotal = Math.max(net, 0) * goldRate;

            const netEl = receiveModal.querySelector('.js-net-weight');
            const pureEl = receiveModal.querySelector('.js-pure-weight');
            const labourTotalEl = receiveModal.querySelector('.js-labour-total');
            const goldTotalEl = receiveModal.querySelector('.js-gold-total');
            if (netEl) netEl.value = net.toFixed(3);
            if (pureEl) pureEl.value = pure.toFixed(3);
            if (labourTotalEl) labourTotalEl.value = labourTotal.toFixed(2);
            if (goldTotalEl) goldTotalEl.value = goldTotal.toFixed(2);
        }

        receiveModal.addEventListener('click', function (event) {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const addDia = target.closest('.js-add-dia-row');
            if (addDia) {
                const body = receiveModal.querySelector('.js-dia-body');
                if (body) body.insertAdjacentHTML('beforeend', createRowHtml('dia'));
                recalcReceiveModal();
                return;
            }
            const addStone = target.closest('.js-add-stone-row');
            if (addStone) {
                const body = receiveModal.querySelector('.js-stone-body');
                if (body) body.insertAdjacentHTML('beforeend', createRowHtml('stone'));
                recalcReceiveModal();
                return;
            }
            const addOther = target.closest('.js-add-other-row');
            if (addOther) {
                const body = receiveModal.querySelector('.js-other-body');
                if (body) body.insertAdjacentHTML('beforeend', createRowHtml('other'));
                recalcReceiveModal();
                return;
            }
            const rm = target.closest('.js-remove-row');
            if (rm) {
                const tr = rm.closest('tr');
                const tbody = tr ? tr.parentElement : null;
                if (tbody && tr && tbody.children.length > 1) {
                    tr.remove();
                    recalcReceiveModal();
                }
            }
        });

        receiveModal.addEventListener('input', function () {
            recalcReceiveModal();
        });

        recalcReceiveModal();
    })();
</script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$orderPhoto = null;
$generalAttachments = [];
$auditAttachments = [];
foreach ($attachments as $file) {
    $fileType = strtolower((string) ($file['file_type'] ?? ''));
    if ($fileType === 'photo') {
        $orderPhoto = base_url((string) $file['file_path']);
    }
    if (str_contains($fileType, 'audit')) {
        $auditAttachments[] = $file;
    } else {
        $generalAttachments[] = $file;
    }
}
$isCancelledOrder = (string) ($order['status'] ?? '') === 'Cancelled';
$isCompletedOrder = (string) ($order['status'] ?? '') === 'Completed';
$isLockedOrder = $isCancelledOrder || $isCompletedOrder;
?>

<div class="order-layout-shell">
    <div class="order-head-row">
        <div class="order-head-code"><i class="fe fe-package"></i> Order: <?= esc($order['order_no']) ?></div>
        <div class="order-head-actions d-flex flex-wrap gap-2 mt-3">
            <?php if (! $isLockedOrder): ?>
                <a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-info"><i class="fe fe-edit me-1"></i>Edit</a>
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="fe fe-refresh-cw me-1"></i>Update Status</button>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#receiveModal"><i class="fe fe-download me-1"></i>Receive</button>
                <a href="<?= site_url('admin/diamond-inventory/issues/create?order_id=' . $order['id']) ?>" class="btn btn-outline-success"><i class="fe fe-share me-1"></i>Diamond Issue</a>
                <a href="<?= site_url('admin/diamond-inventory/returns/create?order_id=' . $order['id']) ?>" class="btn btn-outline-primary"><i class="fe fe-corner-up-left me-1"></i>Diamond Return</a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="fe fe-x-circle me-1"></i>Cancel</button>
            <?php elseif ($isCompletedOrder): ?>
                <a href="<?= site_url('admin/orders/' . $order['id'] . '/packing-list/generate?print=1&download=1') ?>" class="btn btn-outline-primary"><i class="fe fe-download me-1"></i>Download Packing List</a>
                <a href="<?= site_url('admin/orders/' . $order['id'] . '/delivery-challan?download=1') ?>" target="_blank" class="btn btn-outline-dark"><i class="fe fe-file-text me-1"></i>Delivery Challan</a>
            <?php endif; ?>
            <a href="<?= site_url((string) $order['order_type'] === 'Repair' ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <div class="super-admin-list-head order-summary mt-4 mb-2">
        <div class="row">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="grid-info-item subscription-list total-transaction">
                            <div class="grid-head-icon"><i class="fe fe-tag"></i></div>
                            <div class="grid-info">
                                <span>Order Type</span>
                                <h4><?= esc($order['order_type']) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="grid-info-item subscription-list total-subscriber">
                            <div class="grid-head-icon"><i class="fe fe-calendar"></i></div>
                            <div class="grid-info">
                                <span>Due Date</span>
                                <h4><?= esc($order['due_date'] ?: '-') ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="grid-info-item subscription-list active-subscriber">
                            <div class="grid-head-icon"><i class="fe fe-user-check"></i></div>
                            <div class="grid-info">
                                <span>Assigned Karigar</span>
                                <h4><?= esc($order['karigar_name'] ?: 'Not Assigned') ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="grid-info-item subscription-list expired-subscriber">
                            <div class="grid-head-icon"><i class="fe fe-activity"></i></div>
                            <div class="grid-info">
                                <span>Current Status</span>
                                <h4><?= esc($order['status']) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- order items card -->
    <div class="card mb-4" id="order-items">
        <div class="card-header d-flex align-items-center">
            <h5 class="order-section-title mb-0"><i class="fe fe-list me-2"></i>Order Items</h5>
        </div>
        <div class="card-body p-0 p-3">
            <div class="table-responsive">
                <table class="table datatable table-hover table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Design</th>
                            <th>Gold Purity</th>
                            <th>Description</th>
                            <th>Size</th>
                            <th>Qty</th>
                            <th>Gold (gm)</th>
                            <th>Diamond (cts)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No items added.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="fw-medium"><?= esc(($item['design_code'] ?? '') . ' ' . ($item['design_name'] ?? '')) ?></td>
                                <td><?= esc(trim(($item['purity_code'] ?? '') . ' ' . ($item['color_name'] ?? '')) ?: '-') ?></td>
                                <td><?= esc($item['item_description'] ?: '-') ?></td>
                                <td><?= esc($item['size_label'] ?: '-') ?></td>
                                <td><?= esc((string) $item['qty']) ?></td>
                                <td class="text-success fw-semibold"><?= esc(number_format((float) $item['gold_required_gm'], 3)) ?></td>
                                <td class="text-primary fw-semibold"><?= esc(number_format((float) $item['diamond_required_cts'], 3)) ?></td>
                                <td><span class="badge bg-light text-dark px-3 py-2 rounded-pill"><?= esc($item['item_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="order-followups">
        <div class="card-header d-flex align-items-center">
            <h5 class="order-section-title mb-0"><i class="fe fe-message-circle me-2"></i>Order Followups</h5>
        </div>
        <div class="card-body p-0 p-3">
            <div class="table-responsive">
                <table class="table datatable table-hover table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th>Description</th>
                            <th>Next Followup</th>
                            <th>Taken By</th>
                            <th>Taken On</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($followups ?? []) === []): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No followups taken yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($followups ?? []) as $followup): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark px-3 py-2 rounded-pill"><?= esc((string) ($followup['stage'] ?? '-')) ?></span></td>
                                <td><?= esc((string) ($followup['description'] ?? '-')) ?></td>
                                <td><?= esc((string) (!empty($followup['next_followup_date']) ? $followup['next_followup_date'] : '-')) ?></td>
                                <td><?= esc((string) (($followup['followup_taken_by_name'] ?? '') !== '' ? $followup['followup_taken_by_name'] : 'Admin')) ?></td>
                                <td><?= esc((string) (!empty($followup['followup_taken_on']) ? $followup['followup_taken_on'] : '-')) ?></td>
                                <td>
                                    <?php if (!empty($followup['image_path'])): ?>
                                        <a href="<?= base_url((string) $followup['image_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- two column: order details & photo + summary -->
    <div class="row g-4 mb-4" id="order-details">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="order-section-title mb-0"><i class="fe fe-info me-2"></i>Order Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="mb-2"><span class="order-dot bg-primary"></span><strong>Customer:</strong> <?= esc($order['customer_name'] ?: '-') ?></p>
                            <p class="mb-2"><span class="order-dot bg-success"></span><strong>Lead:</strong> <?= esc($order['lead_name'] ?: '-') ?></p>
                            <p class="mb-2"><span class="order-dot bg-info"></span><strong>Priority:</strong> <?= esc($order['priority']) ?></p>
                            <p class="mb-2"><span class="order-dot bg-warning"></span><strong>Status:</strong> <?= esc($order['status']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Order No:</strong> <?= esc($order['order_no']) ?></p>
                            <p class="mb-2"><strong>Due Date:</strong> <?= esc($order['due_date'] ?: '-') ?></p>
                            <p class="mb-2"><strong>Karigar:</strong> <?= esc($order['karigar_name'] ?: 'Not Assigned') ?></p>
                            <p class="mb-2"><strong>Type:</strong> <?= esc($order['order_type']) ?></p>
                        </div>
                    </div>

                    <?php if ((string) $order['order_type'] === 'Repair'): ?>
                        <hr class="my-3">
                        <h6 class="mb-3"><i class="fe fe-tool me-1"></i>Repair Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Received On:</strong> <?= esc($order['repair_received_at'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Receive Wt:</strong> <?= esc(number_format((float) ($order['repair_receive_weight_gm'] ?? 0), 3)) ?> gm</p>
                            </div>
                            <div class="col-12">
                                <p class="mb-1"><strong>Ornament:</strong> <?= esc($order['repair_ornament_details'] ?: '-') ?></p>
                            </div>
                            <div class="col-12">
                                <p class="mb-0"><strong>Work Detail:</strong> <?= esc($order['repair_work_details'] ?: '-') ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">
                    <p class="mb-0"><strong>Notes:</strong> <?= esc($order['order_notes'] ?: '-') ?></p>

                    <?php if ($order['status'] === 'Cancelled'): ?>
                        <div class="alert alert-warning mt-4 mb-0 rounded-4"><i class="fe fe-alert-triangle me-2"></i>This order is cancelled. Reason: <?= esc((string) ($order['cancel_reason'] ?? '-')) ?></div>
                    <?php elseif (empty($order['assigned_karigar_id'])): ?>
                        <div class="alert alert-warning mt-4 mb-0 rounded-4"><i class="fe fe-alert-triangle me-2"></i>Karigar is not assigned. Issuement is blocked until assignment.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="order-section-title mb-0"><i class="fe fe-image me-2"></i>Photo & Summary</h5>
                </div>
                <div class="card-body">
                    <div class="order-photo-box mb-4">
                        <?php if ($orderPhoto !== null): ?>
                            <img src="<?= esc($orderPhoto) ?>" alt="Order Photo" class="img-fluid" style="width: 50%" ;>
                        <?php else: ?>
                            <div class="text-center text-muted py-5"><i class="fe fe-image fs-24"></i>
                                <div class="mt-2">No order photo uploaded</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="order-metric-card p-3 text-center" style="background-color: #c8ffdc;border: solid, #39b36e;">
                                <div class="text-muted small">Gold Required</div>
                                <div class="h3 mb-0 text-success fw-bold"><?= esc(number_format((float) $summary['gold_required_gm'], 3)) ?> <small>gm</small></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="order-metric-card p-3 text-center" style="background-color: #c8f0ff;border: solid, #4c39b3;">
                                <div class="text-muted small">Diamond Required</div>
                                <div class="h3 mb-0 text-primary fw-bold"><?= esc(number_format((float) $summary['diamond_required_cts'], 3)) ?> <small>cts</small></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gold ledger full width -->
    <div class="card order-grid-card" id="gold-ledger">
        <div class="card-header">
            <h5 class="order-section-title mb-0"><i class="fe fe-dollar-sign me-2" style="color: #b45309;"></i>Gold Ledger (Auto)</h5>
        </div>
        <div class="card-body p-0 p-3">
            <div class="table-responsive">
                <table class="table datatable table-sm table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Karigar</th>
                            <th>Location</th>
                            <th>Purity</th>
                            <th>Weight</th>
                            <th>Pure Gold</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($goldLedgers === []): ?><tr>
                                <td colspan="9" class="text-muted text-center py-3">No entries</td>
                            </tr><?php endif; ?>
                        <?php foreach ($goldLedgers as $gl): ?>
                            <tr>
                                <td><?= esc(ucfirst((string) $gl['entry_type'])) ?></td>
                                <td><?= esc($gl['karigar_name'] ?: '-') ?></td>
                                <td><?= esc($gl['location_name'] ?: '-') ?></td>
                                <td><?= esc(trim(($gl['purity_code'] ?? '') . ' ' . ($gl['color_name'] ?? '')) ?: '-') ?></td>
                                <td><?= number_format((float) $gl['weight_gm'], 3) ?></td>
                                <td><?= number_format((float) ($gl['pure_gold_weight_gm'] ?? 0), 3) ?></td>
                                <td><?= esc(($gl['reference_type'] ?: '-') . ($gl['reference_id'] ? ' #' . $gl['reference_id'] : '')) ?></td>
                                <td><?= esc($gl['notes'] ?: '-') ?></td>
                                <td><?= esc((string) $gl['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Diamond transactions full width -->
    <div class="card order-grid-card" id="diamond-ledger">
        <div class="card-header">
            <h5 class="order-section-title mb-0"><i class="fe fe-gem me-2" style="color: #2563eb;"></i>Diamond Transactions (Issue / Return)</h5>
        </div>
        <div class="card-body p-0 p-3">
            <div class="d-flex flex-wrap gap-3 mb-3">
                <span class="badge bg-light text-dark">Issued: <?= number_format((float) ($diamondInventorySummary['issue_carat'] ?? 0), 3) ?> cts / <?= number_format((float) ($diamondInventorySummary['issue_pcs'] ?? 0), 3) ?> pcs</span>
                <span class="badge bg-light text-dark">Returned: <?= number_format((float) ($diamondInventorySummary['return_carat'] ?? 0), 3) ?> cts / <?= number_format((float) ($diamondInventorySummary['return_pcs'] ?? 0), 3) ?> pcs</span>
                <span class="badge bg-light text-dark">Pending: <?= number_format((float) ($diamondInventorySummary['balance_carat'] ?? 0), 3) ?> cts / <?= number_format((float) ($diamondInventorySummary['balance_pcs'] ?? 0), 3) ?> pcs</span>
            </div>
            <div class="table-responsive">
                <table class="table datatable table-sm table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Ref</th>
                            <th>Date</th>
                            <th>Party</th>
                            <th>Purpose</th>
                            <th>Item</th>
                            <th>Chalni</th>
                            <th>Color</th>
                            <th>Clarity</th>
                            <th>PCS</th>
                            <th>CTS</th>
                            <th>Rate/cts</th>
                            <th>Value</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($diamondInventoryTransactions ?? []) === []): ?><tr>
                                <td colspan="14" class="text-muted text-center py-3">No issue/return transactions linked to this order.</td>
                            </tr><?php endif; ?>
                        <?php foreach (($diamondInventoryTransactions ?? []) as $tx): ?>
                            <tr>
                                <td><?= esc((string) ($tx['txn_type'] ?? '-')) ?></td>
                                <td><?= esc(((string) ($tx['txn_type'] ?? '') === 'Issue' ? 'ISS-' : 'RET-') . (string) ($tx['header_id'] ?? '')) ?></td>
                                <td><?= esc((string) ($tx['txn_date'] ?? '-')) ?></td>
                                <td><?= esc((string) ($tx['party_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($tx['purpose'] ?? '-')) ?></td>
                                <td><?= esc(trim((string) (($tx['diamond_type'] ?? '') . ' ' . (($tx['shape'] ?? '') ? '(' . $tx['shape'] . ')' : '')))) ?></td>
                                <td><?= esc(($tx['chalni_from'] !== null && $tx['chalni_to'] !== null) ? ($tx['chalni_from'] . '-' . $tx['chalni_to']) : 'NA') ?></td>
                                <td><?= esc((string) ($tx['color'] ?? '-')) ?></td>
                                <td><?= esc((string) ($tx['clarity'] ?? '-')) ?></td>
                                <td><?= number_format((float) ($tx['pcs'] ?? 0), 3) ?></td>
                                <td><?= number_format((float) ($tx['carat'] ?? 0), 3) ?></td>
                                <td><?= $tx['rate_per_carat'] === null ? '-' : number_format((float) $tx['rate_per_carat'], 2) ?></td>
                                <td><?= $tx['line_value'] === null ? '-' : number_format((float) $tx['line_value'], 2) ?></td>
                                <td><?= esc((string) ($tx['notes'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- budget & issuance row -->
    <div class="row g-4 mb-4" id="budget-issuance">
        <div class="col-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="order-section-title mb-0"><i class="fe fe-bar-chart-2 me-2"></i>Budget Monitor</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th></th>
                                    <th>Gold (gm)</th>
                                    <th>Diamond (cts)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Budget</td>
                                    <td class="fw-semibold"><?= number_format((float)$budgetMonitor['budget_gold'], 3) ?></td>
                                    <td class="fw-semibold"><?= number_format((float)$budgetMonitor['budget_diamond'], 3) ?></td>
                                </tr>
                                <tr>
                                    <td>Total Issued</td>
                                    <td><?= number_format((float)$budgetMonitor['issue_gold'], 3) ?></td>
                                    <td><?= number_format((float)$budgetMonitor['issue_diamond'], 3) ?></td>
                                </tr>
                                <tr>
                                    <td>Total Received</td>
                                    <td><?= number_format((float)$budgetMonitor['receive_gold'], 3) ?></td>
                                    <td><?= number_format((float)$budgetMonitor['receive_diamond'], 3) ?></td>
                                </tr>
                                <tr class="<?= ($budgetMonitor['over_receive_gold'] > 0 || $budgetMonitor['over_receive_diamond'] > 0) ? 'budget-warning' : '' ?>">
                                    <td>Over on Receive</td>
                                    <td class="<?= $budgetMonitor['over_receive_gold'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format((float)$budgetMonitor['over_receive_gold'], 3) ?></td>
                                    <td class="<?= $budgetMonitor['over_receive_diamond'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format((float)$budgetMonitor['over_receive_diamond'], 3) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info d-flex align-items-center rounded-4 mb-0" role="alert"><i class="fe fe-info me-2"></i> If receive exceeds budget, warning appears with over-budget grams/cts.</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <!-- stone ledger card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="order-section-title mb-0"><i class="fe fe-layers me-2" style="color: #7e22ce;"></i>Stone Ledger (Auto)</h5>
                </div>
                <div class="card-body p-0 p-3">
                    <div class="table-responsive">
                        <table class="table datatable table-sm table-hover table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Karigar</th>
                                    <th>Location</th>
                                    <th>Stone</th>
                                    <th>PCS</th>
                                    <th>CTS</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stoneLedgers === []): ?><tr>
                                        <td colspan="9" class="text-muted text-center py-3">No stone ledger entries</td>
                                    </tr><?php endif; ?>
                                <?php foreach ($stoneLedgers as $sl): ?>
                                    <?php
                                        $stoneLabel = trim(implode(' | ', array_filter([
                                            (string) ($sl['stone_type'] ?? ''),
                                            (string) ($sl['size'] ?? ''),
                                            (string) ($sl['stone_item_type'] ?? ''),
                                            (string) ($sl['color'] ?? ''),
                                            (string) ($sl['quality'] ?? ''),
                                        ], static fn(string $v): bool => trim($v) !== '')));
                                        if ($stoneLabel === '') {
                                            $stoneLabel = '-';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= esc(ucfirst((string)$sl['entry_type'])) ?></td>
                                        <td><?= esc($sl['karigar_name'] ?: '-') ?></td>
                                        <td><?= esc($sl['location_name'] ?: '-') ?></td>
                                        <td><?= esc($stoneLabel) ?></td>
                                        <td><?= number_format((float) ($sl['pcs'] ?? 0), 3) ?></td>
                                        <td><?= number_format((float) ($sl['weight_cts'] ?? 0), 3) ?></td>
                                        <td><?= esc(((string) ($sl['reference_type'] ?? '-')) . (!empty($sl['reference_id']) ? (' #' . (int) $sl['reference_id']) : '')) ?></td>
                                        <td><?= esc((string) ($sl['notes'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($sl['created_at'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- movement history full width -->
    <div class="card order-grid-card">
        <div class="card-header">
            <h5 class="order-section-title mb-0"><i class="fe fe-clock me-2"></i>Issue / Receive History</h5>
        </div>
        <div class="card-body p-0 p-3">
            <div class="table-responsive">
                <table class="table datatable table-sm table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Karigar</th>
                            <th>Location</th>
                            <th>Gold Purity</th>
                            <th>Gross</th>
                            <th>Other</th>
                            <th>Diamond(gm)</th>
                            <th>Gold(gm)</th>
                            <th>Dia(cts)</th>
                            <th>Pure Gold</th>
                            <th>Notes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movements === []): ?><tr>
                                <td colspan="12" class="text-muted text-center py-3">No movements</td>
                            </tr><?php endif; ?>
                        <?php foreach ($movements as $mv): ?>
                            <tr>
                                <td><?= esc(ucfirst((string) $mv['movement_type'])) ?></td>
                                <td><?= esc($mv['karigar_name'] ?: '-') ?></td>
                                <td><?= esc($mv['location_name'] ?: '-') ?></td>
                                <td><?= esc(trim(($mv['purity_code'] ?? '') . ' ' . ($mv['color_name'] ?? '')) ?: '-') ?></td>
                                <td><?= number_format((float) ($mv['gross_weight_gm'] ?? 0), 3) ?></td>
                                <td><?= number_format((float) ($mv['other_weight_gm'] ?? 0), 3) ?></td>
                                <td><?= number_format((float) ($mv['diamond_weight_gm'] ?? 0), 3) ?></td>
                                <td><?= number_format((float) $mv['gold_gm'], 3) ?></td>
                                <td><?= number_format((float) $mv['diamond_cts'], 3) ?></td>
                                <td><?= number_format((float) ($mv['pure_gold_weight_gm'] ?? 0), 3) ?></td>
                                <td><?= esc($mv['notes'] ?: '-') ?></td>
                                <td><?= esc((string) $mv['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- attachments full width -->
    <div class="card order-grid-card">
        <div class="card-header">
            <h5 class="order-section-title mb-0"><i class="fe fe-paperclip me-2"></i>Attachments</h5>
        </div>
        <div class="card-body">
            <form action="<?= site_url('admin/orders/' . $order['id'] . '/attachments') ?>" method="post" enctype="multipart/form-data" class="mb-3 bg-soft-primary p-3 rounded-4">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-7"><input type="file" name="order_files[]" class="form-control" multiple></div>
                    <div class="col-md-3"><select name="file_type" class="form-select">
                            <option value="reference">Reference</option>
                            <option value="cad">CAD</option>
                            <option value="photo">Photo</option>
                            <option value="approval">Approval</option>
                        </select></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fe fe-upload"></i> Upload</button></div>
                </div>
            </form>

            <ul class="list-group list-group-flush bg-transparent">
                <?php if ($generalAttachments === []): ?><li class="list-group-item text-muted bg-transparent">No business attachments uploaded.</li><?php endif; ?>
                <?php foreach ($generalAttachments as $file): ?>
                    <li class="list-group-item bg-transparent d-flex align-items-center justify-content-between px-0">
                        <span><i class="fe fe-file me-2"></i> <?= esc($file['file_type']) ?> - <?= esc($file['file_name']) ?></span>
                        <a href="<?= base_url($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">Open</a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <hr class="my-3">
            <h6 class="mb-2"><i class="fe fe-shield me-1"></i>Audit Proofs</h6>
            <ul class="list-group list-group-flush bg-transparent">
                <?php if ($auditAttachments === []): ?><li class="list-group-item text-muted bg-transparent">No audit proofs uploaded yet.</li><?php endif; ?>
                <?php foreach ($auditAttachments as $file): ?>
                    <li class="list-group-item bg-transparent d-flex align-items-center justify-content-between px-0">
                        <span><i class="fe fe-camera me-2 text-warning"></i> <?= esc($file['file_type']) ?> - <?= esc($file['file_name']) ?></span>
                        <a href="<?= base_url($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-warning rounded-pill">View</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</div>

<!-- modals (unchanged structurally, but with creative touch: rounded, icons) -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fe fe-refresh-cw me-2"></i>Update Status - <?= esc($order['order_no']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= site_url('admin/orders/' . $order['id'] . '/status') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= esc($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= esc($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Audit Image <span class="text-danger">*</span></label>
                        <input type="file" name="audit_image" accept="image/*" class="form-control" required>
                        <small class="text-muted">Image proof is mandatory for status change.</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fe fe-download me-2"></i>Receive Material - <?= esc($order['order_no']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= site_url('admin/orders/' . $order['id'] . '/receive') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="js-receive-modal">
                        <div class="card border mb-3">
                            <div class="card-header py-2"><strong>1. Weight Section</strong></div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Receive Location</label>
                                        <select name="location_id" class="form-select" required>
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $loc): ?>
                                                <option value="<?= esc((string)$loc['id']) ?>"><?= esc($loc['name'] . ' (' . $loc['location_type'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gross Weight (gm)</label>
                                        <input type="number" step="0.001" min="0" name="gross_weight_gm" class="form-control js-gross-weight" value="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Purity %</label>
                                        <input type="number" step="0.001" min="0" max="100" name="purity_percent" class="form-control js-purity-percent" value="<?= esc((string) number_format((float) ($receivePurityPercent ?? 100), 3, '.', '')) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Net Weight (gm)</label>
                                        <input type="text" class="form-control js-net-weight" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Pure Weight (gm)</label>
                                        <input type="text" class="form-control js-pure-weight" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Gold Rate / gm</label>
                                        <input type="number" step="0.01" min="0" name="gold_rate_per_gm" class="form-control js-gold-rate" value="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Gold Total</label>
                                        <input type="text" class="form-control js-gold-total" value="0.00" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>2. Studded Diamond</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-dia-row"><i class="fe fe-plus"></i></button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" data-dt-skip="1">
                                        <thead><tr><th>Dia Type</th><th>Pcs</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th></th></tr></thead>
                                        <tbody class="js-dia-body">
                                            <?php $prefillDiaRows = (array) ($receivePrefill['diamond_rows'] ?? []); ?>
                                            <?php if ($prefillDiaRows === []): ?>
                                                <tr>
                                                    <td><input type="text" name="studded_diamond_type[]" class="form-control"></td>
                                                    <td><input type="number" step="0.001" min="0" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="0"></td>
                                                    <td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td>
                                                    <td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0" readonly></td>
                                                    <td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($prefillDiaRows as $dr): ?>
                                                    <?php
                                                        $drPcs = (float) ($dr['pcs'] ?? 0);
                                                        $drWt = (float) ($dr['weight_cts'] ?? 0);
                                                        $drRate = (float) ($dr['rate'] ?? 0);
                                                        $drTotal = $drWt * $drRate;
                                                    ?>
                                                    <tr>
                                                        <td><input type="text" name="studded_diamond_type[]" class="form-control" value="<?= esc((string) ($dr['type'] ?? '')) ?>"></td>
                                                        <td><input type="number" step="0.001" min="0" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="<?= esc((string) number_format($drPcs, 3, '.', '')) ?>"></td>
                                                        <td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="<?= esc((string) number_format($drWt, 3, '.', '')) ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="<?= esc((string) number_format($drRate, 2, '.', '')) ?>" readonly></td>
                                                        <td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="<?= esc((string) number_format($drTotal, 2, '.', '')) ?>" readonly></td>
                                                        <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>3. Stone</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-stone-row"><i class="fe fe-plus"></i></button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" data-dt-skip="1">
                                        <thead><tr><th>Type</th><th>Pcs</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th></th></tr></thead>
                                        <tbody class="js-stone-body">
                                            <?php $prefillStoneRows = (array) ($receivePrefill['stone_rows'] ?? []); ?>
                                            <?php if ($prefillStoneRows === []): ?>
                                                <tr>
                                                    <td><input type="text" name="stone_type[]" class="form-control"></td>
                                                    <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td>
                                                    <td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td>
                                                    <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0" readonly></td>
                                                    <td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="0.00" readonly></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($prefillStoneRows as $sr): ?>
                                                    <?php
                                                        $srPcs = (float) ($sr['pcs'] ?? 0);
                                                        $srWt = (float) ($sr['weight_cts'] ?? 0);
                                                        $srRate = (float) ($sr['rate'] ?? 0);
                                                        $srTotal = $srWt * $srRate;
                                                    ?>
                                                    <tr>
                                                        <td><input type="text" name="stone_type[]" class="form-control" value="<?= esc((string) ($sr['type'] ?? '')) ?>"></td>
                                                        <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="<?= esc((string) number_format($srPcs, 3, '.', '')) ?>"></td>
                                                        <td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="<?= esc((string) number_format($srWt, 3, '.', '')) ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="<?= esc((string) number_format($srRate, 2, '.', '')) ?>" readonly></td>
                                                        <td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="<?= esc((string) number_format($srTotal, 2, '.', '')) ?>" readonly></td>
                                                        <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header py-2"><strong>4. Labour Details</strong></div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Labour Rate</label>
                                        <input type="number" step="0.01" min="0" name="labour_rate_per_gm" class="form-control js-labour-rate" value="<?= esc((string) number_format((float) ($receiveLabourRate ?? 0), 2, '.', '')) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Total Labour</label>
                                        <input type="text" name="labour_total" class="form-control js-labour-total" value="0.00" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>5. Other</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-other-row"><i class="fe fe-plus"></i></button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" data-dt-skip="1">
                                        <thead><tr><th>Disc</th><th>Pcs</th><th>Weight (gm)</th><th>Price</th><th>Total</th><th></th></tr></thead>
                                        <tbody class="js-other-body">
                                            <tr>
                                                <td><input type="text" name="other_desc[]" class="form-control"></td>
                                                <td><input type="number" step="0.001" min="0" name="other_pcs[]" class="form-control js-other-pcs" value="0"></td>
                                                <td><input type="number" step="0.001" min="0" name="other_weight_line_gm[]" class="form-control js-other-weight" value="0"></td>
                                                <td><input type="number" step="0.01" min="0" name="other_price[]" class="form-control js-other-price" value="0"></td>
                                                <td><input type="text" name="other_total[]" class="form-control js-other-total" value="0.00" readonly></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Receive</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($order['status'] !== 'Cancelled'): ?>
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="fe fe-x-circle me-2"></i>Cancel Order - <?= esc($order['order_no']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= site_url('admin/orders/' . $order['id'] . '/cancel') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4">Cancel Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
