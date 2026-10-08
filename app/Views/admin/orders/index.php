<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $orderMode = (string) ($orderMode ?? 'all'); ?>
<?php $isReadyMode = $orderMode === 'ready'; ?>
<?php $isAllMode = $orderMode === 'all'; ?>
<style>
    .order-list-thumb { align-items: center; background: #f4f5f7; border: 1px solid #e1e5eb; border-radius: 9px; color: #9aa3af; display: inline-flex; height: 44px; justify-content: center; overflow: hidden; position: relative; width: 44px; }
    .order-list-thumb img { height: 100%; inset: 0; object-fit: cover; position: absolute; width: 100%; }
    .order-list-name { min-width: 150px; white-space: normal; }
    #receiveModal .modal-dialog { max-width: min(1480px, calc(100vw - 28px)); }
    #receiveModal .diamond-table-shell::before { content: none !important; display: none !important; }
    #receiveModal .receive-diamond-table { min-width: 0 !important; table-layout: fixed; width: 100%; }
    #receiveModal .receive-diamond-table th, #receiveModal .receive-diamond-table td { white-space: normal !important; }
    #receiveModal .receive-diamond-table th:nth-child(1) { width: 45%; }
    #receiveModal .receive-diamond-table th:nth-child(2) { width: 8%; }
    #receiveModal .receive-diamond-table th:nth-child(3) { width: 11%; }
    #receiveModal .receive-diamond-table th:nth-child(4) { width: 9%; }
    #receiveModal .receive-diamond-table th:nth-child(5) { width: 11%; }
    #receiveModal .receive-diamond-table th:nth-child(6) { width: 16%; }
    #receiveModal .receive-diamond-table .select2-container { max-width: 100%; min-width: 0 !important; }
    #receiveModal .diamond-row-actions { display: flex; flex-direction: column; gap: 6px; min-width: 76px; }
    #receiveModal .diamond-row-actions .btn { justify-content: center; white-space: nowrap; width: 100%; }
    #receiveModal .receive-diamond-table input, #receiveModal .receive-diamond-table .select2-container { min-width: 0; width: 100% !important; }
    #receiveModal .js-stone-inventory-select + .select2-container { min-width: 190px; width: 100% !important; }
    #receiveModal .select2-dropdown { z-index: 2070; }
    @media (max-width: 767.98px) {
        #receiveModal .receive-diamond-table { min-width: 760px !important; }
    }
</style>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Production workflow</span>
        <h4 class="mb-1"><?= esc($title ?? 'Orders') ?></h4>
        <p class="mb-0">Track customer orders, assignments, production and delivery status.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (admin_can('orders.create') && ! in_array($orderMode, ['repair', 'ready'], true)): ?>
            <a href="<?= site_url('admin/orders/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Create Order</a>
        <?php endif; ?>
        <?php if (admin_can('orders.create') && $orderMode !== 'ready'): ?>
            <a href="<?= site_url('admin/orders/repair/create') ?>" class="btn btn-outline-primary"><i class="fe fe-settings"></i> Repair Receive</a>
        <?php endif; ?>
    </div>
</div>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Order No</th>
                        <th>Order Name</th>
                        <th>Category</th>
                        <th>Order From</th>
                        <th>Karigar</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="10" class="text-center text-muted py-5">No orders found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $isCancelled = (string) ($order['status'] ?? '') === 'Cancelled';
                            $isCompleted = (string) ($order['status'] ?? '') === 'Completed';
                            $isLocked = $isCancelled || $isCompleted;
                        ?>
                        <tr>
                            <td><a class="order-list-thumb" href="<?= site_url('admin/orders/' . (int) $order['id']) ?>" aria-label="View order"><i class="fe fe-image"></i><?php if (! empty($order['thumbnail_url'])): ?><img src="<?= esc((string) $order['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php endif; ?></a></td>
                            <td><a class="erp-data-link" href="<?= site_url('admin/orders/' . (int) $order['id']) ?>"><?= esc($order['order_no']) ?></a></td>
                            <td class="order-list-name"><strong><?= esc((string) (($order['order_name'] ?? '') ?: '-')) ?></strong></td>
                            <td><?= esc((string) (($order['order_category_name'] ?? '') ?: '-')) ?></td>
                            <td><?= esc((string) (($order['order_from'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php if (! empty($order['karigar_name'])): ?>
                                    <span class="badge bg-success-light text-success"><?= esc($order['karigar_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning-light text-warning">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($order['order_type']) ?></td>
                            <td><?= esc($order['status']) ?></td>
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
                                    <?php else: ?>
                                        <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <?php if ($isCompleted): ?>
                                            <?php if (admin_can('orders.documents')): ?>
                                                <a href="<?= site_url('admin/orders/' . $order['id'] . '/packing-list/generate') ?>" class="btn btn-sm btn-outline-primary" title="Generate Packing List">
                                                    <i class="fe fe-package"></i>
                                                </a>
                                                <a href="<?= site_url('admin/orders/' . $order['id'] . '/delivery-challan?download=1') ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Delivery Challan">
                                                    <i class="fe fe-file-text"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($isCancelled): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Cancelled order">
                                                <i class="fe fe-lock"></i>
                                            </button>
                                        <?php else: ?>
                                            <?php if (admin_can('orders.edit')): ?>
                                                <a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info" title="Edit">
                                                    <i class="fe fe-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (admin_can('orders.assign')): ?>
                                                <?php if (empty($order['assigned_karigar_id'])): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-success js-assign-btn"
                                                        data-order-id="<?= esc((string) $order['id']) ?>"
                                                        data-order-no="<?= esc($order['order_no']) ?>"
                                                        data-order-from="<?= esc((string) ($order['order_from'] ?? ''), 'attr') ?>"
                                                        data-customer-id="<?= esc((string) ($order['customer_id'] ?? '')) ?>"
                                                        data-followup-assigned-to="<?= esc((string) ($order['followup_assigned_to'] ?? '')) ?>"
                                                        data-followup-due-at="<?= ! empty($order['followup_due_at']) ? esc(date('Y-m-d\\TH:i', strtotime((string) $order['followup_due_at'])), 'attr') : '' ?>"
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
                                            <?php endif; ?>
                                            <?php if (admin_can('orders.receive') && ! empty($order['assigned_karigar_id'])): ?>
                                                <a href="<?= site_url('admin/orders/' . $order['id'] . '/receive') ?>"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Receive">
                                                    <i class="fe fe-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (admin_can('orders.status')): ?>
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

<?php if (! $isReadyMode && admin_can('orders.assign')): ?>
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
                        <label class="form-label">Order From</label>
                        <input type="text" id="assign-order-from" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Customer <span class="text-danger">*</span></label>
                        <select class="form-control" id="assign-customer-select" name="customer_id" required>
                            <option value="">Choose customer...</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= esc((string) $customer['id']) ?>">
                                    <?= esc($customer['name'] . (! empty($customer['phone']) ? ' - ' . $customer['phone'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Customer must be selected before assigning this order.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Karigar</label>
                        <select class="form-control" id="assign-karigar-select" name="karigar_id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($karigars as $karigar): ?>
                                <option value="<?= esc((string) $karigar['id']) ?>"><?= esc($karigar['name'] . ' - ' . ($karigar['department'] ?: 'General')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Order Follower <span class="text-danger">*</span></label>
                            <select class="form-control js-searchable-select" id="assign-follower-select" name="followup_assigned_to" data-placeholder="Search staff follower" required>
                                <option value=""></option>
                                <?php foreach (($staffFollowers ?? []) as $person): ?>
                                    <option value="<?= (int) $person['id'] ?>"><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Follow-up Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="assign-followup-due" name="followup_due_at" value="<?= esc(date('Y-m-d\\T11:00', strtotime('+1 day')), 'attr') ?>" required>
                        </div>
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
                                        <th>Photo</th>
                                        <th>Order</th>
                                        <th>Order Name</th>
                                        <th>Status</th>
                                        <th>Due</th>
                                        <th>Req (gm)</th>
                                    </tr>
                                </thead>
                                <tbody id="kg-order-details">
                                    <tr><td colspan="6" class="text-center text-muted">Select karigar to view details.</td></tr>
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
<?php endif; ?>

<?php if (! $isReadyMode && admin_can('orders.receive')): ?>
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
                        <div class="alert alert-info">
                            Enter finished details manually. Stone shortage is automatically deducted from Stone Inventory; its balance may go negative.
                        </div>
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
                                    <div class="col-md-3">
                                        <label class="form-label">Ornament Purity</label>
                                        <select name="gold_purity_id" id="receive-gold-purity" class="form-select js-purity-select" required>
                                            <option value="">Select from Purity Master</option>
                                            <?php foreach (($goldPurities ?? []) as $purity): ?>
                                                <option value="<?= (int) $purity['id'] ?>" data-percent="<?= esc((string) number_format((float) $purity['purity_percent'], 3, '.', '')) ?>">
                                                    <?= esc((string) $purity['purity_code']) ?> (<?= esc(number_format((float) $purity['purity_percent'], 3)) ?>%)<?= ! empty($purity['color_name']) ? ' · ' . esc((string) $purity['color_name']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Net Weight (gm)</label>
                                        <input type="text" class="form-control js-net-weight" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Pure Weight (gm)</label>
                                        <input type="text" class="form-control js-pure-weight" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gold Rate / gm</label>
                                        <input type="number" step="0.01" min="0.01" name="gold_rate_per_gm" class="form-control js-gold-rate" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gold Total</label>
                                        <input type="text" class="form-control js-gold-total" value="0.00" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>2. Studded Diamond</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-dia-row"><i class="fe fe-plus me-1"></i>Add Row</button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive diamond-table-shell">
                                    <table class="table table-bordered mb-0 receive-diamond-table" data-dt-skip="1">
                                        <thead><tr><th>Available Type / Final Name</th><th>PCS</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th>Actions</th></tr></thead>
                                        <tbody class="js-dia-body">
                                            <tr>
                                                <td><select multiple class="form-select js-diamond-balance-select"></select><input type="hidden" name="studded_diamond_type[]" class="js-dia-type"><input type="text" name="studded_diamond_name[]" class="form-control mt-1 js-dia-name" placeholder="Editable final name"></td>
                                                <td><input type="number" step="1" min="1" name="studded_diamond_pcs[]" class="form-control js-dia-pcs"></td>
                                                <td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td>
                                                <td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0"></td>
                                                <td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td>
                                                <td><div class="diamond-row-actions"><button type="button" class="btn btn-sm btn-outline-primary js-merge-dia-row" title="Use the full selected weight as one finished piece"><i class="fe fe-minimize-2 me-1"></i>1 PCS</button><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" title="Remove row"><i class="fe fe-trash"></i></button></div></td>
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
                                        <thead><tr><th>Inventory Item</th><th>Description</th><th>Pcs</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th></th></tr></thead>
                                        <tbody class="js-stone-body">
                                            <tr>
                                                <td>
                                                    <select name="stone_item_id[]" class="form-select js-stone-inventory-select" data-placeholder="Search stone inventory">
                                                        <option value="">Select stone</option>
                                                        <?php foreach (($stoneInventoryItems ?? []) as $stoneItem): ?>
                                                            <option value="<?= (int) $stoneItem['id'] ?>" data-description="<?= esc((string) (($stoneItem['stone_type'] ?? '') ?: $stoneItem['product_name']), 'attr') ?>" data-rate="<?= esc((string) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 'attr') ?>"><?= esc((string) $stoneItem['product_name'] . (($stoneItem['stone_type'] ?? '') !== '' ? ' · ' . $stoneItem['stone_type'] : '') . ' · ' . number_format((float) $stoneItem['qty_balance'], 3) . ' available · Rate ' . number_format((float) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 2)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="stone_type[]" class="form-control"></td>
                                                <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td>
                                                <td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td>
                                                <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0"></td>
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
                                    <div class="col-md-3">
                                        <label class="form-label">Labour Rate</label>
                                        <input type="number" step="0.01" min="0" name="labour_rate_per_gm" id="receive-labour-rate" class="form-control js-labour-rate" value="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Total Labour</label>
                                        <input type="text" name="labour_total" class="form-control js-labour-total" value="0.00" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Wastage %</label>
                                        <input type="number" step="0.001" min="0" max="100" name="wastage_percent" class="form-control js-wastage-percent" value="0" required>
                                        <small class="text-muted"><span class="js-wastage-weight-text">0.000</span> gm at ornament purity</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Pure Gold Wastage Deduction</label>
                                        <input type="text" class="form-control js-pure-wastage-weight" value="0.000" readonly>
                                        <small class="text-muted">Deducted from karigar pure-gold ledger</small>
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
<?php endif; ?>

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

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! $isReadyMode): ?>
<script>
    (function () {
        const assignForm = document.getElementById('assign-karigar-form');
        const orderLabel = document.getElementById('assign-order-label');
        const orderFromInput = document.getElementById('assign-order-from');
        const customerSelect = document.getElementById('assign-customer-select');
        const karigarSelect = document.getElementById('assign-karigar-select');
        const followerSelect = document.getElementById('assign-follower-select');
        const followupDueInput = document.getElementById('assign-followup-due');
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
        const stoneInventoryItems = <?= json_encode(array_values($stoneInventoryItems ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let activeDiamondOptions = [];
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

        function stoneItemOptions(selectedId) {
            let options = '<option value="">Select stone</option>';
            stoneInventoryItems.forEach(function (item) {
                const id = String(item.id || '');
                const name = String(item.product_name || 'Stone');
                const type = String(item.stone_type || '');
                const rate = num(item.avg_rate || item.default_rate);
                const label = name + (type ? ' · ' + type : '') + ' · ' + num(item.qty_balance).toFixed(3) + ' available · Rate ' + rate.toFixed(2);
                const description = type || name;
                options += '<option value="' + attr(id) + '" data-description="' + attr(description) + '" data-rate="' + rate.toFixed(2) + '"' + (String(selectedId || '') === id ? ' selected' : '') + '>' + attr(label) + '</option>';
            });
            return options;
        }

        function diamondBalanceOptions(selectedValues, select) {
            const selected = Array.isArray(selectedValues) ? selectedValues.map(String) : [];
            const usedElsewhere = new Set();
            if (receiveModal) {
                receiveModal.querySelectorAll('.js-diamond-balance-select').forEach(function (otherSelect) {
                    if (otherSelect === select) return;
                    Array.from(otherSelect.selectedOptions).forEach(function (option) {
                        usedElsewhere.add(String(option.value));
                    });
                });
            }
            let options = '';
            activeDiamondOptions.forEach(function (item) {
                const value = String(item.value || '');
                const label = String(item.label || value || 'Diamond') + ' · ' + num(item.available_cts).toFixed(3) + ' cts / ' + num(item.available_pcs).toFixed(0) + ' pcs';
                options += '<option value="' + attr(value) + '" data-final-name="' + attr(item.label || value) + '" data-available-cts="' + num(item.available_cts).toFixed(3) + '" data-available-pcs="' + num(item.available_pcs).toFixed(3) + '" data-issue-line-id="' + num(item.issue_line_id) + '"' + (selected.indexOf(value) >= 0 ? ' selected' : '') + (usedElsewhere.has(value) ? ' disabled' : '') + '>' + attr(label) + '</option>';
            });
            return options;
        }

        function refreshDiamondSelectors() {
            if (!receiveModal) return;
            receiveModal.querySelectorAll('.js-diamond-balance-select').forEach(function (select) {
                const selected = Array.from(select.selectedOptions).map(function (option) { return option.value; });
                select.innerHTML = diamondBalanceOptions(selected, select);
                const hidden = select.closest('tr').querySelector('.js-dia-type');
                if (hidden) hidden.value = JSON.stringify(selected);
                if (window.jQuery) window.jQuery(select).trigger('change.select2');
            });
        }

        function updateDiamondSelection(select) {
            const row = select.closest('tr');
            if (!row) return;
            const chosen = Array.from(select.selectedOptions);
            const values = chosen.map(function (option) { return option.value; });
            const availablePcs = chosen.reduce(function (sum, option) { return sum + num(option.getAttribute('data-available-pcs')); }, 0);
            const availableCts = chosen.reduce(function (sum, option) { return sum + num(option.getAttribute('data-available-cts')); }, 0);
            const hidden = row.querySelector('.js-dia-type');
            const pcs = row.querySelector('.js-dia-pcs');
            const weight = row.querySelector('.js-dia-weight');
            const name = row.querySelector('.js-dia-name');
            if (hidden) hidden.value = JSON.stringify(values);
            if (pcs) pcs.value = availablePcs > 0 ? String(Math.floor(availablePcs)) : '';
            if (weight) weight.value = availableCts.toFixed(3);
            if (name && name.dataset.edited !== '1') {
                name.value = chosen.map(function (option) {
                    return option.getAttribute('data-final-name') || option.textContent.trim();
                }).join(' + ');
            }
            refreshDiamondSelectors();
            recalcReceiveModal();
        }

        function initStoneInventorySelects() {
            if (!receiveModal || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
            window.jQuery(receiveModal).find('.js-stone-inventory-select, .js-diamond-balance-select, .js-purity-select').each(function () {
                if (window.jQuery(this).hasClass('select2-hidden-accessible')) return;
                window.jQuery(this).select2({
                    width: '100%',
                    allowClear: true,
                    placeholder: window.jQuery(this).hasClass('js-diamond-balance-select')
                        ? 'Search available diamond'
                        : (window.jQuery(this).hasClass('js-purity-select') ? 'Search ornament purity' : 'Search stone inventory'),
                    minimumResultsForSearch: 0,
                    dropdownParent: window.jQuery(receiveModal)
                });
            });
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
                    + '<td><select multiple class="form-select js-diamond-balance-select">' + diamondBalanceOptions([], null) + '</select><input type="hidden" name="studded_diamond_type[]" class="js-dia-type"><input type="text" name="studded_diamond_name[]" class="form-control mt-1 js-dia-name" placeholder="Editable final name" value="' + attr(r.name || '') + '"></td>'
                    + '<td><input type="number" step="1" min="1" name="studded_diamond_pcs[]" class="form-control js-dia-pcs" value="' + (pcs > 0 ? Math.round(pcs) : '') + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="' + wt.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="' + rate.toFixed(2) + '"></td>'
                    + '<td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="' + total.toFixed(2) + '" readonly></td>'
                    + '<td><div class="diamond-row-actions"><button type="button" class="btn btn-sm btn-outline-primary js-merge-dia-row" title="Use the full selected weight as one finished piece"><i class="fe fe-minimize-2 me-1"></i>1 PCS</button><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" title="Remove row"><i class="fe fe-trash"></i></button></div></td>'
                    + '</tr>';
            }
            if (kind === 'stone') {
                return '<tr>'
                    + '<td><select name="stone_item_id[]" class="form-select js-stone-inventory-select">' + stoneItemOptions(r.item_id || '') + '</select></td>'
                    + '<td><input type="text" name="stone_type[]" class="form-control" value="' + type + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="' + pcs.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="' + wt.toFixed(3) + '"></td>'
                    + '<td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="' + rate.toFixed(2) + '"></td>'
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
            const puritySelect = receiveModal.querySelector('.js-purity-select');
            const selectedPurity = puritySelect && puritySelect.selectedOptions ? puritySelect.selectedOptions[0] : null;
            const purityPercent = num(selectedPurity ? selectedPurity.getAttribute('data-percent') : 0);
            const diaGm = diaCts * 0.2;
            const stoneGm = stoneCts * 0.2;
            const net = gross - (diaGm + stoneGm + otherGm);
            const pure = net * (purityPercent / 100);
            const labourRate = num((receiveModal.querySelector('.js-labour-rate') || {}).value);
            const wastagePercent = num((receiveModal.querySelector('.js-wastage-percent') || {}).value);
            const goldRate = num((receiveModal.querySelector('.js-gold-rate') || {}).value);
            const labourTotal = Math.max(net, 0) * labourRate;
            const goldTotal = Math.max(net, 0) * goldRate;
            const wastageWeight = Math.max(net, 0) * wastagePercent / 100;
            const pureWastageWeight = Math.max(pure, 0) * wastagePercent / 100;

            const netEl = receiveModal.querySelector('.js-net-weight');
            const pureEl = receiveModal.querySelector('.js-pure-weight');
            const labourTotalEl = receiveModal.querySelector('.js-labour-total');
            const goldTotalEl = receiveModal.querySelector('.js-gold-total');
            const wastageWeightTextEl = receiveModal.querySelector('.js-wastage-weight-text');
            const pureWastageWeightEl = receiveModal.querySelector('.js-pure-wastage-weight');

            if (netEl) netEl.value = net.toFixed(3);
            if (pureEl) pureEl.value = pure.toFixed(3);
            if (labourTotalEl) labourTotalEl.value = labourTotal.toFixed(2);
            if (goldTotalEl) goldTotalEl.value = goldTotal.toFixed(2);
            if (wastageWeightTextEl) wastageWeightTextEl.textContent = wastageWeight.toFixed(3);
            if (pureWastageWeightEl) pureWastageWeightEl.value = pureWastageWeight.toFixed(3);
        }

        function setSummaryLoading(loading) {
            if (summaryLoaderEl) summaryLoaderEl.classList.toggle('d-none', !loading);
            updateAssignSubmitState(loading);
            if (!loading) return;
            if (totalGoldEl) totalGoldEl.textContent = '...';
            if (pendingOrdersEl) pendingOrdersEl.textContent = '...';
            if (pendingGoldEl) pendingGoldEl.textContent = '...';
            if (orderDetailsEl) {
                orderDetailsEl.innerHTML = '<tr><td colspan="6" class="text-center text-primary"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading order details...</td></tr>';
            }
        }

        function resetKarigarSummary() {
            if (summaryLoaderEl) summaryLoaderEl.classList.add('d-none');
            if (totalGoldEl) totalGoldEl.textContent = '0.000';
            if (pendingOrdersEl) pendingOrdersEl.textContent = '0';
            if (pendingGoldEl) pendingGoldEl.textContent = '0.000';
            updateAssignSubmitState(false);
            if (orderDetailsEl) {
                orderDetailsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Select karigar to view details.</td></tr>';
            }
        }

        function updateAssignSubmitState(loading) {
            if (!assignSubmitBtn) return;
            const hasKarigar = !!(karigarSelect && karigarSelect.value);
            const hasCustomer = !!(customerSelect && customerSelect.value);
            const hasFollower = !!(followerSelect && followerSelect.value);
            const hasFollowupDue = !!(followupDueInput && followupDueInput.value);
            assignSubmitBtn.disabled = !!loading || !hasKarigar || !hasCustomer || !hasFollower || !hasFollowupDue;
        }

        function renderOrderDetails(orders) {
            if (!orderDetailsEl) return;
            if (!Array.isArray(orders) || orders.length === 0) {
                orderDetailsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No pending orders for this karigar.</td></tr>';
                return;
            }

            const rows = orders.map(function (row) {
                const orderNo = String(row.order_no || '-');
                const orderName = String(row.order_name || '-');
                const thumbnail = String(row.thumbnail_url || '');
                const status = String(row.status || '-');
                const dueDate = String(row.due_date || '-');
                const req = Number(row.required_gold_gm || 0).toFixed(3);
                return '<tr>'
                    + '<td>' + (thumbnail ? '<img src="' + attr(thumbnail) + '" alt="" style="height:34px;width:34px;object-fit:cover;border-radius:7px">' : '<i class="fe fe-image text-muted"></i>') + '</td>'
                    + '<td>' + attr(orderNo) + '</td>'
                    + '<td>' + attr(orderName) + '</td>'
                    + '<td>' + attr(status) + '</td>'
                    + '<td>' + attr(dueDate) + '</td>'
                    + '<td>' + req + '</td>'
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
                if (orderFromInput) orderFromInput.value = btn.getAttribute('data-order-from') || '';
                if (customerSelect) customerSelect.value = btn.getAttribute('data-customer-id') || '';
                if (followerSelect) {
                    followerSelect.value = btn.getAttribute('data-followup-assigned-to') || '';
                    if (window.jQuery) jQuery(followerSelect).trigger('change.select2');
                }
                if (followupDueInput) followupDueInput.value = btn.getAttribute('data-followup-due-at') || '<?= date('Y-m-d\\T11:00', strtotime('+1 day')) ?>';
                if (karigarSelect) karigarSelect.value = '';
                resetKarigarSummary();
            }

            if (btn.classList.contains('js-receive-btn')) {
                try { activeDiamondOptions = JSON.parse(btn.getAttribute('data-diamond-options') || '[]'); } catch (error) { activeDiamondOptions = []; }
                if (receiveForm && orderId) receiveForm.setAttribute('action', assignBase + '/' + orderId + '/receive');
                if (receiveOrderLabel) receiveOrderLabel.textContent = orderNo;
                if (receiveModal) {
                    if (receiveForm) receiveForm.reset();
                    const suggestedPurity = num(btn.getAttribute('data-order-purity'));
                    const puritySelect = receiveModal.querySelector('.js-purity-select');
                    if (puritySelect && suggestedPurity > 0) {
                        const matchingOption = Array.from(puritySelect.options).find(function (option) {
                            return Math.abs(num(option.getAttribute('data-percent')) - suggestedPurity) < 0.0005;
                        });
                        puritySelect.value = matchingOption ? matchingOption.value : '';
                        if (window.jQuery) window.jQuery(puritySelect).trigger('change.select2');
                    }
                    ensureSingleRow('.js-dia-body', 'dia');
                    ensureSingleRow('.js-stone-body', 'stone');
                    ensureSingleRow('.js-other-body', 'other');
                    initStoneInventorySelects();
                    recalcReceiveModal();
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
        if (customerSelect) {
            customerSelect.addEventListener('change', function () {
                updateAssignSubmitState(false);
            });
        }
        [followerSelect, followupDueInput].forEach(function (field) {
            if (field) field.addEventListener('change', function () { updateAssignSubmitState(false); });
        });

        if (receiveModal) {
            receiveModal.addEventListener('click', function (event) {
                const target = event.target instanceof Element ? event.target : null;
                if (!target) return;

                const addDia = target.closest('.js-add-dia-row');
                if (addDia) {
                    const body = receiveModal.querySelector('.js-dia-body');
                    if (body) body.insertAdjacentHTML('beforeend', createRowHtml('dia'));
                    refreshDiamondSelectors();
                    initStoneInventorySelects();
                    recalcReceiveModal();
                    return;
                }
                const addStone = target.closest('.js-add-stone-row');
                if (addStone) {
                    const body = receiveModal.querySelector('.js-stone-body');
                    if (body) body.insertAdjacentHTML('beforeend', createRowHtml('stone'));
                    initStoneInventorySelects();
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
                const mergeDiamond = target.closest('.js-merge-dia-row');
                if (mergeDiamond) {
                    const row = mergeDiamond.closest('tr');
                    const select = row ? row.querySelector('.js-diamond-balance-select') : null;
                    const selected = select && select.selectedOptions ? Array.from(select.selectedOptions) : [];
                    const availableCts = selected.reduce(function (sum, option) { return sum + num(option.getAttribute('data-available-cts')); }, 0);
                    const availablePcs = selected.reduce(function (sum, option) { return sum + num(option.getAttribute('data-available-pcs')); }, 0);
                    if (selected.length === 0 || availableCts <= 0 || availablePcs < 1) {
                        if (select) select.focus();
                        if (window.Swal) {
                            window.Swal.fire({
                                icon: 'error',
                                title: 'Cannot merge diamond',
                                text: 'Select a diamond line with at least one piece and available weight.'
                            });
                        }
                        return;
                    }

                    const pcs = row.querySelector('.js-dia-pcs');
                    const weight = row.querySelector('.js-dia-weight');
                    if (pcs) pcs.value = '1';
                    if (weight) weight.value = availableCts.toFixed(3);
                    row.classList.add('table-success');
                    recalcReceiveModal();
                    return;
                }
                const rm = target.closest('.js-remove-row');
                if (rm) {
                    const tr = rm.closest('tr');
                    const tbody = tr ? tr.parentElement : null;
                    if (tbody && tr && tbody.children.length > 1) {
                        tr.remove();
                        refreshDiamondSelectors();
                        recalcReceiveModal();
                    }
                }
            });

            receiveModal.addEventListener('shown.bs.modal', initStoneInventorySelects);
            receiveModal.addEventListener('input', function () {
                recalcReceiveModal();
            });
            receiveModal.addEventListener('change', function (event) {
                const diamondSelect = event.target instanceof Element ? event.target.closest('.js-diamond-balance-select') : null;
                const stoneSelect = event.target instanceof Element ? event.target.closest('.js-stone-inventory-select') : null;
                if (diamondSelect) {
                    updateDiamondSelection(diamondSelect);
                }
                if (stoneSelect) {
                    const row = stoneSelect.closest('tr');
                    const option = stoneSelect.selectedOptions ? stoneSelect.selectedOptions[0] : null;
                    const description = row ? row.querySelector('[name="stone_type[]"]') : null;
                    const rate = row ? row.querySelector('.js-stone-rate') : null;
                    if (option && stoneSelect.value) {
                        if (description) description.value = option.getAttribute('data-description') || option.textContent.trim();
                        if (rate) rate.value = num(option.getAttribute('data-rate')).toFixed(2);
                    }
                }
                recalcReceiveModal();
            });
            receiveModal.addEventListener('input', function (event) {
                const name = event.target instanceof Element ? event.target.closest('.js-dia-name') : null;
                if (name) name.dataset.edited = '1';
            });

            if (receiveForm) {
                let submitting = false;
                receiveModal.addEventListener('hide.bs.modal', function (event) {
                    if (submitting) event.preventDefault();
                });
                receiveForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    if (submitting) return;

                    const submitButton = receiveForm.querySelector('[type="submit"]');
                    const originalLabel = submitButton ? submitButton.innerHTML : '';
                    submitting = true;
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Saving...';
                    }

                    try {
                        const response = await fetch(receiveForm.action, {
                            method: 'POST',
                            body: new FormData(receiveForm),
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        let result;
                        try {
                            result = await response.json();
                        } catch (error) {
                            throw new Error('The server returned an unexpected response. Your receipt was not confirmed.');
                        }

                        if (result.csrf && result.csrf.name && result.csrf.hash) {
                            const csrfInput = Array.from(receiveForm.elements).find(function (field) {
                                return field.name === result.csrf.name;
                            });
                            if (csrfInput) csrfInput.value = result.csrf.hash;
                        }
                        if (!response.ok || result.status !== 'ok') {
                            throw new Error(result.message || 'Unable to save the finished jewellery receipt.');
                        }

                        if (window.Swal) {
                            await window.Swal.fire({ icon: 'success', title: 'Completed', text: result.message || 'Finished jewellery received.' });
                        }
                        submitting = false;
                        bootstrap.Modal.getOrCreateInstance(receiveModal).hide();
                        window.location.reload();
                    } catch (error) {
                        if (window.Swal) {
                            await window.Swal.fire({
                                icon: 'error',
                                title: 'Unable to complete receipt',
                                text: error instanceof Error ? error.message : 'Unable to save the finished jewellery receipt.'
                            });
                        }
                    } finally {
                        submitting = false;
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalLabel;
                        }
                    }
                });
            }
        }
    })();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
