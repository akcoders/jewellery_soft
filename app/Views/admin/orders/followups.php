<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $currentAdminId = (int) session('admin_id'); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Order Followups</h4>
</div>
<style>
    .followup-order-thumb { align-items: center; background: #f3f4f6; border: 1px solid #e2e5ea; border-radius: 8px; color: #9aa3af; display: inline-flex; height: 40px; justify-content: center; overflow: hidden; position: relative; width: 40px; }
    .followup-order-thumb img { height: 100%; inset: 0; object-fit: cover; position: absolute; width: 100%; }
    .followups-table { min-width: 1640px; table-layout: fixed; }
    .followups-table th, .followups-table td { vertical-align: middle; }
    .followups-table th:nth-child(1), .followups-table td:nth-child(1) { width: 60px; }
    .followups-table th:nth-child(2), .followups-table td:nth-child(2) { width: 105px; }
    .followups-table th:nth-child(3), .followups-table td:nth-child(3) { width: 180px; }
    .followups-table th:nth-child(4), .followups-table td:nth-child(4) { width: 130px; }
    .followups-table th:nth-child(5), .followups-table td:nth-child(5) { width: 155px; }
    .followups-table th:nth-child(6), .followups-table td:nth-child(6) { width: 130px; }
    .followups-table th:nth-child(7), .followups-table td:nth-child(7) { width: 110px; }
    .followups-table th:nth-child(8), .followups-table td:nth-child(8) { width: 170px; }
    .followups-table th:nth-child(9), .followups-table td:nth-child(9) { width: 150px; }
    .followups-table th:nth-child(10), .followups-table td:nth-child(10) { width: 110px; }
    .followups-table th:nth-child(11), .followups-table td:nth-child(11) { width: 160px; }
    .followups-table th:nth-child(12), .followups-table td:nth-child(12) { width: 280px; }
    .followups-table tbody td:nth-child(9) .badge { white-space: nowrap; }
    .followup-actions { display: flex; flex-wrap: nowrap; gap: 6px; }
    .followup-actions .btn { flex: 0 0 auto; white-space: nowrap; }
</style>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover table-bordered mb-0 followups-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Order No</th>
                        <th>Order Name</th>
                        <th>Karigar</th>
                        <th>Assigned Follower</th>
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
                            <td colspan="12" class="text-center text-muted">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach (($orders ?? []) as $order): ?>
                        <?php
                            $assignedFollowerId = (int) ($order['followup_assigned_to'] ?? 0);
                            $canTakeFollowup = $assignedFollowerId > 0 && $assignedFollowerId === $currentAdminId;
                            $takeFollowupTitle = $assignedFollowerId <= 0
                                ? 'Admin must assign an order follower first'
                                : ($canTakeFollowup ? 'Take Followup' : 'Only the assigned follower can take this follow-up');
                        ?>
                        <tr>
                            <td><span class="followup-order-thumb"><i class="fe fe-image"></i><?php if (! empty($order['thumbnail_url'])): ?><img src="<?= esc((string) $order['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php endif; ?></span></td>
                            <td>
                                <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>">
                                    <?= esc((string) $order['order_no']) ?>
                                </a>
                            </td>
                            <td><strong><?= esc((string) (($order['order_name'] ?? '') ?: '-')) ?></strong></td>
                            <td><?= esc((string) (($order['karigar_name'] ?? '') !== '' ? $order['karigar_name'] : 'Not Assigned')) ?></td>
                            <td><strong><?= esc((string) (($order['follower_name'] ?? '') ?: 'Not Assigned')) ?></strong></td>
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
                                <div class="followup-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary js-take-followup-btn"
                                        data-order-id="<?= esc((string) $order['id']) ?>"
                                        data-order-no="<?= esc((string) $order['order_no']) ?>"
                                        data-order-status="<?= esc((string) $order['status']) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#takeFollowupModal"
                                        title="<?= esc($takeFollowupTitle, 'attr') ?>"
                                        <?= ! $canTakeFollowup ? 'disabled' : '' ?>
                                    >
                                        <i class="fe fe-edit-3"></i> Take Followup
                                    </button>
                                    <?php if (admin_can('orders.assign')): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary js-change-follower-btn"
                                            data-order-id="<?= esc((string) $order['id']) ?>"
                                            data-order-no="<?= esc((string) $order['order_no']) ?>"
                                            data-follower-id="<?= esc((string) $assignedFollowerId) ?>"
                                            data-followup-due-at="<?= ! empty($order['followup_due_at']) ? esc(date('Y-m-d\\TH:i', strtotime((string) $order['followup_due_at'])), 'attr') : '' ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#changeFollowerModal"
                                            title="Change order follower"
                                        >
                                            <i class="fe fe-user-check"></i> Change Follower
                                        </button>
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

<?php if (admin_can('orders.assign')): ?>
<div class="modal fade" id="changeFollowerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Follower - <span id="change-follower-order-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="change-follower-form" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Follower <span class="text-danger">*</span></label>
                        <select class="form-select" id="change-follower-select" name="followup_assigned_to" required>
                            <option value="">Select staff follower</option>
                            <?php foreach (($staffFollowers ?? []) as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Next Follow-up Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="change-follower-due-at" name="followup_due_at" required>
                        <div class="form-text">The pending follow-up will immediately move to the selected follower.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Follower</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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
                            <label class="form-label mb-1">Next Followup Date &amp; Time</label>
                            <input type="datetime-local" name="next_followup_date" class="form-control">
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
        const followerForm = document.getElementById('change-follower-form');
        const followerOrderLabel = document.getElementById('change-follower-order-label');
        const followerSelect = document.getElementById('change-follower-select');
        const followerDueAt = document.getElementById('change-follower-due-at');
        const base = '<?= site_url('admin/orders') ?>';
        const defaultDueAt = '<?= date('Y-m-d\\T11:00', strtotime('+1 day')) ?>';

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const btn = target.closest('.js-take-followup-btn');
            if (btn) {
                const orderId = btn.getAttribute('data-order-id');
                const orderNo = btn.getAttribute('data-order-no') || '';
                const orderStatus = btn.getAttribute('data-order-status') || '';

                if (form && orderId) form.setAttribute('action', base + '/' + orderId + '/followups');
                if (orderLabel) orderLabel.textContent = orderNo;
                if (stageSelect && orderStatus) stageSelect.value = orderStatus;
                return;
            }

            const followerBtn = target.closest('.js-change-follower-btn');
            if (!followerBtn) return;
            const followerOrderId = followerBtn.getAttribute('data-order-id');
            if (followerForm && followerOrderId) followerForm.setAttribute('action', base + '/' + followerOrderId + '/follower');
            if (followerOrderLabel) followerOrderLabel.textContent = followerBtn.getAttribute('data-order-no') || '';
            if (followerSelect) followerSelect.value = followerBtn.getAttribute('data-follower-id') || '';
            if (followerDueAt) followerDueAt.value = followerBtn.getAttribute('data-followup-due-at') || defaultDueAt;
        });
    })();
</script>
<?= $this->endSection() ?>
