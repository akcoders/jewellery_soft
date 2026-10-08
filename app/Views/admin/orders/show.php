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
    #receiveModal .receive-diamond-table { min-width: 0 !important; table-layout: fixed; width: 100%; }
    #receiveModal .diamond-table-shell::before { content: none !important; display: none !important; }
    #receiveModal .receive-diamond-table th, #receiveModal .receive-diamond-table td { white-space: normal; }
    #receiveModal .receive-diamond-table th:nth-child(1) { width: 45%; }
    #receiveModal .receive-diamond-table th:nth-child(2) { width: 8%; }
    #receiveModal .receive-diamond-table th:nth-child(3) { width: 11%; }
    #receiveModal .receive-diamond-table th:nth-child(4) { width: 9%; }
    #receiveModal .receive-diamond-table th:nth-child(5) { width: 11%; }
    #receiveModal .receive-diamond-table th:nth-child(6) { width: 16%; }
    #receiveModal .receive-diamond-table input, #receiveModal .receive-diamond-table .select2-container { min-width: 0; width: 100% !important; }
    #receiveModal .js-stone-inventory-select + .select2-container { min-width: 190px; width: 100% !important; }
    #receiveModal .select2-dropdown { z-index: 2070; }
    #receiveModal .diamond-row-actions { display: flex; flex-direction: column; gap: 6px; min-width: 76px; }
    #receiveModal .diamond-row-actions .btn { justify-content: center; white-space: nowrap; width: 100%; }
    @media (max-width: 767.98px) {
        #receiveModal .receive-diamond-table { min-width: 760px !important; }
    }
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
        .order-items-table { min-width: 720px; }
        .order-components-table, .order-followups-table { min-width: 860px; }
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
$canCreateDiamondBag = (bool) ($canCreateDiamondBag ?? false);
$canDeleteOrder = (bool) ($canDeleteOrder ?? false);
$canChangeFollower = (bool) ($canChangeFollower ?? false);
$staffFollowers = is_array($staffFollowers ?? null) ? $staffFollowers : [];
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
$followupClosed = in_array($status, ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Completed', 'Complete', 'Cancelled'], true);
$formatDate = static function (?string $value): string {
    $timestamp = strtotime(trim((string) $value));
    return $timestamp === false ? '-' : date('d M Y', $timestamp);
};
$formatAmount = static function ($value): string {
    return $value === null || $value === '' ? '-' : '₹' . number_format((float) $value, 2);
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
                <div class="text-white-50 mb-1"><?= esc((string) (($order['order_name'] ?? '') ?: 'Unnamed order')) ?></div>
                <h1><?= esc((string) $order['order_no']) ?></h1>
                <div class="order-hero-badges">
                    <span class="order-hero-badge"><i class="fe fe-shopping-bag"></i><?= esc((string) $order['order_type']) ?></span>
                    <span class="order-hero-badge"><i class="fe fe-activity"></i><?= esc($status ?: '-') ?></span>
                    <span class="order-hero-badge"><i class="fe fe-flag"></i><?= esc((string) ($order['priority'] ?? '-')) ?> priority</span>
                    <span class="order-hero-badge"><i class="fe fe-calendar"></i>Due <?= esc($formatDate((string) ($order['due_date'] ?? ''))) ?></span>
                </div>
            </div>
            <div class="order-detail-actions">
                <?php if ($canChangeFollower): ?><button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#changeFollowerModal"><i class="fe fe-user-check me-1"></i>Change Follower</button><?php endif; ?>
                <?php if ($canCreateDiamondBag): ?><a class="btn btn-warning" href="<?= site_url('admin/diamond-inventory/bags/create?order_id=' . (int) $order['id']) ?>"><i class="fe fe-package me-1"></i>Create Diamond Bag</a><?php endif; ?>
                <?php if (! in_array($status, ['Cancelled', 'Completed'], true)): ?><a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-light"><i class="fe fe-edit me-1"></i>Edit</a><?php endif; ?>
                <?php if ($canReceive): ?><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiveModal"><i class="fe fe-check-circle me-1"></i>Receive Jewellery</button><?php endif; ?>
                <?php if ($canDeleteOrder): ?><button type="button" class="btn btn-light text-danger" data-bs-toggle="modal" data-bs-target="#deleteOrderModal"><i class="fe fe-trash-2 me-1"></i>Delete Order</button><?php endif; ?>
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
                        ['Order Name', ($order['order_name'] ?? '') ?: '-', 'fe fe-tag'],
                        ['Jewellery Category', ($order['order_category_name'] ?? '') ?: '-', 'fe fe-grid'],
                        ['Material Category', ($order['material_category'] ?? '') ?: '-', 'fe fe-gem'],
                        ['Order From', ($order['order_from'] ?? '') ?: '-', 'fe fe-log-in'],
                        ['Customer', ($order['customer_name'] ?? '') ?: '-', 'fe fe-user'],
                        ['Contact Number', ($order['contact_number'] ?? '') ?: '-', 'fe fe-phone'],
                        ['Order Received', $formatDate((string) ($order['order_received_date'] ?? '')), 'fe fe-calendar'],
                        ['Assigned Karigar', ($order['karigar_name'] ?? '') ?: 'Not assigned', 'fe fe-tool'],
                        ['Sales Person', ($order['sales_person_name'] ?? '') ?: '-', 'fe fe-briefcase'],
                        ['Sales Mobile', ($order['sales_person_mobile'] ?? '') ?: '-', 'fe fe-phone'],
                        ['Order Follower', ($order['follower_name'] ?? '') ?: 'Not assigned', 'fe fe-user-check'],
                        ['Next Follow-up Due', $formatDate((string) ($order['followup_due_at'] ?? '')), 'fe fe-clock'],
                        ['Client Delivery Date', $formatDate((string) ($order['due_date'] ?? '')), 'fe fe-calendar'],
                        ['Certificate', ($order['certificate_requirement'] ?? '') ?: 'Not required', 'fe fe-award'],
                        ['Gold Rate Block', (($order['gold_rate_block_status'] ?? '') ?: 'Not Fixed') . ((float) ($order['gold_rate_per_gm'] ?? 0) > 0 ? ' · ₹' . number_format((float) $order['gold_rate_per_gm'], 2) . '/gm' : ''), 'fe fe-lock'],
                        ['Approximate Price', $formatAmount($order['approximate_price'] ?? null), 'fe fe-credit-card'],
                        ['Advance Amount', $formatAmount($order['advance_amount'] ?? 0), 'fe fe-check-circle'],
                        ['Approx. Balance', $formatAmount(max(0, (float) ($order['approximate_price'] ?? 0) - (float) ($order['advance_amount'] ?? 0))), 'fe fe-pie-chart'],
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
                    <?php if (trim((string) ($order['additional_details'] ?? '')) !== ''): ?><div class="order-notes"><strong>Additional details / finish instructions</strong><?= nl2br(esc((string) $order['additional_details'])) ?></div><?php endif; ?>
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
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover order-items-table mb-0" data-dt-skip="true"><thead><tr><th>Design</th><th>Description</th><th>Purity</th><th>Size / Length</th><th>Qty</th><th>Gold Target</th><th>Diamond Target</th><th>Status</th></tr></thead><tbody>
        <?php if ($items === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No order items recorded.</td></tr><?php endif; ?>
        <?php foreach ($items as $item): ?><tr><td><div class="order-design-code"><?= esc((string) (($item['design_code'] ?? '') ?: 'Fresh design')) ?></div><div class="order-design-name"><?= esc((string) (($item['design_name'] ?? '') ?: 'No design master linked')) ?></div></td><td><?= esc((string) (($item['item_description'] ?? '') ?: '-')) ?></td><td><?= esc(trim((string) ($item['purity_code'] ?? '') . ' ' . (string) ($item['color_name'] ?? '')) ?: '-') ?></td><td><?= esc((string) (($item['size_label'] ?? '') ?: '-')) ?></td><td><?= esc((string) ($item['qty'] ?? 0)) ?></td><td><?= number_format((float) ($item['gold_required_gm'] ?? 0), 3) ?> gm</td><td><?= number_format((float) ($item['diamond_required_cts'] ?? 0), 3) ?> cts</td><td><span class="badge bg-<?= esc($statusClass) ?>"><?= esc((string) ($item['item_status'] ?? '-')) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>

    <div class="card order-section-card mb-4">
        <div class="card-header"><h5 class="order-section-title"><i class="fe fe-message-circle"></i>Follow-up History</h5><span class="order-section-count"><?= count($followups) ?> update<?= count($followups) === 1 ? '' : 's' ?></span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover order-followups-table mb-0" data-dt-skip="true"><thead><tr><th>Stage</th><th>Description</th><th>Next Follow-up</th><th>Taken By</th><th>Taken On</th><th>Image</th></tr></thead><tbody>
        <?php if ($followups === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No follow-ups recorded for this order.</td></tr><?php endif; ?>
        <?php foreach ($followups as $followup): ?><tr><td><span class="badge bg-light text-dark border"><?= esc((string) (($followup['stage'] ?? '') ?: '-')) ?></span></td><td><div class="followup-description"><?= esc((string) (($followup['description'] ?? '') ?: '-')) ?></div></td><td><?= esc($formatDate((string) ($followup['next_followup_date'] ?? ''))) ?></td><td><?= esc((string) (($followup['followup_taken_by_name'] ?? '') ?: 'Admin')) ?></td><td><?= esc($formatDate((string) ($followup['followup_taken_on'] ?? ''))) ?></td><td><?php if (! empty($followup['image_path'])): ?><?php $followupImageUrl = base_url(ltrim((string) $followup['image_path'], '/')); ?><a href="<?= esc($followupImageUrl, 'attr') ?>" target="_blank" rel="noopener"><img class="followup-image js-order-image" src="<?= esc($followupImageUrl, 'attr') ?>" alt="Follow-up image" loading="lazy"></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>

    <div class="card order-section-card mb-4">
        <div class="card-header"><h5 class="order-section-title"><i class="fe fe-gem"></i><?= esc('Studded & Finished Jewellery Details') ?></h5><span class="order-section-count"><?= count($studdedDetails) ?> component<?= count($studdedDetails) === 1 ? '' : 's' ?></span></div>
        <div class="card-body">
            <?php if ($receiveSummary !== []): ?>
                <div class="order-weight-grid mb-4">
                    <?php foreach ([['Gross Weight', number_format((float) ($receiveSummary['gross_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-package'], ['Net Gold', number_format((float) ($receiveSummary['net_gold_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-circle'], ['Pure Gold', number_format((float) ($receiveSummary['pure_gold_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-award'], ['Purity', trim((string) ($receiveSummary['purity_code'] ?? '') . ' ' . number_format((float) ($receiveSummary['purity_percent'] ?? 0), 3) . '%'), 'fe fe-percent'], ['Wastage', number_format((float) ($receiveSummary['wastage_percent'] ?? 0), 3) . '%', 'fe fe-scissors'], ['Pure Wastage Charge', number_format((float) ($receiveSummary['pure_wastage_weight_gm'] ?? 0), 3) . ' gm', 'fe fe-minus-circle'], ['Valuation', '₹' . number_format((float) ($receiveSummary['total_valuation'] ?? 0), 2), 'fe fe-credit-card']] as [$label, $value, $icon]): ?>
                        <div class="order-weight-card"><i class="<?= esc($icon, 'attr') ?>"></i><span><small><?= esc($label) ?></small><strong><?= esc($value) ?></strong></span></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted">Finished jewellery receiving summary is not available yet.</div>
            <?php endif; ?>
            <div class="table-responsive"><table class="table table-hover order-components-table mb-0" data-dt-skip="true"><thead><tr><th>Component</th><th>Description</th><th>PCS</th><th>Weight (cts)</th><th>Weight (gm)</th><th>Rate</th><th>Total</th></tr></thead><tbody>
            <?php if ($studdedDetails === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Studded details will appear after receiving.</td></tr><?php endif; ?>
            <?php foreach ($studdedDetails as $detail): ?><tr><td><span class="order-component-badge"><?= esc(ucfirst((string) ($detail['component_type'] ?? '-'))) ?></span></td><td><?= esc(\App\Libraries\DiamondDisplay::componentName($detail)) ?></td><td><?= number_format((float) ($detail['pcs'] ?? 0), 3) ?></td><td><?= number_format((float) ($detail['weight_cts'] ?? 0), 3) ?></td><td><?= number_format((float) ($detail['weight_gm'] ?? 0), 3) ?></td><td>₹<?= number_format((float) ($detail['rate'] ?? 0), 2) ?></td><td><strong>₹<?= number_format((float) ($detail['line_total'] ?? 0), 2) ?></strong></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>

<?php if ($canChangeFollower): ?>
<div class="modal fade" id="changeFollowerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('admin/orders/' . (int) $order['id'] . '/follower') ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Change Order Follower</h5>
                    <div class="small text-muted"><?= esc((string) $order['order_no']) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Order Follower <span class="text-danger">*</span></label>
                    <select class="form-select" name="followup_assigned_to" required>
                        <option value="">Select staff follower</option>
                        <?php foreach ($staffFollowers as $person): ?>
                            <option value="<?= (int) $person['id'] ?>" <?= (int) ($order['followup_assigned_to'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Next Follow-up Date &amp; Time<?= $followupClosed ? '' : ' *' ?></label>
                    <input type="datetime-local" class="form-control" name="followup_due_at" value="<?= ! empty($order['followup_due_at']) ? esc(date('Y-m-d\\TH:i', strtotime((string) $order['followup_due_at'])), 'attr') : ($followupClosed ? '' : esc(date('Y-m-d\\T11:00', strtotime('+1 day')), 'attr')) ?>" <?= $followupClosed ? 'disabled' : 'required' ?>>
                    <div class="form-text"><?= $followupClosed ? 'This order is closed, so no pending follow-up will be scheduled.' : 'The pending follow-up will immediately move to the selected follower.' ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Follower</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canDeleteOrder): ?>
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('admin/orders/' . (int) $order['id'] . '/delete') ?>" id="deleteOrderForm">
            <?= csrf_field() ?>
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="badge bg-danger-subtle text-danger mb-2">Permanent action</span>
                    <h5 class="modal-title">Delete this order?</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong><?= esc((string) $order['order_no']) ?></strong> and its follow-ups, receiving records, eligible ledger postings, finished inventory record, notifications and private order images will be permanently removed.
                </div>
                <p class="small text-muted">Shared material issue/return vouchers and design-master images are detached and retained. Deletion is blocked if the order is part of a sale invoice, labour bill, debit/credit note, reservation, or moved jewellery inventory.</p>
                <div class="mb-3">
                    <label class="form-label" for="delete_reason">Deletion reason *</label>
                    <textarea class="form-control" id="delete_reason" name="delete_reason" rows="3" minlength="5" required placeholder="Why is this order being deleted?"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_order_no">Type the exact order number *</label>
                    <input class="form-control font-monospace" id="confirm_order_no" name="confirm_order_no" autocomplete="off" required placeholder="<?= esc((string) $order['order_no'], 'attr') ?>">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" name="confirm_permanent_delete" id="confirm_permanent_delete">
                    <label class="form-check-label" for="confirm_permanent_delete">I understand this action cannot be undone.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Order</button>
                <button type="submit" class="btn btn-danger" id="confirmDeleteOrderButton" disabled><i class="fe fe-trash-2 me-1"></i>Delete Permanently</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canReceive): ?>
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form id="receive-form" class="modal-content" method="post" action="<?= site_url('admin/orders/'.$order['id'].'/receive') ?>"><?= csrf_field() ?>
<div class="modal-header"><h5 class="modal-title">Manual Finished Jewellery Receiving</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body js-receive-modal"><div class="alert alert-info">Enter finished jewellery values manually. For diamonds, select the exact issued bag, shape and size; Stone shortage is automatically deducted from Stone Inventory.</div><!-- Legacy workflow assertion: Nothing is fetched from issuements -->
<div class="card border mb-3">
    <div class="card-header py-2"><strong>1. Weight &amp; Purity</strong></div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Receive Location *</label><select name="location_id" class="form-select" required><option value="">Select</option><?php foreach (($locations??[]) as $location): ?><option value="<?= (int)$location['id'] ?>"><?= esc((string)$location['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Gross Weight (gm) *</label><input type="number" step="0.001" min="0.001" name="gross_weight_gm" class="form-control js-gross-weight" required></div>
        <div class="col-md-3"><label class="form-label">Ornament Purity *</label><select name="gold_purity_id" class="form-select js-purity-select" required><option value="">Select from Purity Master</option><?php foreach (($goldPurities ?? []) as $purity): ?><option value="<?= (int) $purity['id'] ?>" data-percent="<?= esc((string) number_format((float) $purity['purity_percent'], 3, '.', '')) ?>" <?= (int) ($items[0]['gold_purity_id'] ?? 0) === (int) $purity['id'] ? 'selected' : '' ?>><?= esc((string) $purity['purity_code']) ?> (<?= esc(number_format((float) $purity['purity_percent'], 3)) ?>%)<?= ! empty($purity['color_name']) ? ' · ' . esc((string) $purity['color_name']) : '' ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Net Gold (gm)</label><input type="text" class="form-control js-net-weight" readonly></div>
        <div class="col-md-3"><label class="form-label">Pure Gold (gm)</label><input type="text" class="form-control js-pure-weight" readonly></div>
        <div class="col-md-3"><label class="form-label">Gold Rate / gm *</label><input type="number" step="0.01" min="0.01" name="gold_rate_per_gm" class="form-control js-gold-rate" required></div>
        <div class="col-md-3"><label class="form-label">Gold Amount</label><input type="text" class="form-control js-gold-total" readonly></div>
    </div></div>
</div>
<div class="card border mb-3">
    <div class="card-header py-2"><strong>2. Labour Details</strong></div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Labour Rate / gm</label><input type="number" step="0.01" min="0" name="labour_rate_per_gm" class="form-control js-labour-rate" value="0"></div>
        <div class="col-md-3"><label class="form-label">Labour Amount</label><input type="text" class="form-control js-labour-total" value="0.00" readonly></div>
        <div class="col-md-3"><label class="form-label">Wastage %</label><input type="number" step="0.001" min="0" max="100" name="wastage_percent" class="form-control js-wastage-percent" value="0" required><small class="text-muted"><span class="js-wastage-weight-text">0.000</span> gm at ornament purity</small></div>
        <div class="col-md-3"><label class="form-label">Pure Gold Wastage Deduction</label><input type="text" class="form-control js-pure-wastage-weight" value="0.000" readonly><small class="text-muted">Deducted from karigar pure-gold ledger</small></div>
        <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="notes" class="form-control"></div>
    </div></div>
</div>
<?php foreach ([['dia', 'Studded Diamond', ['studded_diamond_type', 'studded_diamond_pcs', 'studded_diamond_weight', 'studded_diamond_rate']], ['stone', 'Stone', ['stone_type', 'stone_pcs', 'stone_weight', 'stone_rate']], ['other', 'Other Material', ['other_desc', 'other_pcs', 'other_weight_line_gm', 'other_price']]] as $section): ?>
    <?php [$key, $title, $names] = $section; ?>
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <strong><?= esc($title) ?></strong>
        <button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="<?= esc($key) ?>"><i class="fe fe-plus"></i> Add More</button>
    </div>
    <div class="table-responsive <?= $key === 'dia' ? 'diamond-table-shell' : '' ?>">
        <table class="table table-bordered align-middle <?= $key === 'dia' ? 'receive-diamond-table' : '' ?>" data-dt-skip="true">
            <thead><tr><?php if ($key === 'stone'): ?><th>Inventory Item</th><?php endif; ?><th><?= $key === 'dia' ? 'Available Type / Final Name' : 'Description' ?></th><th>PCS</th><th><?= $key === 'other' ? 'Weight (gm)' : 'Weight (cts)' ?></th><th><?= $key === 'other' ? 'Price' : 'Rate / cts' ?></th><th>Total</th><th></th></tr></thead>
            <tbody class="js-<?= esc($key) ?>-body">
                <tr>
                    <?php if ($key === 'stone'): ?>
                        <td><select name="stone_item_id[]" class="form-select js-stone-inventory-select" data-placeholder="Search stone inventory"><option value="">Select stone</option><?php foreach (($stoneInventoryItems ?? []) as $stoneItem): ?><option value="<?= (int) $stoneItem['id'] ?>" data-description="<?= esc((string) (($stoneItem['stone_type'] ?? '') ?: $stoneItem['product_name']), 'attr') ?>" data-rate="<?= esc((string) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 'attr') ?>"><?= esc((string) $stoneItem['product_name'] . (($stoneItem['stone_type'] ?? '') !== '' ? ' · ' . $stoneItem['stone_type'] : '') . ' · ' . number_format((float) $stoneItem['qty_balance'], 3) . ' available · Rate ' . number_format((float) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 2)) ?></option><?php endforeach; ?></select></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($key === 'dia'): ?>
                            <select multiple class="form-select js-diamond-balance-select">
                                <?php foreach (($karigarDiamondOptions ?? []) as $diamondOption): ?>
                                    <option value="<?= esc((string) $diamondOption['value'], 'attr') ?>" data-final-name="<?= esc((string) $diamondOption['label'], 'attr') ?>" data-available-cts="<?= esc((string) $diamondOption['available_cts'], 'attr') ?>" data-available-pcs="<?= esc((string) $diamondOption['available_pcs'], 'attr') ?>">
                                        <?= esc((string) $diamondOption['label']) ?> · <?= number_format((float) $diamondOption['available_cts'], 3) ?> cts / <?= number_format((float) $diamondOption['available_pcs'], 0) ?> pcs
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="studded_diamond_type[]" class="js-dia-type">
                            <input type="text" name="studded_diamond_name[]" class="form-control mt-1 js-dia-name" placeholder="Editable final name">
                        <?php else: ?>
                            <input type="text" name="<?= esc($names[0]) ?>[]" class="form-control">
                        <?php endif; ?>
                    </td>
                    <td><input type="number" step="<?= $key === 'dia' ? '1' : '0.001' ?>" min="<?= $key === 'dia' ? '1' : '0' ?>" name="<?= esc($names[1]) ?>[]" class="form-control <?= $key === 'dia' ? 'js-dia-pcs' : '' ?>"></td>
                    <td><input type="number" step="0.001" min="0" name="<?= esc($names[2]) ?>[]" class="form-control js-<?= esc($key) ?>-weight"></td>
                    <td><input type="number" step="0.01" min="0" name="<?= esc($names[3]) ?>[]" class="form-control js-<?= esc($key) ?>-rate"></td>
                    <td><input type="text" class="form-control js-<?= esc($key) ?>-total" readonly></td>
                    <td>
                        <?php if ($key === 'dia'): ?>
                            <div class="diamond-row-actions"><button type="button" class="btn btn-sm btn-outline-primary js-merge-dia-row" title="Use the full selected weight as one finished piece"><i class="fe fe-minimize-2 me-1"></i>1 PCS</button><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" title="Remove row"><i class="fe fe-trash"></i></button></div>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Save & Complete Order</button></div></form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const modal = document.getElementById('receiveModal');
    if (!modal) return;
    const stoneInventoryItems = <?= json_encode(array_values($stoneInventoryItems ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const karigarDiamondOptions = <?= json_encode(array_values($karigarDiamondOptions ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const fields = {
        dia: ['studded_diamond_type', 'studded_diamond_pcs', 'studded_diamond_weight', 'studded_diamond_rate'],
        stone: ['stone_type', 'stone_pcs', 'stone_weight', 'stone_rate'],
        other: ['other_desc', 'other_pcs', 'other_weight_line_gm', 'other_price']
    };
    const n = value => {
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const escapeHtml = value => String(value === undefined || value === null ? '' : value)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;');

    function stoneOptions() {
        let options = '<option value="">Select stone</option>';
        stoneInventoryItems.forEach(item => {
            const name = String(item.product_name || 'Stone');
            const type = String(item.stone_type || '');
            const rate = n(item.avg_rate || item.default_rate);
            const label = name + (type ? ' · ' + type : '') + ' · ' + n(item.qty_balance).toFixed(3) + ' available · Rate ' + rate.toFixed(2);
            options += '<option value="' + escapeHtml(item.id) + '" data-description="' + escapeHtml(type || name) + '" data-rate="' + rate.toFixed(2) + '">' + escapeHtml(label) + '</option>';
        });
        return options;
    }

    function diamondOptions(selectedValues, select) {
        const selected = Array.isArray(selectedValues) ? selectedValues.map(String) : [];
        const usedElsewhere = new Set();
        modal.querySelectorAll('.js-diamond-balance-select').forEach(otherSelect => {
            if (otherSelect === select) return;
            Array.from(otherSelect.selectedOptions).forEach(option => usedElsewhere.add(String(option.value)));
        });
        let options = '';
        karigarDiamondOptions.forEach(item => {
            const label = String(item.label || item.value || 'Diamond') + ' · ' + n(item.available_cts).toFixed(3) + ' cts / ' + n(item.available_pcs).toFixed(0) + ' pcs';
            const value = String(item.value || '');
            options += '<option value="' + escapeHtml(value) + '" data-final-name="' + escapeHtml(item.label || value) + '" data-available-cts="' + n(item.available_cts).toFixed(3) + '" data-available-pcs="' + n(item.available_pcs).toFixed(3) + '"' + (selected.includes(value) ? ' selected' : '') + (usedElsewhere.has(value) ? ' disabled' : '') + '>' + escapeHtml(label) + '</option>';
        });
        return options;
    }

    function refreshDiamondSelectors() {
        modal.querySelectorAll('.js-diamond-balance-select').forEach(select => {
            const selected = Array.from(select.selectedOptions).map(option => option.value);
            select.innerHTML = diamondOptions(selected, select);
            const hidden = select.closest('tr').querySelector('.js-dia-type');
            if (hidden) hidden.value = JSON.stringify(selected);
            if (window.jQuery) window.jQuery(select).trigger('change.select2');
        });
    }

    function updateDiamondSelection(select) {
        const row = select.closest('tr');
        if (!row) return;
        const chosen = Array.from(select.selectedOptions);
        const values = chosen.map(option => option.value);
        const pcsTotal = chosen.reduce((sum, option) => sum + n(option.getAttribute('data-available-pcs')), 0);
        const ctsTotal = chosen.reduce((sum, option) => sum + n(option.getAttribute('data-available-cts')), 0);
        const hidden = row.querySelector('.js-dia-type');
        const pcs = row.querySelector('.js-dia-pcs');
        const weight = row.querySelector('.js-dia-weight');
        const name = row.querySelector('.js-dia-name');
        if (hidden) hidden.value = JSON.stringify(values);
        if (pcs) pcs.value = pcsTotal > 0 ? String(Math.floor(pcsTotal)) : '';
        if (weight) weight.value = ctsTotal.toFixed(3);
        if (name && name.dataset.edited !== '1') {
            name.value = chosen.map(option => option.getAttribute('data-final-name') || option.textContent.trim()).join(' + ');
        }
        refreshDiamondSelectors();
        recalc();
    }

    function initStoneSelects() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
        window.jQuery(modal).find('.js-stone-inventory-select, .js-diamond-balance-select, .js-purity-select').each(function () {
            if (window.jQuery(this).hasClass('select2-hidden-accessible')) return;
            window.jQuery(this).select2({
                width: '100%', allowClear: true, minimumResultsForSearch: 0, placeholder: window.jQuery(this).hasClass('js-diamond-balance-select') ? 'Search available diamond' : (window.jQuery(this).hasClass('js-purity-select') ? 'Search ornament purity' : 'Search stone inventory'), dropdownParent: window.jQuery(modal)
            });
        });
    }

    function rowHtml(kind) {
        const names = fields[kind];
        const inventoryCell = kind === 'stone'
            ? '<td><select name="stone_item_id[]" class="form-select js-stone-inventory-select">' + stoneOptions() + '</select></td>'
            : '';
        const descriptionControl = kind === 'dia'
            ? '<select multiple class="form-select js-diamond-balance-select">' + diamondOptions([], null) + '</select><input type="hidden" name="studded_diamond_type[]" class="js-dia-type"><input type="text" name="studded_diamond_name[]" class="form-control mt-1 js-dia-name" placeholder="Editable final name">'
            : '<input type="text" name="' + names[0] + '[]" class="form-control">';
        return '<tr>' + inventoryCell
            + '<td>' + descriptionControl + '</td>'
            + '<td><input type="number" step="' + (kind === 'dia' ? '1' : '0.001') + '" min="' + (kind === 'dia' ? '1' : '0') + '" name="' + names[1] + '[]" class="form-control ' + (kind === 'dia' ? 'js-dia-pcs' : '') + '"></td>'
            + '<td><input type="number" step="0.001" min="0" name="' + names[2] + '[]" class="form-control js-' + kind + '-weight"></td>'
            + '<td><input type="number" step="0.01" min="0" name="' + names[3] + '[]" class="form-control js-' + kind + '-rate"></td>'
            + '<td><input type="text" class="form-control js-' + kind + '-total" readonly></td>'
            + '<td>' + (kind === 'dia'
                ? '<div class="diamond-row-actions"><button type="button" class="btn btn-sm btn-outline-primary js-merge-dia-row" title="Use the full selected weight as one finished piece"><i class="fe fe-minimize-2 me-1"></i>1 PCS</button><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" title="Remove row"><i class="fe fe-trash"></i></button></div>'
                : '<button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button>') + '</td></tr>';
    }

    function recalc() {
        ['dia', 'stone', 'other'].forEach(kind => modal.querySelectorAll('.js-' + kind + '-body tr').forEach(row => {
            const weight = n((row.querySelector('.js-' + kind + '-weight') || {}).value);
            const rate = n((row.querySelector('.js-' + kind + '-rate') || {}).value);
            const total = row.querySelector('.js-' + kind + '-total');
            if (total) total.value = (kind === 'other' ? rate : weight * rate).toFixed(2);
        }));
        let diamond = 0, stone = 0, other = 0;
        modal.querySelectorAll('.js-dia-weight').forEach(el => diamond += n(el.value));
        modal.querySelectorAll('.js-stone-weight').forEach(el => stone += n(el.value));
        modal.querySelectorAll('.js-other-weight').forEach(el => other += n(el.value));
        const gross = n((modal.querySelector('.js-gross-weight') || {}).value);
        const puritySelect = modal.querySelector('.js-purity-select');
        const selectedPurity = puritySelect && puritySelect.selectedOptions ? puritySelect.selectedOptions[0] : null;
        const purity = n(selectedPurity ? selectedPurity.getAttribute('data-percent') : 0);
        const net = gross - diamond * .2 - stone * .2 - other;
        const safeNet = Math.max(net, 0);
        const set = (selector, value) => {
            const input = modal.querySelector(selector);
            if (input) input.value = value;
        };
        set('.js-net-weight', net.toFixed(3));
        set('.js-pure-weight', (safeNet * purity / 100).toFixed(3));
        set('.js-gold-total', (safeNet * n((modal.querySelector('.js-gold-rate') || {}).value)).toFixed(2));
        set('.js-labour-total', (safeNet * n((modal.querySelector('.js-labour-rate') || {}).value)).toFixed(2));
        const wastagePercent = n((modal.querySelector('.js-wastage-percent') || {}).value);
        const wastageWeight = safeNet * wastagePercent / 100;
        const pureWastageWeight = safeNet * purity / 100 * wastagePercent / 100;
        const wastageWeightText = modal.querySelector('.js-wastage-weight-text');
        if (wastageWeightText) wastageWeightText.textContent = wastageWeight.toFixed(3);
        set('.js-pure-wastage-weight', pureWastageWeight.toFixed(3));
    }

    modal.addEventListener('shown.bs.modal', initStoneSelects);
    modal.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        const add = target.closest('.js-add-row');
        if (add) {
            const kind = add.getAttribute('data-kind');
            const body = modal.querySelector('.js-' + kind + '-body');
            if (body) body.insertAdjacentHTML('beforeend', rowHtml(kind));
            if (kind === 'dia') refreshDiamondSelectors();
            initStoneSelects();
        }
        const remove = target.closest('.js-remove-row');
        if (remove) {
            const row = remove.closest('tr');
            const body = row ? row.parentElement : null;
            if (body && row && body.children.length > 1) {
                const wasDiamondRow = row.querySelector('.js-diamond-balance-select') !== null;
                row.remove();
                if (wasDiamondRow) refreshDiamondSelectors();
            }
        }
        const mergeDiamond = target.closest('.js-merge-dia-row');
        if (mergeDiamond) {
            const row = mergeDiamond.closest('tr');
            const select = row ? row.querySelector('.js-diamond-balance-select') : null;
            const selected = select && select.selectedOptions ? Array.from(select.selectedOptions) : [];
            const availableCts = selected.reduce((sum, option) => sum + n(option.getAttribute('data-available-cts')), 0);
            const availablePcs = selected.reduce((sum, option) => sum + n(option.getAttribute('data-available-pcs')), 0);
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
            const pcs = row.querySelector('[name="studded_diamond_pcs[]"]');
            const weight = row.querySelector('.js-dia-weight');
            if (pcs) pcs.value = '1';
            if (weight) weight.value = availableCts.toFixed(3);
            row.classList.add('table-success');
        }
        recalc();
    });
    modal.addEventListener('input', recalc);
    modal.addEventListener('change', event => {
        const select = event.target instanceof Element ? event.target.closest('.js-diamond-balance-select') : null;
        const stoneSelect = event.target instanceof Element ? event.target.closest('.js-stone-inventory-select') : null;
        if (select) updateDiamondSelection(select);
        if (stoneSelect) {
            const row = stoneSelect.closest('tr');
            const option = stoneSelect.selectedOptions ? stoneSelect.selectedOptions[0] : null;
            const description = row ? row.querySelector('[name="stone_type[]"]') : null;
            const rate = row ? row.querySelector('.js-stone-rate') : null;
            if (option && stoneSelect.value) {
                if (description) description.value = option.getAttribute('data-description') || option.textContent.trim();
                if (rate) rate.value = n(option.getAttribute('data-rate')).toFixed(2);
            }
        }
        recalc();
    });
    modal.addEventListener('input', event => {
        const name = event.target instanceof Element ? event.target.closest('.js-dia-name') : null;
        if (name) name.dataset.edited = '1';
    });
    const receiveForm = document.getElementById('receive-form');
    if (receiveForm) {
        let submitting = false;
        modal.addEventListener('hide.bs.modal', event => {
            if (submitting) event.preventDefault();
        });
        receiveForm.addEventListener('submit', async event => {
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
                    const csrfInput = Array.from(receiveForm.elements).find(field => field.name === result.csrf.name);
                    if (csrfInput) csrfInput.value = result.csrf.hash;
                }
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to save the finished jewellery receipt.');
                }
                if (window.Swal) {
                    await window.Swal.fire({ icon: 'success', title: 'Completed', text: result.message || 'Finished jewellery received.' });
                }
                submitting = false;
                bootstrap.Modal.getOrCreateInstance(modal).hide();
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
    recalc();
})();
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
<?php if ($canDeleteOrder): ?>
<script>
    (function () {
        const form = document.getElementById('deleteOrderForm');
        if (!form) return;
        const expected = <?= json_encode((string) $order['order_no'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const orderNo = document.getElementById('confirm_order_no');
        const reason = document.getElementById('delete_reason');
        const acknowledgement = document.getElementById('confirm_permanent_delete');
        const submit = document.getElementById('confirmDeleteOrderButton');
        const refresh = function () {
            submit.disabled = orderNo.value.trim() !== expected
                || reason.value.trim().length < 5
                || !acknowledgement.checked;
        };
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        form.addEventListener('submit', function (event) {
            refresh();
            if (submit.disabled) event.preventDefault();
        });
    })();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
