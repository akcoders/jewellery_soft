<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$label = static fn(string $type): string => match ($type) {
    'customer_create' => 'New Customer',
    'karigar_create' => 'New Karigar',
    'followup' => 'Order Follow-up',
    'delivery_challan' => 'Delivery Challan',
    default => 'Material Issuement',
};
$icon = static fn(string $type): string => match ($type) {
    'customer_create' => 'fe-users',
    'karigar_create' => 'fe-user-check',
    'followup' => 'fe-message-circle',
    'delivery_challan' => 'fe-file-text',
    default => 'fe-share-2',
};
?>
<style>
.approval-hero{background:linear-gradient(125deg,#31152d,#651b46 56%,#b98a2e);border-radius:20px;color:#fff;padding:26px 30px;box-shadow:0 18px 44px rgba(65,18,55,.2);position:relative;overflow:hidden}.approval-hero:after{content:"";position:absolute;width:220px;height:220px;border:1px solid rgba(255,255,255,.18);border-radius:50%;right:-55px;top:-105px}.approval-hero h2{color:#fff;margin:0}.approval-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07);overflow:hidden}.approval-card.pending{border-left:5px solid #c69a3a}.approval-head{background:linear-gradient(135deg,#fffaf0,#fff);border-bottom:1px solid #efe4d4;padding:18px 20px}.approval-mark{width:46px;height:46px;border-radius:14px;background:#f5e8b9;color:#6a4820;display:inline-flex;align-items:center;justify-content:center;font-size:20px}.approval-material{background:#faf7fa;border:1px solid #eee4ec;border-radius:13px;padding:13px;height:100%}.approval-material strong{color:#4a1739}.approval-photo{max-height:300px;width:100%;object-fit:contain;background:#22151f;border-radius:13px}.status-pill{border-radius:999px;font-size:11px;font-weight:800;padding:7px 11px;text-transform:uppercase;letter-spacing:.04em}.status-pending{background:#fff2c8;color:#77540a}.status-approved{background:#dcf7e8;color:#176a3c}.status-rejected{background:#fde5e7;color:#9d1d2a}.approval-history-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07)}.approval-history-card .card-header{background:#fff;border-bottom:1px solid #efe4ed;padding:18px 20px}.approval-view-modal .modal-content{border:0;border-radius:18px;overflow:hidden}.approval-view-modal .modal-header{background:linear-gradient(135deg,#32142d,#76224f);color:#fff}.approval-view-modal .btn-close{filter:invert(1)}
</style>
<div class="content container-fluid">
    <div class="approval-hero mb-4 d-flex align-items-center justify-content-between">
        <div><span class="text-uppercase small opacity-75">Admin control desk</span><h2>Mobile Approvals</h2><p class="mb-0 opacity-75">Review follow-up, delivery challan, issuement and master requests with complete details.</p></div>
        <div class="text-center"><div class="fs-2 fw-bold"><?= (int) $pendingCount ?></div><small>Pending</small></div>
    </div>
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
    <ul class="nav nav-pills gap-2 mb-4" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="pending-approvals-tab" data-bs-toggle="pill" data-bs-target="#pending-approvals-pane" type="button" role="tab" aria-controls="pending-approvals-pane" aria-selected="true"><i class="fe fe-clock me-1"></i>Pending <span class="badge bg-warning text-dark ms-1"><?= (int) $pendingCount ?></span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="approval-history-tab" data-bs-toggle="pill" data-bs-target="#approval-history-pane" type="button" role="tab" aria-controls="approval-history-pane" aria-selected="false"><i class="fe fe-list me-1"></i>Approval History <span class="badge bg-light text-dark ms-1"><?= count($historyItems ?? []) ?></span></button></li>
    </ul>
    <div class="tab-content">
    <div class="tab-pane fade show active" id="pending-approvals-pane" role="tabpanel" aria-labelledby="pending-approvals-tab" tabindex="0">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div><h4 class="mb-1">Pending approvals</h4><p class="text-muted mb-0">Only requests waiting for a decision are shown here.</p></div>
        <span class="status-pill status-pending"><?= (int) $pendingCount ?> pending</span>
    </div>
    <?php if ($items === []): ?>
        <div class="card approval-card mb-4"><div class="card-body text-center py-5"><i class="fe fe-check-circle fs-1 text-success"></i><h4 class="mt-3">No pending approval requests</h4><p class="text-muted mb-0">Reviewed requests are available in Approval History below.</p></div></div>
    <?php endif; ?>
    <?php foreach ($items as $row): $payload = is_array($row['payload'] ?? null) ? $row['payload'] : []; $type = (string) ($row['request_type'] ?? ''); $status = (string) ($row['status'] ?? 'pending'); ?>
        <div class="card approval-card <?= esc($status) ?> mb-4">
            <div class="approval-head d-flex gap-3 align-items-center">
                <span class="approval-mark"><i class="fe <?= esc($icon($type)) ?>"></i></span>
                <div class="flex-grow-1"><div class="small text-muted">REQUEST #<?= (int) $row['id'] ?> · <?= esc(date('d M Y, h:i A', strtotime((string) $row['created_at']))) ?></div><h4 class="mb-1"><?= esc($label($type)) ?></h4><div><?= esc($row['summary'] ?? '') ?></div></div>
                <div class="text-end"><span class="status-pill status-<?= esc($status) ?>"><?= esc($status === 'rejected' ? 'disapproved' : $status) ?></span><small class="d-block text-muted mt-2">by <?= esc($row['requested_by_name'] ?? '-') ?></small></div>
            </div>
            <div class="card-body p-4">
                <?= view('admin/mobile_approvals/_request_details', ['row' => $row]) ?>
                <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $row['id'] . '/review') ?>" class="mt-4 p-3 rounded bg-light">
                    <?= csrf_field() ?><div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">Admin note</label><input class="form-control" name="note" maxlength="500" placeholder="Optional approval / rejection note"></div><div class="col-md-auto d-flex gap-2"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Reject this request?')"><i class="fe fe-x me-1"></i>Reject</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve and process this request?')"><i class="fe fe-check me-1"></i>Approve & Process</button></div></div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="tab-pane fade" id="approval-history-pane" role="tabpanel" aria-labelledby="approval-history-tab" tabindex="0">
    <div class="card approval-history-card">
        <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div><h4 class="mb-1">Approval History</h4><p class="text-muted mb-0">Search reviewed requests and open View for complete details.</p></div>
            <div class="d-flex gap-2"><span class="status-pill status-approved"><?= (int) ($approvedCount ?? 0) ?> approved</span><span class="status-pill status-rejected"><?= (int) ($rejectedCount ?? 0) ?> disapproved</span></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table datatable table-bordered table-hover align-middle mb-0" data-dt-page-length="10">
                    <thead><tr><th>Request</th><th>Type</th><th>Summary</th><th>Requested By</th><th>Status</th><th>Reviewed By</th><th>Reviewed At</th><th>Reference</th><th data-dt-ordering="false">Action</th></tr></thead>
                    <tbody>
                    <?php foreach (($historyItems ?? []) as $history): $historyType = (string) ($history['request_type'] ?? ''); $historyStatus = (string) ($history['status'] ?? ''); ?>
                        <tr>
                            <td data-order="<?= (int) $history['id'] ?>">#<?= (int) $history['id'] ?></td>
                            <td><?= esc($label($historyType)) ?></td>
                            <td><?= esc((string) ($history['summary'] ?? '-')) ?></td>
                            <td><?= esc((string) ($history['requested_by_name'] ?? '-')) ?></td>
                            <td><span class="status-pill status-<?= esc($historyStatus) ?>"><?= esc($historyStatus === 'rejected' ? 'disapproved' : $historyStatus) ?></span></td>
                            <td><?= esc((string) ($history['reviewed_by_name'] ?? '-')) ?></td>
                            <td data-order="<?= esc((string) ($history['reviewed_at'] ?? '')) ?>"><?= ! empty($history['reviewed_at']) ? esc(date('d M Y, h:i A', strtotime((string) $history['reviewed_at']))) : '-' ?></td>
                            <td><?= esc((string) (($history['result_reference'] ?? '') ?: '-')) ?></td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#approvalHistoryModal<?= (int) $history['id'] ?>"><i class="fe fe-eye me-1"></i>View</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
    </div>
</div>

<?php foreach (($historyItems ?? []) as $history): $historyType = (string) ($history['request_type'] ?? ''); $historyStatus = (string) ($history['status'] ?? ''); ?>
<div class="modal fade approval-view-modal" id="approvalHistoryModal<?= (int) $history['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><small class="opacity-75">REQUEST #<?= (int) $history['id'] ?></small><h5 class="modal-title text-white"><?= esc($label($historyType)) ?></h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><strong><?= esc((string) ($history['summary'] ?? '-')) ?></strong><div class="small text-muted">Requested by <?= esc((string) ($history['requested_by_name'] ?? '-')) ?> · <?= ! empty($history['created_at']) ? esc(date('d M Y, h:i A', strtotime((string) $history['created_at']))) : '-' ?></div></div><span class="status-pill status-<?= esc($historyStatus) ?>"><?= esc($historyStatus === 'rejected' ? 'disapproved' : $historyStatus) ?></span></div>
                <?= view('admin/mobile_approvals/_request_details', ['row' => $history]) ?>
                <?php if (! empty($history['can_reapprove'])): ?>
                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $history['id'] . '/review') ?>" class="mt-3 p-3 rounded bg-light">
                        <?= csrf_field() ?><div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">Re-approval note</label><input class="form-control" name="note" maxlength="500" placeholder="Why this rejected issuement is being approved"></div><div class="col-md-auto"><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Re-approve and process this rejected issuement?')"><i class="fe fe-refresh-cw me-1"></i>Re-approve &amp; Process</button></div></div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.getElementById('approval-history-tab')?.addEventListener('shown.bs.tab', function () {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') return;
    jQuery('#approval-history-pane table.datatable').each(function () {
        if (jQuery.fn.DataTable.isDataTable(this)) jQuery(this).DataTable().columns.adjust();
    });
});
</script>
<?= $this->endSection() ?>
