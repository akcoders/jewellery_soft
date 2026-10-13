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
.approval-hero{background:linear-gradient(125deg,#31152d,#651b46 56%,#b98a2e);border-radius:20px;color:#fff;padding:26px 30px;box-shadow:0 18px 44px rgba(65,18,55,.2);position:relative;overflow:hidden}.approval-hero:after{content:"";position:absolute;width:220px;height:220px;border:1px solid rgba(255,255,255,.18);border-radius:50%;right:-55px;top:-105px}.approval-hero h2{color:#fff;margin:0}.approval-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07);overflow:hidden}.approval-card.pending{border-left:5px solid #c69a3a}.approval-head{background:linear-gradient(135deg,#fffaf0,#fff);border-bottom:1px solid #efe4d4;padding:18px 20px}.approval-mark{width:46px;height:46px;border-radius:14px;background:#f5e8b9;color:#6a4820;display:inline-flex;align-items:center;justify-content:center;font-size:20px}.approval-material{background:#faf7fa;border:1px solid #eee4ec;border-radius:13px;padding:13px;height:100%}.approval-material strong{color:#4a1739}.approval-photo{max-height:300px;width:100%;object-fit:contain;background:#22151f;border-radius:13px}.status-pill{border-radius:999px;font-size:11px;font-weight:800;padding:7px 11px;text-transform:uppercase;letter-spacing:.04em;display:inline-flex;align-items:center;gap:5px}.status-pending{background:#fff2c8;color:#77540a}.status-approved{background:#dcf7e8;color:#176a3c}.status-rejected{background:#fde5e7;color:#9d1d2a}.approval-history-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07)}.approval-history-card .card-header{background:#fff;border-bottom:1px solid #efe4ed;padding:18px 20px}.approval-view-modal .modal-content{border:0;border-radius:18px;overflow:hidden}.approval-view-modal .modal-header{background:linear-gradient(135deg,#32142d,#76224f);color:#fff}.approval-view-modal .btn-close{filter:invert(1)}
.approval-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.approval-metric{background:#fff;border:1px solid #eadfe8;border-radius:16px;padding:15px 17px}.approval-metric small{display:block;color:#83717e}.approval-metric strong{font-size:1.45rem;color:#421633}.followup-group{border:1px solid #eadce6;border-radius:20px;box-shadow:0 12px 34px rgba(61,25,51,.08);overflow:hidden}.followup-group-head{padding:20px 22px;background:linear-gradient(135deg,#3d1736,#71224e);color:#fff}.followup-group-head h4{color:#fff}.followup-timeline{position:relative;padding:22px}.followup-timeline:before{content:"";position:absolute;left:42px;top:28px;bottom:28px;width:2px;background:#e8dbe4}.followup-step{position:relative;padding-left:54px;margin-bottom:18px}.followup-step:last-child{margin-bottom:0}.followup-dot{position:absolute;left:9px;top:16px;width:23px;height:23px;border:4px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #d8c7d2;background:#999;z-index:1}.followup-dot.approved{background:#28a869;box-shadow:0 0 0 2px #bce8d1}.followup-dot.pending{background:#d49d25;box-shadow:0 0 0 2px #f1d99a}.followup-dot.rejected{background:#d74b58;box-shadow:0 0 0 2px #f1b9bf}.followup-step-card{border:1px solid #eadfe7;border-radius:16px;background:#fff;padding:17px 18px}.followup-step.pending .followup-step-card{border-color:#e5c26c;background:#fffdf7}.followup-step-top{display:flex;gap:12px;align-items:flex-start;justify-content:space-between}.followup-description{font-size:1rem;color:#35112d;white-space:pre-wrap}.followup-meta{font-size:.78rem;color:#887681}.followup-image{width:84px;height:72px;object-fit:cover;border-radius:11px;border:1px solid #e8dce4}.followup-review{margin-top:13px;padding-top:13px;border-top:1px dashed #ddced8}.followup-review form{background:#faf7f9;border-radius:13px;padding:13px}.review-first{background:#fff0bf;color:#715008;border-radius:999px;font-size:10px;font-weight:800;padding:5px 8px;text-transform:uppercase;letter-spacing:.04em}.followup-context{background:#f7f2f6;border:1px solid #eadfe7;border-radius:12px;padding:11px 13px}.approval-section-label{font-size:.75rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#7e6978}
@media(max-width:767.98px){.approval-hero{padding:20px}.approval-hero p{font-size:.84rem}.approval-metrics{grid-template-columns:1fr}.followup-group-head,.followup-timeline{padding:17px}.followup-timeline:before{left:28px}.followup-step{padding-left:37px}.followup-dot{left:0}.followup-step-top{display:block}.followup-step-top .status-pill{margin-top:9px}.followup-review .btn{width:100%}.followup-image{width:100%;height:160px;margin-top:10px}}
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
        <div><h4 class="mb-1">Pending approvals</h4><p class="text-muted mb-0">Follow-ups are grouped by order and displayed in the sequence they happened.</p></div>
        <span class="status-pill status-pending"><?= (int) $pendingCount ?> pending</span>
    </div>
    <div class="approval-metrics mb-4">
        <div class="approval-metric"><small>Orders awaiting follow-up review</small><strong><?= count($followupGroups ?? []) ?></strong></div>
        <div class="approval-metric"><small>Pending follow-up updates</small><strong><?= (int) ($pendingFollowupCount ?? 0) ?></strong></div>
        <div class="approval-metric"><small>Other mobile requests</small><strong><?= count($otherItems ?? []) ?></strong></div>
    </div>
    <?php if ($items === []): ?>
        <div class="card approval-card mb-4"><div class="card-body text-center py-5"><i class="fe fe-check-circle fs-1 text-success"></i><h4 class="mt-3">No pending approval requests</h4><p class="text-muted mb-0">Reviewed requests are available in Approval History below.</p></div></div>
    <?php endif; ?>

    <?php foreach (($followupGroups ?? []) as $group): ?>
        <?php
        $pendingByFollowup = [];
        foreach (($group['requests'] ?? []) as $pendingRequest) {
            $pendingPayload = is_array($pendingRequest['payload'] ?? null) ? $pendingRequest['payload'] : [];
            $followupId = (string) ($pendingRequest['subject_table'] ?? '') === 'order_followups'
                ? (int) ($pendingRequest['subject_id'] ?? 0)
                : (int) ($pendingPayload['followup_id'] ?? 0);
            if ($followupId > 0) $pendingByFollowup[$followupId] = $pendingRequest;
        }
        $chain = is_array($group['chain'] ?? null) ? $group['chain'] : [];
        ?>
        <section class="followup-group mb-4">
            <div class="followup-group-head d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><div class="small opacity-75 text-uppercase">Follow-up approval chain</div><h4 class="mb-1"><?= esc((string) ($group['order_no'] ?? 'Order')) ?></h4><div class="small opacity-75"><?= count($chain) ?> total update<?= count($chain) === 1 ? '' : 's' ?> · <?= count($group['requests'] ?? []) ?> waiting for review</div></div>
                <?php if ((int) ($group['order_id'] ?? 0) > 0): ?><a class="btn btn-sm btn-light" href="<?= site_url('admin/orders/' . (int) $group['order_id']) ?>"><i class="fe fe-external-link me-1"></i>Open order</a><?php endif; ?>
            </div>
            <div class="followup-timeline">
                <?php foreach ($chain as $position => $followup): ?>
                    <?php
                    $followupStatus = strtolower((string) ($followup['approval_status'] ?? 'approved'));
                    $followupStatus = in_array($followupStatus, ['pending', 'approved', 'rejected'], true) ? $followupStatus : 'approved';
                    $pendingRequest = $pendingByFollowup[(int) ($followup['id'] ?? 0)] ?? null;
                    $isOldestPending = is_array($pendingRequest) && ! empty($pendingRequest['followup_is_oldest_pending']);
                    $imageUrl = (string) (($followup['image_url'] ?? '') ?: (is_array($pendingRequest) ? ($pendingRequest['attachment_url'] ?? '') : ''));
                    ?>
                    <article class="followup-step <?= esc($followupStatus) ?>">
                        <span class="followup-dot <?= esc($followupStatus) ?>" aria-hidden="true"></span>
                        <div class="followup-step-card">
                            <div class="followup-step-top">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><span class="approval-section-label">Update <?= (int) $position + 1 ?></span><?php if ($isOldestPending): ?><span class="review-first"><i class="fe fe-arrow-right me-1"></i>Review first</span><?php endif; ?></div>
                                    <div class="fw-semibold text-dark mb-1"><?= esc((string) (($followup['stage'] ?? '') ?: 'Follow-up')) ?></div>
                                    <div class="followup-description"><?= esc((string) (($followup['description'] ?? '') ?: 'No update text recorded.')) ?></div>
                                    <div class="followup-meta mt-2"><?= ! empty($followup['followup_taken_on']) ? esc(date('d M Y, h:i A', strtotime((string) $followup['followup_taken_on']))) : '-' ?> · <?= esc((string) (($followup['followup_taken_by_name'] ?? '') ?: 'Staff')) ?><?php if (! empty($followup['next_followup_date'])): ?> · Next: <?= esc(date('d M Y, h:i A', strtotime((string) $followup['next_followup_date']))) ?><?php endif; ?></div>
                                </div>
                                <div class="text-end"><span class="status-pill status-<?= esc($followupStatus) ?>"><i class="fe <?= $followupStatus === 'approved' ? 'fe-check' : ($followupStatus === 'rejected' ? 'fe-x' : 'fe-clock') ?>"></i><?= esc($followupStatus === 'rejected' ? 'disapproved' : $followupStatus) ?></span><?php if ($imageUrl !== ''): ?><a href="<?= esc($imageUrl, 'attr') ?>" target="_blank" rel="noopener" class="d-block"><img class="followup-image" src="<?= esc($imageUrl, 'attr') ?>" alt="Evidence for update <?= (int) $position + 1 ?>" loading="lazy"></a><?php endif; ?></div>
                            </div>
                            <?php if ($followupStatus !== 'pending' && (! empty($followup['reviewed_by_name']) || ! empty($followup['review_note']))): ?><div class="followup-context mt-3 small"><strong><?= $followupStatus === 'rejected' ? 'Disapproved' : 'Approved' ?><?= ! empty($followup['reviewed_by_name']) ? ' by ' . esc((string) $followup['reviewed_by_name']) : '' ?></strong><?= ! empty($followup['review_note']) ? '<div class="mt-1">' . esc((string) $followup['review_note']) . '</div>' : '' ?></div><?php endif; ?>
                            <?php if (is_array($pendingRequest)): ?>
                                <div class="followup-review">
                                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $pendingRequest['id'] . '/review') ?>">
                                        <?= csrf_field() ?>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-lg"><label class="form-label mb-1">Decision note</label><input class="form-control" name="note" maxlength="500" placeholder="Optional approval or disapproval note"></div>
                                            <div class="col-lg-auto d-flex flex-column flex-sm-row gap-2"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Disapprove this follow-up? It will lose 2 points.')"><i class="fe fe-x me-1"></i>Disapprove · −2</button><button class="btn btn-outline-warning" name="decision" value="approve_no_point" onclick="return confirm('Approve this follow-up without performance points?')"><i class="fe fe-minus-circle me-1"></i>No point · 0</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve this follow-up? It will earn 2 points.')"><i class="fe fe-check me-1"></i>Approve · +2</button></div>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (($otherItems ?? []) !== []): ?><div class="approval-section-label mb-3">Other mobile requests</div><?php endif; ?>
    <?php foreach (($otherItems ?? []) as $row): $payload = is_array($row['payload'] ?? null) ? $row['payload'] : []; $type = (string) ($row['request_type'] ?? ''); $status = (string) ($row['status'] ?? 'pending'); ?>
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
