<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $orderMode = (string) ($orderMode ?? 'all'); ?>
<?php $isReadyMode = $orderMode === 'ready'; ?>
<?php $isAllMode = $orderMode === 'all'; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= esc($title ?? 'Orders') ?></h4>
    <div class="d-flex gap-2">
        <?php if (! in_array($orderMode, ['repair', 'ready'], true)): ?>
            <a href="<?= site_url('admin/orders/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Create Order</a>
        <?php endif; ?>
        <?php if ($orderMode !== 'ready'): ?>
            <a href="<?= site_url('admin/orders/repair/create') ?>" class="btn btn-outline-primary"><i class="fe fe-settings"></i> Repair Receive</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Karigar</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $isCancelled = (string) ($order['status'] ?? '') === 'Cancelled';
                            $isCompleted = (string) ($order['status'] ?? '') === 'Completed';
                            $isLocked = $isCancelled || $isCompleted;
                        ?>
                        <tr>
                            <td><?= esc($order['order_no']) ?></td>
                            <td><?= esc($order['customer_name'] ?: '-') ?></td>
                            <td>
                                <?php if (! empty($order['karigar_name'])): ?>
                                    <span class="badge bg-success-light text-success"><?= esc($order['karigar_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning-light text-warning">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($order['order_type']) ?></td>
                            <td><?= esc($order['status']) ?></td>
                            <td><?= esc($order['priority']) ?></td>
                            <td><?= esc($order['due_date'] ?: '-') ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($isReadyMode): ?>
                                        <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary" title="Order Details">
                                            <i class="fe fe-eye me-1"></i>Order Details
                                        </a>
                                        <a href="<?= site_url('admin/orders/' . $order['id'] . '/ornament-details') ?>" class="btn btn-sm btn-outline-dark" title="Ornament Details">
                                            <i class="fe fe-image me-1"></i>Ornament Details
                                        </a>
                                    <?php elseif ($isAllMode): ?>
                                        <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <?php if ($isCompleted): ?>
                                            <a href="<?= site_url('admin/orders/' . $order['id'] . '/packing-list/generate') ?>" class="btn btn-sm btn-outline-primary" title="Generate Packing List">
                                                <i class="fe fe-package"></i>
                                            </a>
                                            <a href="<?= site_url('admin/orders/' . $order['id'] . '/delivery-challan?download=1') ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Delivery Challan">
                                                <i class="fe fe-file-text"></i>
                                            </a>
                                        <?php elseif ($isCancelled): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Cancelled order">
                                                <i class="fe fe-lock"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info" title="Edit">
                                                <i class="fe fe-edit"></i>
                                            </a>
                                            <?php if (empty($order['assigned_karigar_id'])): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-success js-assign-btn"
                                                    data-order-id="<?= esc((string) $order['id']) ?>"
                                                    data-order-no="<?= esc($order['order_no']) ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assignKarigarModal"
                                                    title="Assign Karigar">
                                                    <i class="fe fe-user-plus"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Already Assigned">
                                                    <i class="fe fe-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-success js-receive-btn"
                                                data-order-id="<?= esc((string) $order['id']) ?>"
                                                data-order-no="<?= esc($order['order_no']) ?>"
                                                data-order-purity="<?= esc((string) number_format((float) ($order['avg_purity_percent'] ?? 100), 3, '.', '')) ?>"
                                                data-karigar-rate="<?= esc((string) number_format((float) ($order['karigar_rate_per_gm'] ?? 0), 2, '.', '')) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#receiveModal"
                                                title="Receive">
                                                <i class="fe fe-download"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger js-cancel-btn"
                                                data-order-id="<?= esc((string) $order['id']) ?>"
                                                data-order-no="<?= esc($order['order_no']) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#cancelOrderModal"
                                                title="Cancel Order">
                                                <i class="fe fe-x-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (! $isReadyMode): ?>
<div class="modal fade" id="assignKarigarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Karigar to <span id="assign-order-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assign-karigar-form" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Karigar</label>
                        <select class="form-control" id="assign-karigar-select" name="karigar_id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($karigars as $karigar): ?>
                                <option value="<?= esc((string) $karigar['id']) ?>"><?= esc($karigar['name'] . ' - ' . ($karigar['department'] ?: 'General')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="border rounded p-3 bg-light">
                        <h6 class="mb-2">Current Load</h6>
                        <div id="kg-summary-loader" class="d-none mb-2 text-primary small">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Loading karigar summary...
                        </div>
                        <div class="mb-1">Total Gold With Him: <strong id="kg-total-gold">0.000</strong> gm</div>
                        <div class="mb-1">Pending Orders: <strong id="kg-pending-orders">0</strong></div>
                        <div class="mb-0">Pending Order Gold Weight: <strong id="kg-pending-gold">0.000</strong> gm</div>
                    </div>
                    <div class="border rounded p-3 mt-3">
                        <h6 class="mb-2">Order Details</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" data-dt-skip="1">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Status</th>
                                        <th>Due</th>
                                        <th>Req (gm)</th>
                                        <th>With Him (gm)</th>
                                    </tr>
                                </thead>
                                <tbody id="kg-order-details">
                                    <tr><td colspan="5" class="text-center text-muted">Select karigar to view details.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assign-submit-btn">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receive Material - <span id="receive-order-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="receive-form" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="js-receive-modal">
                        <div class="card border mb-3">
                            <div class="card-header py-2"><strong>1. Weight Section</strong></div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Receive Location</label>
                                        <select name="location_id" class="form-control" required>
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $loc): ?>
                                                <option value="<?= esc((string) $loc['id']) ?>"><?= esc($loc['name'] . ' (' . $loc['location_type'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gross Weight (gm)</label>
                                        <input type="number" step="0.001" min="0" name="gross_weight_gm" class="form-control js-gross-weight" value="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Purity %</label>
                                        <input type="number" step="0.001" min="0" max="100" name="purity_percent" id="receive-purity-percent" class="form-control js-purity-percent" value="100">
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
                                            <tr>
                                                <td><input type="text" name="studded_diamond_type[]" class="form-control"></td>
                                                <td><input type="number" step="0.001" min="0" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="0"></td>
                                                <td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td>
                                                <td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0" readonly></td>
                                                <td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                            </tr>
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
                                            <tr>
                                                <td><input type="text" name="stone_type[]" class="form-control"></td>
                                                <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td>
                                                <td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td>
                                                <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0" readonly></td>
                                                <td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="0.00" readonly></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                                            </tr>
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
                                        <input type="number" step="0.01" min="0" name="labour_rate_per_gm" id="receive-labour-rate" class="form-control js-labour-rate" value="0">
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

                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Receive</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order - <span id="cancel-order-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cancel-order-form" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <label class="form-label">Cancel Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="cancel_reason" rows="3" required placeholder="Enter cancellation reason"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! $isReadyMode): ?>
<script>
    (function () {
        const assignForm = document.getElementById('assign-karigar-form');
        const orderLabel = document.getElementById('assign-order-label');
        const karigarSelect = document.getElementById('assign-karigar-select');
        const totalGoldEl = document.getElementById('kg-total-gold');
        const pendingOrdersEl = document.getElementById('kg-pending-orders');
        const pendingGoldEl = document.getElementById('kg-pending-gold');
        const orderDetailsEl = document.getElementById('kg-order-details');
        const summaryLoaderEl = document.getElementById('kg-summary-loader');
        const assignSubmitBtn = document.getElementById('assign-submit-btn');

        const receiveForm = document.getElementById('receive-form');
        const receiveOrderLabel = document.getElementById('receive-order-label');
        const receiveModal = document.getElementById('receiveModal');

        const cancelForm = document.getElementById('cancel-order-form');
        const cancelOrderLabel = document.getElementById('cancel-order-label');

        const assignBase = '<?= site_url('admin/orders') ?>';
        const summaryBase = '<?= site_url('admin/karigars') ?>';
        let summaryRequestSeq = 0;

        function num(v) {
            const n = parseFloat(String(v === undefined || v === null ? '' : v));
            return Number.isFinite(n) ? n : 0;
        }

        function attr(v) {
            return String(v === undefined || v === null ? '' : v)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/'/g, '&#39;');
        }

        function createRowHtml(kind, row) {
            const r = row || {};
            const type = attr(r.type || '');
            const pcs = num(r.pcs || 0);
            const wt = num(r.weight_cts || 0);
            const rate = num(r.rate || 0);
            const total = wt * rate;
            if (kind === 'dia') {
                return '<tr>'
                    + '<td><input type="text" name="studded_diamond_type[]" class="form-control" value="' + type + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="' + pcs.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="' + wt.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="' + rate.toFixed(2) + '" readonly></td>'
                    + '<td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="' + total.toFixed(2) + '" readonly></td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                    + '</tr>';
            }
            if (kind === 'stone') {
                return '<tr>'
                    + '<td><input type="text" name="stone_type[]" class="form-control" value="' + type + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="' + pcs.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="' + wt.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="' + rate.toFixed(2) + '" readonly></td>'
                    + '<td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="' + total.toFixed(2) + '" readonly></td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                    + '</tr>';
            }
            return '<tr>'
                + '<td><input type="text" name="other_desc[]" class="form-control" value="' + type + '"></td>'
                + '<td><input type="number" step="0.001" min="0" name="other_pcs[]" class="form-control js-other-pcs" value="' + pcs.toFixed(3) + '"></td>'
                + '<td><input type="number" step="0.001" min="0" name="other_weight_line_gm[]" class="form-control js-other-weight" value="' + wt.toFixed(3) + '"></td>'
                + '<td><input type="number" step="0.01" min="0" name="other_price[]" class="form-control js-other-price" value="' + rate.toFixed(2) + '"></td>'
                + '<td><input type="text" name="other_total[]" class="form-control js-other-total" value="' + total.toFixed(2) + '" readonly></td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>'
                + '</tr>';
        }

        function ensureSingleRow(tbodySelector, kind) {
            if (!receiveModal) return;
            const body = receiveModal.querySelector(tbodySelector);
            if (!body) return;
            body.innerHTML = createRowHtml(kind);
        }

        function setRowsFromPrefill(tbodySelector, kind, rows) {
            if (!receiveModal) return;
            const body = receiveModal.querySelector(tbodySelector);
            if (!body) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                body.innerHTML = createRowHtml(kind);
                return;
            }
            body.innerHTML = rows.map(function (row) {
                return createRowHtml(kind, row);
            }).join('');
        }

        function fetchReceivePrefill(orderId) {
            if (!receiveModal || !orderId) return;
            const diaBody = receiveModal.querySelector('.js-dia-body');
            const stoneBody = receiveModal.querySelector('.js-stone-body');
            if (diaBody) diaBody.innerHTML = '<tr><td colspan="6" class="text-center text-primary"><span class="spinner-border spinner-border-sm me-1"></span>Loading pending diamond...</td></tr>';
            if (stoneBody) stoneBody.innerHTML = '<tr><td colspan="6" class="text-center text-primary"><span class="spinner-border spinner-border-sm me-1"></span>Loading pending stone...</td></tr>';

            fetch(assignBase + '/' + orderId + '/receive-prefill', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('prefill request failed');
                    return res.json();
                })
                .then(function (json) {
                    const data = json && json.status === 'ok' ? (json.data || {}) : {};
                    const purityEl = receiveModal.querySelector('#receive-purity-percent');
                    const labourEl = receiveModal.querySelector('#receive-labour-rate');
                    if (purityEl && data.purity_percent !== undefined) purityEl.value = Number(data.purity_percent || 0).toFixed(3);
                    if (labourEl && data.labour_rate !== undefined) labourEl.value = Number(data.labour_rate || 0).toFixed(2);
                    setRowsFromPrefill('.js-dia-body', 'dia', data.diamond_rows || []);
                    setRowsFromPrefill('.js-stone-body', 'stone', data.stone_rows || []);
                    recalcReceiveModal();
                })
                .catch(function () {
                    ensureSingleRow('.js-dia-body', 'dia');
                    ensureSingleRow('.js-stone-body', 'stone');
                    recalcReceiveModal();
                });
        }

        function recalcReceiveModal() {
            if (!receiveModal) return;

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
                if (diaW && diaR && diaT) {
                    diaT.value = (num(diaW.value) * num(diaR.value)).toFixed(2);
                }

                const stW = row.querySelector('.js-stone-weight');
                const stR = row.querySelector('.js-stone-rate');
                const stT = row.querySelector('.js-stone-total');
                if (stW && stR && stT) {
                    stT.value = (num(stW.value) * num(stR.value)).toFixed(2);
                }

                const oP = row.querySelector('.js-other-price');
                const oT = row.querySelector('.js-other-total');
                if (oP && oT) {
                    oT.value = num(oP.value).toFixed(2);
                }
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

        function setSummaryLoading(loading) {
            if (summaryLoaderEl) summaryLoaderEl.classList.toggle('d-none', !loading);
            if (assignSubmitBtn) {
                const hasKarigar = !!(karigarSelect && karigarSelect.value);
                assignSubmitBtn.disabled = loading || !hasKarigar;
            }
            if (!loading) return;
            if (totalGoldEl) totalGoldEl.textContent = '...';
            if (pendingOrdersEl) pendingOrdersEl.textContent = '...';
            if (pendingGoldEl) pendingGoldEl.textContent = '...';
            if (orderDetailsEl) {
                orderDetailsEl.innerHTML = '<tr><td colspan="5" class="text-center text-primary"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading order details...</td></tr>';
            }
        }

        function resetKarigarSummary() {
            if (summaryLoaderEl) summaryLoaderEl.classList.add('d-none');
            if (totalGoldEl) totalGoldEl.textContent = '0.000';
            if (pendingOrdersEl) pendingOrdersEl.textContent = '0';
            if (pendingGoldEl) pendingGoldEl.textContent = '0.000';
            if (assignSubmitBtn) assignSubmitBtn.disabled = !(karigarSelect && karigarSelect.value);
            if (orderDetailsEl) {
                orderDetailsEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Select karigar to view details.</td></tr>';
            }
        }

        function renderOrderDetails(orders) {
            if (!orderDetailsEl) return;
            if (!Array.isArray(orders) || orders.length === 0) {
                orderDetailsEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No pending orders for this karigar.</td></tr>';
                return;
            }

            const rows = orders.map(function (row) {
                const orderNo = String(row.order_no || '-');
                const status = String(row.status || '-');
                const dueDate = String(row.due_date || '-');
                const req = Number(row.required_gold_gm || 0).toFixed(3);
                const bal = Number(row.balance_gold_gm || 0).toFixed(3);
                return '<tr>'
                    + '<td>' + orderNo + '</td>'
                    + '<td>' + status + '</td>'
                    + '<td>' + dueDate + '</td>'
                    + '<td>' + req + '</td>'
                    + '<td>' + bal + '</td>'
                    + '</tr>';
            }).join('');

            orderDetailsEl.innerHTML = rows;
        }

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const btn = target.closest('button');
            if (!btn) return;

            const orderId = btn.getAttribute('data-order-id');
            const orderNo = btn.getAttribute('data-order-no') || '';

            if (btn.classList.contains('js-assign-btn')) {
                if (assignForm && orderId) assignForm.setAttribute('action', assignBase + '/' + orderId + '/assign');
                if (orderLabel) orderLabel.textContent = orderNo;
                if (karigarSelect) karigarSelect.value = '';
                resetKarigarSummary();
            }

            if (btn.classList.contains('js-receive-btn')) {
                if (receiveForm && orderId) receiveForm.setAttribute('action', assignBase + '/' + orderId + '/receive');
                if (receiveOrderLabel) receiveOrderLabel.textContent = orderNo;
                const purity = btn.getAttribute('data-order-purity') || '100';
                const labourRate = btn.getAttribute('data-karigar-rate') || '0';
                if (receiveModal) {
                    if (receiveForm) receiveForm.reset();
                    const purityEl = receiveModal.querySelector('#receive-purity-percent');
                    const labourEl = receiveModal.querySelector('#receive-labour-rate');
                    if (purityEl) purityEl.value = purity;
                    if (labourEl) labourEl.value = labourRate;
                    ensureSingleRow('.js-dia-body', 'dia');
                    ensureSingleRow('.js-stone-body', 'stone');
                    ensureSingleRow('.js-other-body', 'other');
                    recalcReceiveModal();
                    fetchReceivePrefill(orderId);
                }
            }

            if (btn.classList.contains('js-cancel-btn')) {
                if (cancelForm && orderId) cancelForm.setAttribute('action', assignBase + '/' + orderId + '/cancel');
                if (cancelOrderLabel) cancelOrderLabel.textContent = orderNo;
            }

        });

        if (karigarSelect) {
            karigarSelect.addEventListener('change', function () {
                const karigarId = karigarSelect.value;
                if (!karigarId) {
                    resetKarigarSummary();
                    return;
                }
                summaryRequestSeq += 1;
                const reqSeq = summaryRequestSeq;
                setSummaryLoading(true);

                fetch(summaryBase + '/' + karigarId + '/summary', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) {
                        if (!res.ok) throw new Error('Summary request failed');
                        return res.json();
                    })
                    .then(function (json) {
                        if (reqSeq !== summaryRequestSeq) return;
                        if (!json || json.status !== 'ok') {
                            resetKarigarSummary();
                            return;
                        }
                        const d = json.data || {};
                        if (totalGoldEl) totalGoldEl.textContent = Number(d.total_gold_with_him || 0).toFixed(3);
                        if (pendingOrdersEl) pendingOrdersEl.textContent = String(d.pending_order_count || 0);
                        if (pendingGoldEl) pendingGoldEl.textContent = Number(d.pending_order_gold_weight || 0).toFixed(3);
                        renderOrderDetails(d.pending_orders || []);
                    })
                    .catch(function () {
                        if (reqSeq !== summaryRequestSeq) return;
                        resetKarigarSummary();
                    })
                    .finally(function () {
                        if (reqSeq !== summaryRequestSeq) return;
                        setSummaryLoading(false);
                    });
            });
            if (assignSubmitBtn) assignSubmitBtn.disabled = true;
        }

        if (receiveModal) {
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
        }
    })();
</script>
<?php endif; ?>
<?= $this->endSection() ?>

