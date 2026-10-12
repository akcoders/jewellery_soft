<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$items = is_array($items ?? null) ? $items : [];
$staff = is_array($staff ?? null) ? $staff : [];
$typeLabel = static fn(string $type): string => match ($type) {
    'order_delay' => 'Order Delay',
    'gold_requirement' => 'Gold Requirement',
    'diamond_requirement' => 'Diamond Requirement',
    'follower_change' => 'Follower Change',
    default => ucwords(str_replace('_', ' ', $type)),
};
$typeIcon = static fn(string $type): string => match ($type) {
    'order_delay' => 'fe-calendar',
    'gold_requirement' => 'fe-circle',
    'diamond_requirement' => 'fe-package',
    'follower_change' => 'fe-users',
    default => 'fe-clipboard',
};
$defaultDueAt = date('Y-m-d\TH:i', strtotime('+1 day 11:00'));
?>
<style>
.request-hero{background:linear-gradient(125deg,#32142d,#76224f 58%,#c19636);border-radius:20px;color:#fff;padding:26px 30px;box-shadow:0 18px 44px rgba(65,18,55,.2)}
.request-hero h2{color:#fff;margin:0}.request-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07);overflow:hidden}.request-card.pending{border-left:5px solid #c69a3a}
.request-head{background:linear-gradient(135deg,#fffaf0,#fff);border-bottom:1px solid #efe4d4;padding:18px 20px}.request-mark{align-items:center;background:#f5e8b9;border-radius:14px;color:#6a4820;display:inline-flex;font-size:20px;height:46px;justify-content:center;width:46px}
.request-detail{background:#faf7fa;border:1px solid #eee4ec;border-radius:13px;height:100%;padding:13px}.request-detail strong{color:#4a1739}.request-details-text{line-height:1.6;overflow-wrap:anywhere;white-space:pre-wrap}
.status-pill{border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;padding:7px 11px;text-transform:uppercase}.status-pending{background:#fff2c8;color:#77540a}.status-approved,.status-completed{background:#dcf7e8;color:#176a3c}.status-rejected{background:#fde5e7;color:#9d1d2a}
</style>
<div class="content container-fluid">
    <div class="request-hero mb-4 d-flex align-items-center justify-content-between gap-3">
        <div><span class="text-uppercase small opacity-75">Admin approval desk</span><h2>Order Requests</h2><p class="mb-0 opacity-75">Review delivery delays, material requirements and follower changes raised from the app.</p></div>
        <div class="text-center"><div class="fs-2 fw-bold"><?= (int) ($pendingCount ?? 0) ?></div><small>Pending</small></div>
    </div>
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
    <?php if ($items === []): ?>
        <div class="card request-card"><div class="card-body text-center py-5"><i class="fe fe-check-circle fs-1 text-success"></i><h4 class="mt-3">No order requests</h4><p class="text-muted mb-0">Requests raised from assigned followers will appear here.</p></div></div>
    <?php endif; ?>
    <?php foreach ($items as $row):
        $type = (string) ($row['request_type'] ?? '');
        $status = (string) ($row['status'] ?? 'pending');
        $isMaterial = in_array($type, ['gold_requirement', 'diamond_requirement'], true);
        $needsAssignee = $isMaterial || $type === 'follower_change';
        $currentDueTimestamp = strtotime((string) ($row['due_date'] ?? '')) ?: 0;
        $minimumApprovedDueDate = date('Y-m-d', max(strtotime('+1 day'), $currentDueTimestamp > 0 ? strtotime('+1 day', $currentDueTimestamp) : 0));
        $requestedDueDate = substr((string) ($row['requested_due_at'] ?? ''), 0, 10);
        $defaultApprovedDueDate = $requestedDueDate >= $minimumApprovedDueDate ? $requestedDueDate : $minimumApprovedDueDate;
    ?>
        <div class="card request-card <?= esc($status) ?> mb-4">
            <div class="request-head d-flex gap-3 align-items-center">
                <span class="request-mark"><i class="fe <?= esc($typeIcon($type)) ?>"></i></span>
                <div class="flex-grow-1">
                    <div class="small text-muted">REQUEST #<?= (int) ($row['id'] ?? 0) ?> · <?= ! empty($row['created_at']) ? esc(date('d M Y, h:i A', strtotime((string) $row['created_at']))) : '-' ?></div>
                    <h4 class="mb-1"><?= esc($typeLabel($type)) ?></h4>
                    <a href="<?= site_url('admin/orders/' . (int) ($row['order_id'] ?? 0)) ?>" class="fw-semibold"><?= esc((string) ($row['order_no'] ?? ('Order #' . (int) ($row['order_id'] ?? 0)))) ?></a>
                    <span class="text-muted"> · <?= esc((string) ($row['order_status'] ?? '-')) ?></span>
                </div>
                <div class="text-end"><span class="status-pill status-<?= esc($status) ?>"><?= esc($status) ?></span><small class="d-block text-muted mt-2">by <?= esc((string) (($row['requester_name'] ?? '') ?: '-')) ?></small></div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-lg-8"><div class="request-detail"><small class="text-muted">Request details</small><div class="request-details-text fw-semibold"><?= esc((string) (($row['details'] ?? '') ?: '-')) ?></div></div></div>
                    <div class="col-lg-4"><div class="request-detail"><small class="text-muted">Current delivery date</small><strong class="d-block"><?= ! empty($row['due_date']) ? esc(date('d M Y', strtotime((string) $row['due_date']))) : '-' ?></strong></div></div>
                    <?php if ($type === 'order_delay'): ?><div class="col-md-4"><div class="request-detail"><small class="text-muted">Requested delivery date</small><strong class="d-block"><?= ! empty($row['requested_due_at']) ? esc(date('d M Y', strtotime((string) $row['requested_due_at']))) : '-' ?></strong></div></div><?php endif; ?>
                    <?php if ($type === 'order_delay' && ! empty($row['approved_due_at'])): ?><div class="col-md-4"><div class="request-detail"><small class="text-muted">Updated order due date</small><strong class="d-block"><?= esc(date('d M Y', strtotime((string) $row['approved_due_at']))) ?></strong></div></div><?php endif; ?>
                    <?php if ($type === 'gold_requirement'): ?><div class="col-md-4"><div class="request-detail"><small class="text-muted">Gold required</small><strong class="d-block"><?= number_format((float) ($row['gold_quantity_gm'] ?? 0), 3) ?> gm</strong></div></div><?php endif; ?>
                    <?php if (! empty($row['assignee_name'])): ?><div class="col-md-4"><div class="request-detail"><small class="text-muted">Assigned to</small><strong class="d-block"><?= esc((string) $row['assignee_name']) ?></strong></div></div><?php endif; ?>
                    <?php if (! empty($row['voucher_no'])): ?><div class="col-md-4"><div class="request-detail"><small class="text-muted">Voucher</small><strong class="d-block"><?= esc((string) $row['voucher_no']) ?></strong></div></div><?php endif; ?>
                </div>

                <?php if ($status === 'pending'): ?>
                    <form method="post" action="<?= site_url('admin/order-requests/' . (int) $row['id'] . '/review') ?>" class="mt-4 p-3 rounded bg-light">
                        <?= csrf_field() ?>
                        <div class="row g-3 align-items-end">
                            <?php if ($needsAssignee): ?>
                                <div class="col-md-4"><label class="form-label"><?= $type === 'follower_change' ? 'New follower' : 'Assign task to' ?></label><select class="form-select" name="assigned_to"><option value="">Select staff</option><?php foreach ($staff as $person): ?><option value="<?= (int) ($person['id'] ?? 0) ?>"><?= esc((string) ($person['name'] ?? '-')) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
                            <?php endif; ?>
                            <?php if ($type === 'order_delay'): ?><div class="col-md-4"><label class="form-label">Updated order due date *</label><input type="date" class="form-control" name="approved_due_at" min="<?= esc($minimumApprovedDueDate, 'attr') ?>" value="<?= esc($defaultApprovedDueDate, 'attr') ?>"></div><?php endif; ?>
                            <?php if ($isMaterial): ?><div class="col-md-4"><label class="form-label">Task deadline</label><input type="datetime-local" class="form-control" name="task_due_at" value="<?= esc($defaultDueAt, 'attr') ?>"></div><?php endif; ?>
                            <?php if ($type === 'follower_change'): ?><div class="col-md-4"><label class="form-label">Next follow-up</label><input type="datetime-local" class="form-control" name="followup_due_at" value="<?= esc($defaultDueAt, 'attr') ?>"></div><?php endif; ?>
                            <div class="<?= ($needsAssignee || $type === 'order_delay') ? 'col-12' : 'col-md' ?>"><label class="form-label"><?= $type === 'order_delay' ? 'Admin note *' : 'Decision note' ?></label><input class="form-control" name="review_note" maxlength="500" placeholder="<?= $type === 'order_delay' ? 'Reason for the final updated due date' : 'Rejection reason is required when rejecting' ?>"></div>
                            <div class="col-md-auto d-flex gap-2"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Reject this order request?')"><i class="fe fe-x me-1"></i>Reject</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve and process this order request?')"><i class="fe fe-check me-1"></i>Approve</button></div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border mt-3 mb-0"><strong><?= esc(ucfirst($status)) ?><?= ! empty($row['reviewed_by_name']) ? ' by ' . esc((string) $row['reviewed_by_name']) : '' ?></strong><?= ! empty($row['review_note']) ? ' · ' . esc((string) $row['review_note']) : '' ?><?= ! empty($row['reviewed_at']) ? ' · ' . esc(date('d M Y, h:i A', strtotime((string) $row['reviewed_at']))) : '' ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
