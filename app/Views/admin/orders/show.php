<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .order-detail-shell { margin: 0 auto; max-width: 1520px; }
    .order-detail-hero {
        background:
            radial-gradient(circle at 92% 15%, rgba(239, 196, 85, .32), transparent 16rem),
            linear-gradient(132deg, #54111b 0%, #8f1523 46%, #b32936 100%);
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 18px;
        box-shadow: 0 18px 42px rgba(92, 14, 26, .2);
        color: #fff;
        overflow: hidden;
        padding: 25px 27px;
        position: relative;
    }
    .order-detail-hero::after { border: 1px solid rgba(255, 255, 255, .13); border-radius: 50%; content: ''; height: 190px; position: absolute; right: -46px; top: -74px; width: 190px; }
    .order-detail-hero-copy { min-width: 0; position: relative; z-index: 1; }
    .order-detail-kicker { color: #f7d986; font-size: 10px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
    .order-detail-hero h1 { color: #fff; font-size: clamp(22px, 2.2vw, 34px); font-weight: 820; letter-spacing: -.02em; margin: 8px 0 12px; overflow-wrap: anywhere; }
    .order-hero-badges { display: flex; flex-wrap: wrap; gap: 7px; }
    .order-hero-badge { align-items: center; background: rgba(255, 255, 255, .13); border: 1px solid rgba(255, 255, 255, .2); border-radius: 999px; color: #fff; display: inline-flex; font-size: 10px; font-weight: 750; gap: 5px; padding: 7px 10px; }
    .order-detail-actions { display: flex; flex-wrap: wrap; gap: 8px; position: relative; z-index: 1; }
    .order-detail-actions .btn-outline-light { border-color: rgba(255, 255, 255, .7) !important; color: #fff !important; }
    .order-detail-actions .btn-outline-light:hover { background: #fff !important; color: #7b1220 !important; }
    .order-section-card { border-radius: 16px; overflow: hidden; }
    .order-section-card .card-header { align-items: center; background: linear-gradient(180deg, #fff, #fdfdfd); display: flex; justify-content: space-between; padding: 16px 18px; }
    .order-section-title { align-items: center; color: #202939; display: flex; font-size: 13px; font-weight: 800; gap: 9px; margin: 0; }
    .order-section-title i { align-items: center; background: var(--erp-red-soft); border-radius: 9px; color: var(--erp-red); display: inline-flex; height: 32px; justify-content: center; width: 32px; }
    .order-section-count { background: #f2f4f7; border-radius: 999px; color: #667085; font-size: 9px; font-weight: 750; padding: 5px 8px; }
    .order-fact-grid { display: grid; gap: 0; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .order-fact { border-bottom: 1px solid #edf0f4; min-height: 82px; padding: 15px 17px; }
    .order-fact:nth-child(3n + 2), .order-fact:nth-child(3n + 3) { border-left: 1px solid #edf0f4; }
    .order-fact-label { color: #8a94a5; font-size: 9px; font-weight: 800; letter-spacing: .06em; margin-bottom: 7px; text-transform: uppercase; }
    .order-fact-value { color: #253044; font-size: 12px; font-weight: 750; line-height: 1.4; overflow-wrap: anywhere; }
    .order-notes { background: #fffaf0; border: 1px solid #f0e5c8; border-radius: 11px; color: #5f553d; font-size: 11px; line-height: 1.6; margin: 16px; padding: 13px 15px; }
    .order-notes strong { color: #7a620f; display: block; font-size: 9px; letter-spacing: .06em; margin-bottom: 3px; text-transform: uppercase; }
    .order-photo-stage { align-items: center; background: linear-gradient(145deg, #f7f8fa, #eef1f5); border: 1px solid #e4e8ef; border-radius: 13px; display: flex; justify-content: center; min-height: 340px; overflow: hidden; position: relative; }
    .order-photo-stage > a { align-items: center; display: flex; height: 100%; justify-content: center; width: 100%; }
    .order-photo-stage img { display: block; height: 340px; object-fit: contain; width: 100%; }
    .order-photo-label { background: rgba(22, 29, 42, .8); border-radius: 999px; bottom: 11px; color: #fff; font-size: 9px; font-weight: 750; left: 11px; padding: 6px 9px; position: absolute; }
    .order-photo-empty { color: #8b95a5; padding: 35px 20px; text-align: center; }
    .order-photo-empty i { color: #c2c8d2; display: block; font-size: 38px; margin-bottom: 8px; }
    .order-photo-thumbs { display: grid; gap: 8px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 10px; }
    .order-photo-thumb { background: #f4f5f7; border: 1px solid #e4e8ef; border-radius: 10px; display: block; overflow: hidden; position: relative; }
    .order-photo-thumb img { height: 72px; object-fit: cover; width: 100%; }
    .order-photo-thumb span { background: rgba(22, 29, 42, .78); bottom: 4px; color: #fff; font-size: 7px; left: 4px; max-width: calc(100% - 8px); overflow: hidden; padding: 3px 5px; position: absolute; text-overflow: ellipsis; white-space: nowrap; }
    .order-items-table, .order-components-table, .order-followups-table { min-width: 760px; }
    .order-items-table tbody td, .order-components-table tbody td, .order-followups-table tbody td { font-size: 11px; padding: 13px 14px; vertical-align: middle; }
    .order-design-code { color: var(--erp-red-dark); font-size: 11px; font-weight: 800; }
    .order-design-name { color: #8b95a5; font-size: 9px; margin-top: 3px; }
    .order-weight-grid { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .order-weight-card { background: #fff; border: 1px solid #e5e9ef; border-radius: 13px; display: flex; gap: 11px; min-height: 90px; padding: 15px; }
    .order-weight-card i { align-items: center; background: #fff4d7; border-radius: 10px; color: #9c7410; display: inline-flex; flex: 0 0 38px; height: 38px; justify-content: center; }
    .order-weight-card small, .order-weight-card strong { display: block; }
    .order-weight-card small { color: #8b95a5; font-size: 9px; font-weight: 750; margin-bottom: 5px; text-transform: uppercase; }
    .order-weight-card strong { color: #202939; font-size: 16px; }
    .order-component-badge { background: #f2f4f7; border: 1px solid #e4e7ec; border-radius: 999px; color: #344054; display: inline-flex; font-size: 9px; font-weight: 750; padding: 5px 8px; }
    .followup-description { color: #344054; line-height: 1.5; max-width: 480px; }
    .followup-image { border: 1px solid #e1e5eb; border-radius: 8px; height: 46px; object-fit: cover; width: 58px; }
    @media (max-width: 991px) {
        .order-fact-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .order-fact:nth-child(n) { border-left: 0; }
        .order-fact:nth-child(even) { border-left: 1px solid #edf0f4; }
        .order-weight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .order-detail-hero { padding: 21px 18px; }
        .order-detail-actions { width: 100%; }
        .order-detail-actions .btn { flex: 1 1 auto; }
        .order-fact-grid { grid-template-columns: 1fr; }
        .order-fact:nth-child(n) { border-left: 0; }
        .order-photo-stage, .order-photo-stage img { height: 280px; min-height: 280px; }
        .order-items-table, .order-components-table, .order-followups-table { min-width: 0; }
        .order-items-table tbody td:first-child,
        .order-components-table tbody td:first-child,
        .order-followups-table tbody td:nth-child(2) { display: block !important; text-align: left !important; }
        .order-items-table tbody td:first-child::before,
        .order-components-table tbody td:first-child::before,
        .order-followups-table tbody td:nth-child(2)::before { display: block; margin-bottom: 8px; width: 100%; }
        .order-items-table tbody td:first-child > .erp-mobile-value,
        .order-components-table tbody td:first-child > .erp-mobile-value,
        .order-followups-table tbody td:nth-child(2) > .erp-mobile-value { max-width: 100%; text-align: left; }
    }
    @media (max-width: 480px) {
        .order-weight-grid { grid-template-columns: 1fr; }
        .order-photo-thumbs { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$attachments = is_array($attachments ?? null) ? $attachments : [];
$readyImages = is_array($readyImages ?? null) ? $readyImages : [];
$followups = is_array($followups ?? null) ? $followups : [];
$studdedDetails = is_array($studdedDetails ?? null) ? $studdedDetails : [];
$receiveSummary = is_array($receiveSummary ?? null) ? $receiveSummary : [];
$items = is_array($items ?? null) ? $items : [];
$photoGallery = [];
$imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
foreach ($attachments as $file) {
    $path = ltrim(trim((string) ($file['file_path'] ?? '')), '/');
    if ($path === '' || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $imageExtensions, true)) {
        continue;
    }
    $type = strtolower(trim((string) ($file['file_type'] ?? '')));
    $photoGallery[$path] = [
        'url' => base_url($path),
        'label' => str_contains($type, 'finish') ? 'Ready jewellery' : 'Order reference',
        'name' => (string) (($file['file_name'] ?? '') ?: basename($path)),
    ];
}
foreach ($readyImages as $readyImage) {
    $path = ltrim(trim((string) ($readyImage['image_path'] ?? '')), '/');
    $key = $path !== '' ? $path : 'ready:' . (int) ($readyImage['id'] ?? 0);
    if (isset($photoGallery[$key])) {
        $photoGallery[$key]['label'] = 'Ready jewellery';
        continue;
    }
    $photoGallery[$key] = [
        'url' => site_url('admin/orders/ready-image/' . (int) $readyImage['id']),
        'label' => 'Ready jewellery',
        'name' => (string) (($readyImage['design_name'] ?? '') ?: ('Ready item ' . ($readyImage['serial_no'] ?? ''))),
    ];
}
$photoGallery = array_values($photoGallery);
$primaryPhoto = $photoGallery[0] ?? null;
$status = (string) ($order['status'] ?? '');
$canReceive = ! in_array($status, ['Cancelled', 'Completed'], true) && (int) ($order['assigned_karigar_id'] ?? 0) > 0;
$formatDate = static function (?string $value): string {
    $timestamp = strtotime(trim((string) $value));
    return $timestamp === false ? '-' : date('d M Y', $timestamp);
};
$statusClass = match ($status) {
    'Completed', 'Dispatched', 'Ready' => 'success',
    'Cancelled' => 'danger',
    'QC', 'Packed' => 'info',
    'In Production' => 'warning',
    default => 'secondary',
};
?>

<div class="order-detail-shell">
    <div class="order-detail-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="order-detail-hero-copy">
                <div class="order-detail-kicker">Order workspace</div>
                <h1><?= esc((string) $order['order_no']) ?></h1>
                <div class="order-hero-badges">
                    <span class="order-hero-badge"><i class="fe fe-shopping-bag"></i><?= esc((string) $order['order_type']) ?></span>
                    <span class="order-hero-badge"><i class="fe fe-activity"></i><?= esc($status ?: '-') ?></span>
                    <span class="order-hero-badge"><i class="fe fe-flag"></i><?= esc((string) ($order['priority'] ?? '-')) ?> priority</span>
                    <span class="order-hero-badge"><i class="fe fe-calendar"></i>Due <?= esc($formatDate((string) ($order['due_date'] ?? ''))) ?></span>
                </div>
            </div>
            <div class="order-detail-actions">
                <?php if (! in_array($status, ['Cancelled', 'Completed'], true)): ?><a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-light"><i class="fe fe-edit me-1"></i>Edit</a><?php endif; ?>
                <?php if ($canReceive): ?><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiveModal"><i class="fe fe-check-circle me-1"></i>Receive Jewellery</button><?php endif; ?>
                <a href="<?= site_url((string) $order['order_type'] === 'Repair' ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-light"><i class="fe fe-arrow-left me-1"></i>Order List</a>
            </div>
        </div>
    </div>

    <?php if (! $canReceive && ! in_array($status, ['Cancelled', 'Completed'], true)): ?><div class="alert alert-warning">Assign a karigar before receiving finished jewellery.</div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card order-section-card h-100">
                <div class="card-header"><h5 class="order-section-title"><i class="fe fe-info"></i>Order Overview</h5><span class="badge bg-<?= esc($statusClass) ?>"><?= esc($status ?: '-') ?></span></div>
                <div class="card-body p-0">
                    <?php $details = [
                        ['Order From', ($order['order_from'] ?? '') ?: '-', 'fe fe-log-in'],
                        ['Customer', ($order['customer_name'] ?? '') ?: '-', 'fe fe-user'],
                        ['Assigned Karigar', ($order['karigar_name'] ?? '') ?: 'Not assigned', 'fe fe-tool'],
                        ['Sales Person', ($order['sales_person_name'] ?? '') ?: '-', 'fe fe-briefcase'],
                        ['Sales Mobile', ($order['sales_person_mobile'] ?? '') ?: '-', 'fe fe-phone'],
                        ['Due Date', $formatDate((string) ($order['due_date'] ?? '')), 'fe fe-calendar'],
                        ['Order Type', ($order['order_type'] ?? '') ?: '-', 'fe fe-shopping-bag'],
                        ['Design Type', ($order['order_design_type'] ?? '') ?: 'Fresh', 'fe fe-repeat'],
                        ['Created On', $formatDate((string) ($order['created_at'] ?? '')), 'fe fe-clock'],
                    ]; ?>
                    <div class="order-fact-grid">
                        <?php foreach ($details as [$label, $value, $icon]): ?>
                            <div class="order-fact">
                                <div class="order-fact-label"><i class="<?= esc($icon, 'attr') ?> me-1"></i><?= esc($label) ?></div>
                                <div class="order-fact-value"><?= esc((string) $value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-notes"><strong>Order notes</strong><?= nl2br(esc((string) (($order['order_notes'] ?? '') ?: 'No additional notes recorded.'))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card order-section-card h-100">
                <div class="card-header"><h5 class="order-section-title"><i class="fe fe-image"></i>Order &amp; Ready Photos</h5><span class="order-section-count"><?= count($photoGallery) ?> photo<?= count($photoGallery) === 1 ? '' : 's' ?></span></div>
                <div class="card-body">
                    <div class="order-photo-stage js-photo-frame">
                        <?php if ($primaryPhoto !== null): ?>
                            <a href="<?= esc((string) $primaryPhoto['url'], 'attr') ?>" target="_blank" rel="noopener">
                                <img class="js-order-image" src="<?= esc((string) $primaryPhoto['url'], 'attr') ?>" alt="<?= esc((string) $primaryPhoto['name'], 'attr') ?>">
                                <span class="order-photo-label"><?= esc((string) $primaryPhoto['label']) ?></span>
                            </a>
                        <?php else: ?>
                            <div class="order-photo-empty"><i class="fe fe-image"></i><strong>No order or ready photo</strong><div class="small mt-1">Photos will appear here once uploaded.</div></div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($photoGallery) > 1): ?>
                        <div class="order-photo-thumbs">
                            <?php foreach ($photoGallery as $photo): ?>
                                <a class="order-photo-thumb" href="<?= esc((string) $photo['url'], 'attr') ?>" target="_blank" rel="noopener" title="<?= esc((string) $photo['name'], 'attr') ?>">
                                    <img class="js-order-image" src="<?= esc((string) $photo['url'], 'attr') ?>" alt="<?= esc((string) $photo['name'], 'attr') ?>" loading="lazy">
                                    <span><?= esc((string) $photo['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card order-section-card mb-4">
        <div class="card-header"><h5 class="order-section-title"><i class="fe fe-list"></i>Order Items</h5><span class="order-section-count"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover order-items-table mb-0" data-dt-skip="true"><thead><tr><th>Design</th><th>Description</th><th>Purity</th><th>Size</th><th>Qty</th><th>Status</th></tr></thead><tbody>
        <?php if ($items === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No order items recorded.</td></tr><?php endif; ?>
        <?php foreach ($items as $item): ?><tr><td><div class="order-design-code"><?= esc((string) (($item['design_code'] ?? '') ?: 'Fresh design')) ?></div><div class="order-design-name"><?= esc((string) (($item['design_name'] ?? '') ?: 'No design master linked')) ?></div></td><td><?= esc((string) (($item['item_description'] ?? '') ?: '-')) ?></td><td><?= esc(trim((string) ($item['purity_code'] ?? '') . ' ' . (string) ($item['color_name'] ?? '')) ?: '-') ?></td><td><?= esc((string) (($item['size_label'] ?? '') ?: '-')) ?></td><td><?= esc((string) ($item['qty'] ?? 0)) ?></td><td><span class="badge bg-<?= esc($statusClass) ?>"><?= esc((string) ($item['item_status'] ?? '-')) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>

    <div class="card order-section-card mb-4">
        <div class="card-header"><h5 class="order-section-title"><i class="fe fe-gem"></i><?= esc('Studded & Finished Jewellery Details') ?></h5><span class="order-section-count"><?= count($studdedDetails) ?> component<?= count($studdedDetails) === 1 ? '' : 's' ?></span></div>
        <div class="card-body">
            <?php if ($receiveSummary !== []): ?>
                <div class="order-weight-grid mb-4">
                    <?php foreach ([['Gross Weight', number_format((float) ($receiveSummary['gross_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-package'], ['Net Gold', number_format((float) ($receiveSummary['net_gold_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-circle'], ['Pure Gold', number_format((float) ($receiveSummary['pure_gold_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-award'], ['Valuation', '₹' . number_format((float) ($receiveSummary['total_valuation'] ?? 0), 2), 'fe fe-credit-card']] as [$label, $value, $icon]): ?>
                        <div class="order-weight-card"><i class="<?= esc($icon, 'attr') ?>"></i><span><small><?= esc($label) ?></small><strong><?= esc($value) ?></strong></span></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted">Finished jewellery receiving summary is not available yet.</div>
            <?php endif; ?>
            <div class="table-responsive"><table class="table table-hover order-components-table mb-0" data-dt-skip="true"><thead><tr><th>Component</th><th>Description</th><th>PCS</th><th>Weight (cts)</th><th>Weight (gm)</th><th>Rate</th><th>Total</th></tr></thead><tbody>
            <?php if ($studdedDetails === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Studded details will appear after receiving.</td></tr><?php endif; ?>
            <?php foreach ($studdedDetails as $detail): ?><tr><td><span class="order-component-badge"><?= esc(ucfirst((string) ($detail['component_type'] ?? '-'))) ?></span></td><td><?= esc((string) ($detail['component_name'] ?? '-')) ?></td><td><?= number_format((float) ($detail['pcs'] ?? 0), 3) ?></td><td><?= number_format((float) ($detail['weight_cts'] ?? 0), 3) ?></td><td><?= number_format((float) ($detail['weight_gm'] ?? 0), 3) ?></td><td>₹<?= number_format((float) ($detail['rate'] ?? 0), 2) ?></td><td><strong>₹<?= number_format((float) ($detail['line_total'] ?? 0), 2) ?></strong></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>

    <div class="card order-section-card mb-4">
        <div class="card-header"><h5 class="order-section-title"><i class="fe fe-message-circle"></i>Follow-up History</h5><span class="order-section-count"><?= count($followups) ?> update<?= count($followups) === 1 ? '' : 's' ?></span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover order-followups-table mb-0" data-dt-skip="true"><thead><tr><th>Stage</th><th>Description</th><th>Next Follow-up</th><th>Taken By</th><th>Taken On</th><th>Image</th></tr></thead><tbody>
        <?php if ($followups === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No follow-ups recorded for this order.</td></tr><?php endif; ?>
        <?php foreach ($followups as $followup): ?><tr><td><span class="badge bg-light text-dark border"><?= esc((string) (($followup['stage'] ?? '') ?: '-')) ?></span></td><td><div class="followup-description"><?= esc((string) (($followup['description'] ?? '') ?: '-')) ?></div></td><td><?= esc($formatDate((string) ($followup['next_followup_date'] ?? ''))) ?></td><td><?= esc((string) (($followup['followup_taken_by_name'] ?? '') ?: 'Admin')) ?></td><td><?= esc($formatDate((string) ($followup['followup_taken_on'] ?? ''))) ?></td><td><?php if (! empty($followup['image_path'])): ?><?php $followupImageUrl = base_url(ltrim((string) $followup['image_path'], '/')); ?><a href="<?= esc($followupImageUrl, 'attr') ?>" target="_blank" rel="noopener"><img class="followup-image js-order-image" src="<?= esc($followupImageUrl, 'attr') ?>" alt="Follow-up image" loading="lazy"></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>
</div>

<?php if ($canReceive): ?>
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post" action="<?= site_url('admin/orders/'.$order['id'].'/receive') ?>"><?= csrf_field() ?>
<div class="modal-header"><h5 class="modal-title">Manual Finished Jewellery Receiving</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body js-receive-modal"><div class="alert alert-info">Enter every value manually. Nothing is fetched from issuements.</div>
<div class="row g-3 mb-4"><div class="col-md-3"><label class="form-label">Receive Location *</label><select name="location_id" class="form-select" required><option value="">Select</option><?php foreach (($locations??[]) as $location): ?><option value="<?= (int)$location['id'] ?>"><?= esc((string)$location['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Gross Weight (gm) *</label><input type="number" step="0.001" min="0.001" name="gross_weight_gm" class="form-control js-gross-weight" required></div><div class="col-md-3"><label class="form-label">Purity % *</label><input type="number" step="0.001" min="0.001" max="100" name="purity_percent" class="form-control js-purity-percent" required></div><div class="col-md-3"><label class="form-label">Net Gold (gm)</label><input type="text" class="form-control js-net-weight" readonly></div><div class="col-md-3"><label class="form-label">Pure Gold (gm)</label><input type="text" class="form-control js-pure-weight" readonly></div><div class="col-md-3"><label class="form-label">Gold Rate / gm *</label><input type="number" step="0.01" min="0.01" name="gold_rate_per_gm" class="form-control js-gold-rate" required></div><div class="col-md-3"><label class="form-label">Gold Amount</label><input type="text" class="form-control js-gold-total" readonly></div><div class="col-md-3"><label class="form-label">Labour Rate / gm</label><input type="number" step="0.01" min="0" name="labour_rate_per_gm" class="form-control js-labour-rate"></div><div class="col-md-3"><label class="form-label">Labour Amount</label><input type="text" class="form-control js-labour-total" readonly></div><div class="col-md-9"><label class="form-label">Remarks</label><input type="text" name="notes" class="form-control"></div></div>
<?php foreach ([['dia','Studded Diamond',['studded_diamond_type','studded_diamond_pcs','studded_diamond_weight','studded_diamond_rate']],['stone','Stone',['stone_type','stone_pcs','stone_weight','stone_rate']],['other','Other Material',['other_desc','other_pcs','other_weight_line_gm','other_price']]] as $section): ?><?php [$key,$title,$names]=$section; ?><div class="d-flex justify-content-between align-items-center mt-4 mb-2"><strong><?= esc($title) ?></strong><button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="<?= esc($key) ?>"><i class="fe fe-plus"></i> Add More</button></div><div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Description</th><th>PCS</th><th><?= $key==='other'?'Weight (gm)':'Weight (cts)' ?></th><th><?= $key==='other'?'Price':'Rate / cts' ?></th><th>Total</th><th></th></tr></thead><tbody class="js-<?= esc($key) ?>-body"><tr><td><input type="text" name="<?= esc($names[0]) ?>[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="<?= esc($names[1]) ?>[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="<?= esc($names[2]) ?>[]" class="form-control js-<?= esc($key) ?>-weight"></td><td><input type="number" step="0.01" min="0" name="<?= esc($names[3]) ?>[]" class="form-control js-<?= esc($key) ?>-rate"></td><td><input type="text" class="form-control js-<?= esc($key) ?>-total" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr></tbody></table></div><?php endforeach; ?>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Save & Complete Order</button></div></form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){const modal=document.getElementById('receiveModal');if(!modal)return;const n=v=>{const x=parseFloat(v||'0');return Number.isFinite(x)?x:0};const fields={dia:['studded_diamond_type','studded_diamond_pcs','studded_diamond_weight','studded_diamond_rate'],stone:['stone_type','stone_pcs','stone_weight','stone_rate'],other:['other_desc','other_pcs','other_weight_line_gm','other_price']};function rowHtml(k){const f=fields[k];return '<tr><td><input type="text" name="'+f[0]+'[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="'+f[1]+'[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="'+f[2]+'[]" class="form-control js-'+k+'-weight"></td><td><input type="number" step="0.01" min="0" name="'+f[3]+'[]" class="form-control js-'+k+'-rate"></td><td><input type="text" class="form-control js-'+k+'-total" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr>'}function recalc(){['dia','stone','other'].forEach(k=>modal.querySelectorAll('.js-'+k+'-body tr').forEach(row=>{const w=n((row.querySelector('.js-'+k+'-weight')||{}).value),r=n((row.querySelector('.js-'+k+'-rate')||{}).value),t=row.querySelector('.js-'+k+'-total');if(t)t.value=(k==='other'?r:w*r).toFixed(2)}));let d=0,s=0,o=0;modal.querySelectorAll('.js-dia-weight').forEach(e=>d+=n(e.value));modal.querySelectorAll('.js-stone-weight').forEach(e=>s+=n(e.value));modal.querySelectorAll('.js-other-weight').forEach(e=>o+=n(e.value));const gross=n((modal.querySelector('.js-gross-weight')||{}).value),purity=n((modal.querySelector('.js-purity-percent')||{}).value),net=gross-d*.2-s*.2-o,safe=Math.max(net,0),set=(q,v)=>{const e=modal.querySelector(q);if(e)e.value=v};set('.js-net-weight',net.toFixed(3));set('.js-pure-weight',(safe*purity/100).toFixed(3));set('.js-gold-total',(safe*n((modal.querySelector('.js-gold-rate')||{}).value)).toFixed(2));set('.js-labour-total',(safe*n((modal.querySelector('.js-labour-rate')||{}).value)).toFixed(2))}modal.addEventListener('click',e=>{const t=e.target instanceof Element?e.target:null;if(!t)return;const a=t.closest('.js-add-row');if(a){const k=a.getAttribute('data-kind'),b=modal.querySelector('.js-'+k+'-body');if(b)b.insertAdjacentHTML('beforeend',rowHtml(k))}const rm=t.closest('.js-remove-row');if(rm){const row=rm.closest('tr'),body=row?row.parentElement:null;if(body&&row&&body.children.length>1)row.remove()}recalc()});modal.addEventListener('input',recalc);recalc()})();
</script>
<script>
    (function () {
        document.querySelectorAll('.js-order-image').forEach(function (image) {
            image.addEventListener('error', function () {
                const stage = image.closest('.js-photo-frame');
                if (stage) {
                    stage.replaceChildren();
                    const empty = document.createElement('div');
                    empty.className = 'order-photo-empty';
                    empty.textContent = 'Photo file is unavailable.';
                    stage.appendChild(empty);
                    return;
                }
                const link = image.closest('a');
                if (link) link.remove();
            }, { once: true });
        });
    })();
</script>
<?= $this->endSection() ?>
