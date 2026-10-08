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
.approval-hero{background:linear-gradient(125deg,#31152d,#651b46 56%,#b98a2e);border-radius:20px;color:#fff;padding:26px 30px;box-shadow:0 18px 44px rgba(65,18,55,.2);position:relative;overflow:hidden}.approval-hero:after{content:"";position:absolute;width:220px;height:220px;border:1px solid rgba(255,255,255,.18);border-radius:50%;right:-55px;top:-105px}.approval-hero h2{color:#fff;margin:0}.approval-card{border:1px solid #eadce6;border-radius:18px;box-shadow:0 10px 30px rgba(61,25,51,.07);overflow:hidden}.approval-card.pending{border-left:5px solid #c69a3a}.approval-head{background:linear-gradient(135deg,#fffaf0,#fff);border-bottom:1px solid #efe4d4;padding:18px 20px}.approval-mark{width:46px;height:46px;border-radius:14px;background:#f5e8b9;color:#6a4820;display:inline-flex;align-items:center;justify-content:center;font-size:20px}.approval-material{background:#faf7fa;border:1px solid #eee4ec;border-radius:13px;padding:13px;height:100%}.approval-material strong{color:#4a1739}.approval-photo{max-height:300px;width:100%;object-fit:contain;background:#22151f;border-radius:13px}.status-pill{border-radius:999px;font-size:11px;font-weight:800;padding:7px 11px;text-transform:uppercase;letter-spacing:.04em}.status-pending{background:#fff2c8;color:#77540a}.status-approved{background:#dcf7e8;color:#176a3c}.status-rejected{background:#fde5e7;color:#9d1d2a}
</style>
<div class="content container-fluid">
    <div class="approval-hero mb-4 d-flex align-items-center justify-content-between">
        <div><span class="text-uppercase small opacity-75">Admin control desk</span><h2>Mobile Approvals</h2><p class="mb-0 opacity-75">Review follow-up, delivery challan, issuement and master requests with complete details.</p></div>
        <div class="text-center"><div class="fs-2 fw-bold"><?= (int) $pendingCount ?></div><small>Pending</small></div>
    </div>
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
    <?php if ($items === []): ?>
        <div class="card approval-card"><div class="card-body text-center py-5"><i class="fe fe-check-circle fs-1 text-success"></i><h4 class="mt-3">No approval requests</h4></div></div>
    <?php endif; ?>
    <?php foreach ($items as $row): $payload = is_array($row['payload'] ?? null) ? $row['payload'] : []; $type = (string) ($row['request_type'] ?? ''); $status = (string) ($row['status'] ?? 'pending'); ?>
        <div class="card approval-card <?= esc($status) ?> mb-4">
            <div class="approval-head d-flex gap-3 align-items-center">
                <span class="approval-mark"><i class="fe <?= esc($icon($type)) ?>"></i></span>
                <div class="flex-grow-1"><div class="small text-muted">REQUEST #<?= (int) $row['id'] ?> · <?= esc(date('d M Y, h:i A', strtotime((string) $row['created_at']))) ?></div><h4 class="mb-1"><?= esc($label($type)) ?></h4><div><?= esc($row['summary'] ?? '') ?></div></div>
                <div class="text-end"><span class="status-pill status-<?= esc($status) ?>"><?= esc($status === 'rejected' ? 'disapproved' : $status) ?></span><small class="d-block text-muted mt-2">by <?= esc($row['requested_by_name'] ?? '-') ?></small></div>
            </div>
            <div class="card-body p-4">
                <?php if ($type === 'issuement'): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Karigar</small><strong class="d-block"><?= esc($payload['karigar_name'] ?? '-') ?></strong></div></div>
                        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Warehouse</small><strong class="d-block"><?= esc($payload['location_name'] ?? '-') ?></strong></div></div>
                        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Issue Date</small><strong class="d-block"><?= esc($payload['issue_date'] ?? '-') ?></strong></div></div>
                        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Purpose</small><strong class="d-block"><?= esc($payload['purpose'] ?? '-') ?></strong></div></div>
                    </div>
                    <div class="row g-3">
                    <?php foreach (['gold' => ['Gold','weight_gm','gm'], 'diamond' => ['Diamond','carat','ct'], 'stone' => ['Stone','qty','qty']] as $material => $meta): $lines = is_array($payload[$material . '_lines'] ?? null) ? $payload[$material . '_lines'] : []; if ($lines === []) continue; ?>
                        <div class="col-lg-4"><div class="approval-material"><strong><?= esc($meta[0]) ?> · <?= count($lines) ?> line(s)</strong>
                            <?php foreach ($lines as $i => $line): ?><div class="small border-top mt-2 pt-2"><strong><?= esc((string) ($line['display_name'] ?? ('Item #' . (int) ($line['item_id'] ?? 0)))) ?></strong><br>#<?= $i + 1 ?> · <?= esc(number_format((float) ($line[$meta[1]] ?? 0), 3)) ?> <?= esc($meta[2]) ?><?= isset($line['pcs']) ? ' · ' . (int) $line['pcs'] . ' pcs' : '' ?></div><?php endforeach; ?>
                        </div></div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($payload as $key => $value): if (is_array($value)) continue; if ((string) $value === '') continue; ?>
                        <div class="col-md-4"><div class="approval-material"><small class="text-muted text-capitalize"><?= esc(str_replace('_', ' ', (string) $key)) ?></small><strong class="d-block"><?= esc((string) $value) ?></strong></div></div>
                        <?php endforeach; ?>
                        <?php if (is_array($payload['address'] ?? null)): ?><div class="col-12"><div class="approval-material"><small class="text-muted">Address</small><strong class="d-block"><?= esc(implode(', ', array_filter(array_map('strval', $payload['address'])))) ?></strong></div></div><?php endif; ?>
                    </div>
                    <?php if ($type === 'delivery_challan' && is_array($payload['items'] ?? null) && $payload['items'] !== []): ?>
                    <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>#</th><th>Description</th><th>Type / Purity</th><th class="text-end">PCS</th><th class="text-end">Gross / Net</th><th class="text-end">Value</th></tr></thead><tbody>
                        <?php foreach ($payload['items'] as $i => $line): if (! is_array($line)) continue; ?>
                        <tr><td><?= (int) $i + 1 ?></td><td><?= esc((string) ($line['description'] ?? '-')) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($line['item_type'] ?? '-')))) ?> · <?= esc((string) (($line['purity'] ?? '') ?: '-')) ?></td><td class="text-end"><?= (int) ($line['pcs'] ?? 0) ?></td><td class="text-end"><?= number_format((float) ($line['gross_weight_gm'] ?? 0), 3) ?> / <?= number_format((float) ($line['net_weight_gm'] ?? 0), 3) ?> gm</td><td class="text-end">Rs. <?= number_format((float) ($line['value'] ?? 0), 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (! empty($row['attachment_url'])): ?><div class="mt-3"><small class="text-muted d-block mb-2">REQUEST IMAGE / ATTACHMENT</small><a href="<?= esc($row['attachment_url']) ?>" target="_blank"><img class="approval-photo" src="<?= esc($row['attachment_url']) ?>" alt="Request attachment"></a></div><?php endif; ?>
                <?php if ($status === 'pending'): ?>
                    <form method="post" action="<?= site_url('admin/mobile-approvals/' . (int) $row['id'] . '/review') ?>" class="mt-4 p-3 rounded bg-light">
                        <?= csrf_field() ?><div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">Admin note</label><input class="form-control" name="note" maxlength="500" placeholder="Optional approval / rejection note"></div><div class="col-md-auto d-flex gap-2"><button class="btn btn-outline-danger" name="decision" value="reject" onclick="return confirm('Reject this request?')"><i class="fe fe-x me-1"></i>Reject</button><button class="btn btn-success" name="decision" value="approve" onclick="return confirm('Approve and process this request?')"><i class="fe fe-check me-1"></i>Approve & Process</button></div></div>
                    </form>
                <?php elseif (! empty($row['review_note']) || ! empty($row['reviewed_by_name'])): ?><div class="alert alert-light border mt-3 mb-0"><strong><?= esc(ucfirst($status)) ?> by <?= esc($row['reviewed_by_name'] ?? '-') ?></strong><?= ! empty($row['review_note']) ? ' · ' . esc($row['review_note']) : '' ?><?= ! empty($row['result_reference']) ? ' · Reference ' . esc($row['result_reference']) : '' ?></div><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
