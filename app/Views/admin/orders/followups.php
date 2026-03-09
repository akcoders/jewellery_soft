<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Order Followups</h4>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Karigar</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Next Followup</th>
                        <th>Followup State</th>
                        <th>Days Left</th>
                        <th>Last Taken On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($orders ?? []) === []): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach (($orders ?? []) as $order): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>">
                                    <?= esc((string) $order['order_no']) ?>
                                </a>
                            </td>
                            <td><?= esc((string) ($order['customer_name'] ?? '-')) ?></td>
                            <td><?= esc((string) (($order['karigar_name'] ?? '') !== '' ? $order['karigar_name'] : 'Not Assigned')) ?></td>
                            <td><?= esc((string) ($order['status'] ?? '-')) ?></td>
                            <td><?= esc((string) (($order['due_date'] ?? '') !== '' ? $order['due_date'] : '-')) ?></td>
                            <td><?= esc((string) (($order['next_followup_date'] ?? '') !== '' ? $order['next_followup_date'] : '-')) ?></td>
                            <td>
                                <span class="badge bg-<?= esc((string) ($order['followup_status_class'] ?? 'warning')) ?>-light text-<?= esc((string) ($order['followup_status_class'] ?? 'warning')) ?>">
                                    <?= esc((string) ($order['followup_status_label'] ?? 'Followup Pending')) ?>
                                </span>
                            </td>
                            <td><?= esc((string) ($order['followup_days_text'] ?? '-')) ?></td>
                            <td><?= esc((string) (($order['last_followup_on'] ?? '') !== '' ? $order['last_followup_on'] : '-')) ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary js-take-followup-btn"
                                    data-order-id="<?= esc((string) $order['id']) ?>"
                                    data-order-no="<?= esc((string) $order['order_no']) ?>"
                                    data-order-status="<?= esc((string) $order['status']) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#takeFollowupModal"
                                    <?= (string) ($order['status'] ?? '') === 'Cancelled' ? 'disabled' : '' ?>
                                >
                                    <i class="fe fe-edit-3"></i> Take Followup
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="takeFollowupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Take Followup - <span id="followup-order-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="take-followup-form" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= esc(current_url()) ?>">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label mb-1">Stage</label>
                            <select name="stage" id="followup-stage" class="form-select" required>
                                <option value="">Select Stage</option>
                                <?php foreach (($statuses ?? []) as $status): ?>
                                    <option value="<?= esc((string) $status) ?>"><?= esc((string) $status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label mb-1">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Followup description" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">Next Followup Date</label>
                            <input type="date" name="next_followup_date" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1">Image</label>
                            <input type="file" name="followup_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Followup Taken By</label>
                            <input type="text" class="form-control" value="<?= esc((string) (session('admin_name') ?: 'Admin')) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Followup Taken On</label>
                            <input type="text" class="form-control" value="<?= esc(date('Y-m-d H:i:s')) ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Followup</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const form = document.getElementById('take-followup-form');
        const orderLabel = document.getElementById('followup-order-label');
        const stageSelect = document.getElementById('followup-stage');
        const base = '<?= site_url('admin/orders') ?>';

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const btn = target.closest('.js-take-followup-btn');
            if (!btn) return;

            const orderId = btn.getAttribute('data-order-id');
            const orderNo = btn.getAttribute('data-order-no') || '';
            const orderStatus = btn.getAttribute('data-order-status') || '';

            if (form && orderId) {
                form.setAttribute('action', base + '/' + orderId + '/followups');
            }
            if (orderLabel) {
                orderLabel.textContent = orderNo;
            }
            if (stageSelect && orderStatus) {
                stageSelect.value = orderStatus;
            }
        });
    })();
</script>
<?= $this->endSection() ?>
