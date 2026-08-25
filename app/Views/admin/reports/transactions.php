<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$groupMeta = static function (string $group): array {
    return match ($group) {
        'Purchase' => ['purchase', 'fe-shopping-bag'],
        'Issuement' => ['issue', 'fe-arrow-up-right'],
        'Receiving' => ['receive', 'fe-arrow-down-left'],
        'Return' => ['return', 'fe-rotate-ccw'],
        'Payment' => ['payment', 'fe-credit-card'],
        'Opening' => ['opening', 'fe-book-open'],
        'Adjustment' => ['adjustment', 'fe-sliders'],
        'Labour' => ['labour', 'fe-tool'],
        'Showroom' => ['showroom', 'fe-home'],
        'Order' => ['order', 'fe-package'],
        default => ['other', 'fe-activity'],
    };
};
$materialClass = static function (string $material): string {
    return match (strtolower($material)) {
        'gold' => 'gold',
        'diamond' => 'diamond',
        'stone' => 'stone',
        'finished jewellery', 'fg' => 'finished',
        'accounts' => 'accounts',
        'order' => 'order',
        default => 'neutral',
    };
};
$statusClass = static function (string $status): string {
    $value = strtolower($status);
    if (str_contains($value, 'paid') || str_contains($value, 'received') || str_contains($value, 'completed') || str_contains($value, 'posted')) {
        return 'success';
    }
    if (str_contains($value, 'pending') || str_contains($value, 'partial') || str_contains($value, 'progress')) {
        return 'warning';
    }
    if (str_contains($value, 'cancel') || str_contains($value, 'overdue')) {
        return 'danger';
    }
    if (str_contains($value, 'issue') || str_contains($value, 'return')) {
        return 'info';
    }
    return 'neutral';
};
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;
    return $timestamp ? date('d M Y', $timestamp) : '-';
};
$activeFilterCount = 0;
foreach (['from', 'to', 'transaction_group', 'transaction_type', 'material_type', 'party_type', 'order_no', 'status', 'search'] as $filterKey) {
    $activeFilterCount += trim((string) ($filters[$filterKey] ?? '')) !== '' ? 1 : 0;
}
foreach (['karigar_id', 'customer_id', 'vendor_id'] as $filterKey) {
    $activeFilterCount += (int) ($filters[$filterKey] ?? 0) > 0 ? 1 : 0;
}
?>

<style>
    .tx-page { --tx-red: #be1825; --tx-ink: #172033; --tx-muted: #667085; --tx-line: #e6eaf0; }
    .tx-hero { align-items: center; background: linear-gradient(118deg, #fff 0%, #fff9ef 70%, #fdf2f3 100%); border: 1px solid #eee5d8; border-left: 4px solid var(--tx-red); border-radius: 16px; display: flex; justify-content: space-between; margin-bottom: 18px; overflow: hidden; padding: 22px 24px; position: relative; }
    .tx-hero::after { background: rgba(194, 150, 36, .08); border-radius: 50%; content: ''; height: 150px; position: absolute; right: -35px; top: -65px; width: 150px; }
    .tx-eyebrow { color: #9a7216; font-size: 10px; font-weight: 800; letter-spacing: .15em; margin-bottom: 5px; text-transform: uppercase; }
    .tx-hero h2 { color: var(--tx-ink); font-size: 25px; font-weight: 780; letter-spacing: -.025em; margin: 0; }
    .tx-hero p { color: var(--tx-muted); font-size: 13px; margin: 6px 0 0; }
    .tx-live { align-items: center; background: #ecfdf3; border: 1px solid #abefc6; border-radius: 999px; color: #067647; display: inline-flex; font-size: 11px; font-weight: 750; gap: 7px; padding: 8px 12px; position: relative; z-index: 1; }
    .tx-live-dot { background: #17b26a; border-radius: 50%; box-shadow: 0 0 0 4px rgba(23, 178, 106, .12); height: 7px; width: 7px; }
    .tx-filter-panel { background: #fff; border: 1px solid var(--tx-line); border-radius: 16px; box-shadow: 0 8px 24px rgba(16, 24, 40, .04); margin-bottom: 18px; }
    .tx-filter-head { align-items: center; border-bottom: 1px solid #edf0f4; display: flex; justify-content: space-between; padding: 15px 18px; }
    .tx-filter-title { align-items: center; color: var(--tx-ink); display: flex; font-size: 14px; font-weight: 750; gap: 9px; margin: 0; }
    .tx-filter-title i { color: var(--tx-red); }
    .tx-filter-count { background: #fef3f2; border-radius: 999px; color: #b42318; font-size: 10px; font-weight: 750; padding: 4px 8px; }
    .tx-filter-body { padding: 16px 18px 18px; }
    .tx-filter-body .form-label { color: #475467; font-size: 10px; font-weight: 750; letter-spacing: .035em; margin-bottom: 5px; text-transform: uppercase; }
    .tx-filter-body .form-control, .tx-filter-body .form-select { border-color: #d9dee7; border-radius: 9px; font-size: 12px; min-height: 40px; }
    .tx-filter-body .form-control:focus, .tx-filter-body .form-select:focus { border-color: #d97179; box-shadow: 0 0 0 3px rgba(190, 24, 37, .08); }
    .tx-date-hint { color: #98a2b3; font-size: 10px; margin-top: 5px; }
    .tx-filter-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .tx-filter-actions .btn { border-radius: 9px; font-size: 12px; font-weight: 700; min-height: 40px; padding: 9px 16px; }
    .tx-stats-grid { display: grid; gap: 12px; grid-template-columns: repeat(6, minmax(0, 1fr)); margin-bottom: 14px; }
    .tx-stat { align-items: center; background: #fff; border: 1px solid var(--tx-line); border-radius: 14px; display: flex; gap: 11px; min-width: 0; padding: 14px; }
    .tx-stat-icon { align-items: center; border-radius: 11px; display: inline-flex; flex: 0 0 38px; font-size: 16px; height: 38px; justify-content: center; }
    .tx-stat-label { color: #667085; display: block; font-size: 10px; font-weight: 700; line-height: 1.2; }
    .tx-stat-value { color: var(--tx-ink); display: block; font-size: 19px; font-weight: 800; line-height: 1.25; margin-top: 2px; }
    .tx-stat-total .tx-stat-icon { background: #f2f4f7; color: #344054; }
    .tx-stat-purchase .tx-stat-icon { background: #fff7db; color: #9c6800; }
    .tx-stat-issue .tx-stat-icon { background: #fef3f2; color: #b42318; }
    .tx-stat-receive .tx-stat-icon { background: #ecfdf3; color: #067647; }
    .tx-stat-return .tx-stat-icon { background: #eef4ff; color: #3538cd; }
    .tx-stat-payment .tx-stat-icon { background: #f4f3ff; color: #5925dc; }
    .tx-value-strip { align-items: stretch; background: #182230; border-radius: 14px; color: #fff; display: grid; grid-template-columns: 1.5fr repeat(3, 1fr); margin-bottom: 18px; overflow: hidden; }
    .tx-value-item { border-right: 1px solid rgba(255,255,255,.1); padding: 15px 18px; }
    .tx-value-item:last-child { border-right: 0; }
    .tx-value-label { color: #98a2b3; display: block; font-size: 10px; font-weight: 700; margin-bottom: 3px; text-transform: uppercase; }
    .tx-value-number { font-size: 16px; font-weight: 750; white-space: nowrap; }
    .tx-value-item:first-child .tx-value-number { color: #f4cf6b; font-size: 18px; }
    .tx-mix { align-items: center; display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 18px; }
    .tx-mix-label { color: #667085; font-size: 10px; font-weight: 750; letter-spacing: .04em; margin-right: 3px; text-transform: uppercase; }
    .tx-mix-chip { background: #fff; border: 1px solid #e4e7ec; border-radius: 999px; color: #475467; font-size: 10px; font-weight: 650; padding: 6px 9px; }
    .tx-mix-chip strong { color: #101828; margin-left: 4px; }
    .tx-register { background: #fff; border: 1px solid var(--tx-line); border-radius: 16px; box-shadow: 0 10px 28px rgba(16, 24, 40, .045); overflow: hidden; }
    .tx-register-head { align-items: center; border-bottom: 1px solid #e9edf2; display: flex; justify-content: space-between; padding: 16px 18px; }
    .tx-register-head h5 { color: var(--tx-ink); font-size: 15px; font-weight: 780; margin: 0; }
    .tx-register-head p { color: #98a2b3; font-size: 11px; margin: 3px 0 0; }
    .tx-register-count { background: #f2f4f7; border-radius: 8px; color: #344054; font-size: 11px; font-weight: 750; padding: 7px 10px; }
    .tx-table-wrap { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
    .tx-table { margin: 0; min-width: 1420px; width: 100%; }
    .tx-table thead th { background: #f8fafc; border-bottom: 1px solid #dfe4eb; color: #475467; font-size: 10px; font-weight: 800; letter-spacing: .035em; padding: 11px 12px; text-transform: uppercase; white-space: nowrap; }
    .tx-table tbody td { border-color: #edf0f4; color: #344054; font-size: 11px; padding: 12px; vertical-align: middle; }
    .tx-table tbody tr:hover td { background: #fffdf8; }
    .tx-date { color: #101828; font-weight: 700; white-space: nowrap; }
    .tx-activity { align-items: center; border-radius: 999px; display: inline-flex; font-size: 10px; font-weight: 750; gap: 5px; padding: 6px 9px; white-space: nowrap; }
    .tx-activity.purchase { background: #fff7db; color: #805600; }
    .tx-activity.issue { background: #fef3f2; color: #b42318; }
    .tx-activity.receive { background: #ecfdf3; color: #067647; }
    .tx-activity.return { background: #eef4ff; color: #3538cd; }
    .tx-activity.payment { background: #f4f3ff; color: #5925dc; }
    .tx-activity.opening { background: #f2f4f7; color: #344054; }
    .tx-activity.adjustment { background: #fff4ed; color: #b93815; }
    .tx-activity.labour { background: #fdf2fa; color: #c11574; }
    .tx-activity.showroom { background: #ecfdff; color: #0e7090; }
    .tx-activity.order { background: #eff8ff; color: #175cd3; }
    .tx-activity.other { background: #f2f4f7; color: #475467; }
    .tx-type-name { color: #667085; font-size: 9px; font-weight: 650; margin-top: 5px; white-space: nowrap; }
    .tx-material { border: 1px solid transparent; border-radius: 7px; display: inline-flex; font-size: 9px; font-weight: 800; padding: 5px 7px; text-transform: uppercase; white-space: nowrap; }
    .tx-material.gold { background: #fff8dc; border-color: #f4dc8d; color: #8a6100; }
    .tx-material.diamond { background: #eef4ff; border-color: #c7d7fe; color: #3538cd; }
    .tx-material.stone { background: #fdf2fa; border-color: #fcceee; color: #9e165f; }
    .tx-material.finished { background: #ecfdf3; border-color: #abefc6; color: #067647; }
    .tx-material.accounts { background: #f4f3ff; border-color: #d9d6fe; color: #5925dc; }
    .tx-material.order { background: #eff8ff; border-color: #b2ddff; color: #175cd3; }
    .tx-material.neutral { background: #f2f4f7; border-color: #e4e7ec; color: #475467; }
    .tx-reference { color: #101828; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; font-weight: 700; }
    .tx-order { color: var(--tx-red); font-weight: 700; }
    .tx-party-name { color: #101828; font-weight: 700; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tx-party-type { color: #98a2b3; font-size: 9px; margin-top: 3px; }
    .tx-status { align-items: center; border-radius: 999px; display: inline-flex; font-size: 9px; font-weight: 750; gap: 5px; padding: 5px 8px; white-space: nowrap; }
    .tx-status::before { background: currentColor; border-radius: 50%; content: ''; height: 5px; opacity: .75; width: 5px; }
    .tx-status.success { background: #ecfdf3; color: #067647; }
    .tx-status.warning { background: #fffaeb; color: #b54708; }
    .tx-status.danger { background: #fef3f2; color: #b42318; }
    .tx-status.info { background: #eff8ff; color: #175cd3; }
    .tx-status.neutral { background: #f2f4f7; color: #475467; }
    .tx-weight-list { display: flex; flex-wrap: wrap; gap: 5px; min-width: 170px; }
    .tx-weight { background: #f8fafc; border: 1px solid #e4e7ec; border-radius: 6px; color: #475467; font-size: 9px; font-weight: 650; padding: 4px 6px; white-space: nowrap; }
    .tx-weight.gold { color: #8a6100; }
    .tx-weight.diamond { color: #3538cd; }
    .tx-weight.stone { color: #9e165f; }
    .tx-empty { color: #b0b7c3; }
    .tx-amount { color: #101828; font-weight: 800; white-space: nowrap; }
    .tx-note { color: #667085; display: block; max-width: 230px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tx-register .dataTables_wrapper { padding: 14px 16px 16px; }
    .tx-register .dataTables_wrapper .tx-table-wrap { margin-left: -16px; width: calc(100% + 32px); }
    @media (max-width: 1199.98px) { .tx-stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) {
        .tx-hero { align-items: flex-start; flex-direction: column; gap: 14px; padding: 18px; }
        .tx-hero h2 { font-size: 21px; }
        .tx-filter-head, .tx-filter-body { padding-left: 14px; padding-right: 14px; }
        .tx-filter-actions { justify-content: stretch; }
        .tx-filter-actions .btn { flex: 1; }
        .tx-stats-grid { gap: 8px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .tx-stat { padding: 11px; }
        .tx-stat-icon { flex-basis: 34px; height: 34px; }
        .tx-stat-value { font-size: 17px; }
        .tx-value-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .tx-value-item { border-bottom: 1px solid rgba(255,255,255,.1); padding: 12px 14px; }
        .tx-value-number { font-size: 14px; }
        .tx-register-head { align-items: flex-start; padding: 14px; }
        .tx-register .dataTables_wrapper { padding: 12px; }
        .tx-register .dataTables_wrapper .tx-table-wrap { margin-left: -12px; width: calc(100% + 24px); }
    }
</style>

<div class="tx-page">
    <section class="tx-hero">
        <div><div class="tx-eyebrow">Reports · Unified Register</div><h2>All Transactions</h2><p>Purchases, issuements, receiving, returns and payments—one live history across every account.</p></div>
        <span class="tx-live"><span class="tx-live-dot"></span>Auto-synced from live entries</span>
    </section>

    <section class="tx-filter-panel">
        <div class="tx-filter-head"><h5 class="tx-filter-title"><i class="fe fe-filter"></i>Filter transaction history</h5><?php if ($activeFilterCount > 0): ?><span class="tx-filter-count"><?= $activeFilterCount ?> active</span><?php endif; ?></div>
        <div class="tx-filter-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">From date</label><input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>"><div class="tx-date-hint">Blank shows all history</div></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">To date</label><input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>"><div class="tx-date-hint">Including selected date</div></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Activity</label><select name="transaction_group" class="form-select js-searchable-select" data-placeholder="All activities"><option value="">All activities</option><?php foreach (($transactionGroups ?? []) as $group): ?><option value="<?= esc($group) ?>" <?= ($filters['transaction_group'] ?? '') === $group ? 'selected' : '' ?>><?= esc($group) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Transaction type</label><select name="transaction_type" class="form-select js-searchable-select" data-placeholder="All types"><option value="">All types</option><?php foreach (($transactionTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['transaction_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Material</label><select name="material_type" class="form-select js-searchable-select" data-placeholder="All materials"><option value="">All materials</option><?php foreach (($materialTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['material_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Party type</label><select name="party_type" class="form-select js-searchable-select" data-placeholder="All parties"><option value="">All parties</option><?php foreach (($partyTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['party_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Karigar</label><select name="karigar_id" class="form-select js-searchable-select" data-placeholder="All karigars"><option value="0">All karigars</option><?php foreach (($karigars ?? []) as $karigar): ?><option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Customer</label><select name="customer_id" class="form-select js-searchable-select" data-placeholder="All customers"><option value="0">All customers</option><?php foreach (($customers ?? []) as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= esc((string) $customer['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Vendor</label><select name="vendor_id" class="form-select js-searchable-select" data-placeholder="All vendors"><option value="0">All vendors</option><?php foreach (($vendors ?? []) as $vendor): ?><option value="<?= (int) $vendor['id'] ?>" <?= (int) ($filters['vendor_id'] ?? 0) === (int) $vendor['id'] ? 'selected' : '' ?>><?= esc((string) $vendor['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Status</label><select name="status" class="form-select js-searchable-select" data-placeholder="All statuses"><option value="">All statuses</option><?php foreach (($statuses ?? []) as $status): ?><option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc($status) ?></option><?php endforeach; ?></select></div>
                <div class="col-xl-2 col-md-4 col-6"><label class="form-label">Order number</label><input type="text" name="order_no" class="form-control" value="<?= esc((string) ($filters['order_no'] ?? '')) ?>" placeholder="Search order"></div>
                <div class="col-xl-3 col-md-5"><label class="form-label">Global search</label><input type="text" name="search" class="form-control" value="<?= esc((string) ($filters['search'] ?? '')) ?>" placeholder="Reference, party, type or notes"></div>
                <div class="col-xl-3 col-md-7 tx-filter-actions"><button type="submit" class="btn btn-primary"><i class="fe fe-search me-1"></i>Apply filters</button><a href="<?= site_url('admin/reports/transactions') ?>" class="btn btn-outline-secondary"><i class="fe fe-rotate-ccw me-1"></i>Reset</a></div>
            </form>
        </div>
    </section>

    <section class="tx-stats-grid">
        <div class="tx-stat tx-stat-total"><span class="tx-stat-icon"><i class="fe fe-layers"></i></span><span><span class="tx-stat-label">Transactions</span><strong class="tx-stat-value"><?= number_format((int) ($cards['row_count'] ?? 0)) ?></strong></span></div>
        <div class="tx-stat tx-stat-purchase"><span class="tx-stat-icon"><i class="fe fe-shopping-bag"></i></span><span><span class="tx-stat-label">Purchases</span><strong class="tx-stat-value"><?= number_format((int) ($groupCounts['Purchase'] ?? 0)) ?></strong></span></div>
        <div class="tx-stat tx-stat-issue"><span class="tx-stat-icon"><i class="fe fe-arrow-up-right"></i></span><span><span class="tx-stat-label">Issuements</span><strong class="tx-stat-value"><?= number_format((int) ($groupCounts['Issuement'] ?? 0)) ?></strong></span></div>
        <div class="tx-stat tx-stat-receive"><span class="tx-stat-icon"><i class="fe fe-arrow-down-left"></i></span><span><span class="tx-stat-label">Receivings</span><strong class="tx-stat-value"><?= number_format((int) ($groupCounts['Receiving'] ?? 0)) ?></strong></span></div>
        <div class="tx-stat tx-stat-return"><span class="tx-stat-icon"><i class="fe fe-rotate-ccw"></i></span><span><span class="tx-stat-label">Returns</span><strong class="tx-stat-value"><?= number_format((int) ($groupCounts['Return'] ?? 0)) ?></strong></span></div>
        <div class="tx-stat tx-stat-payment"><span class="tx-stat-icon"><i class="fe fe-credit-card"></i></span><span><span class="tx-stat-label">Payments</span><strong class="tx-stat-value"><?= number_format((int) ($groupCounts['Payment'] ?? 0)) ?></strong></span></div>
    </section>

    <section class="tx-value-strip">
        <div class="tx-value-item"><span class="tx-value-label">Transaction value</span><strong class="tx-value-number">₹<?= number_format((float) ($cards['amount_total'] ?? 0), 2) ?></strong></div>
        <div class="tx-value-item"><span class="tx-value-label">Gold movement</span><strong class="tx-value-number"><?= number_format((float) ($cards['gold_total'] ?? 0), 3) ?> gm</strong></div>
        <div class="tx-value-item"><span class="tx-value-label">Diamond movement</span><strong class="tx-value-number"><?= number_format((float) ($cards['diamond_total'] ?? 0), 3) ?> cts</strong></div>
        <div class="tx-value-item"><span class="tx-value-label">Stone / quantity</span><strong class="tx-value-number"><?= number_format((float) ($cards['stone_total'] ?? 0), 3) ?></strong></div>
    </section>

    <?php if (($typeCounts ?? []) !== []): ?><div class="tx-mix"><span class="tx-mix-label">Visible mix</span><?php foreach (($typeCounts ?? []) as $label => $count): ?><span class="tx-mix-chip"><?= esc((string) $label) ?><strong><?= (int) $count ?></strong></span><?php endforeach; ?></div><?php endif; ?>

    <section class="tx-register">
        <div class="tx-register-head"><div><h5>Transaction Register</h5><p>Complete filtered activity, newest transaction first</p></div><span class="tx-register-count"><?= number_format((int) ($cards['row_count'] ?? 0)) ?> records</span></div>
        <div class="tx-table-wrap">
            <table class="table datatable table-hover align-middle tx-table" data-dt-page-length="25">
                <thead><tr><th>Date</th><th>Activity</th><th>Material</th><th>Reference</th><th>Order</th><th>Party</th><th>Status</th><th>Movement details</th><th>Amount</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?><tr><td colspan="10" class="text-center text-muted py-5">No transactions match the selected filters.</td></tr><?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <?php
                        $group = (string) ($row['transaction_group'] ?? 'Other');
                        [$groupClass, $groupIcon] = $groupMeta($group);
                        $material = (string) ($row['material_type'] ?? '-');
                        $status = (string) ($row['status'] ?? '-');
                        $gold = (float) ($row['gold_gm'] ?? 0);
                        $diamond = (float) ($row['diamond_cts'] ?? 0);
                        $stone = (float) ($row['stone_qty'] ?? 0);
                        $amount = (float) ($row['amount'] ?? 0);
                        $quantityLabel = match (strtolower($material)) {
                            'diamond' => 'Pcs',
                            'stone', 'finished jewellery' => 'Stone',
                            default => 'Qty',
                        };
                        ?>
                        <tr>
                            <td><span class="tx-date"><?= esc($formatDate((string) ($row['transaction_date'] ?? ''))) ?></span></td>
                            <td><span class="tx-activity <?= esc($groupClass) ?>"><i class="fe <?= esc($groupIcon) ?>"></i><?= esc($group) ?></span><div class="tx-type-name"><?= esc((string) ($row['transaction_type'] ?? '-')) ?></div></td>
                            <td><span class="tx-material <?= esc($materialClass($material)) ?>"><?= esc($material) ?></span></td>
                            <td><span class="tx-reference"><?= esc((string) ($row['reference_no'] ?? '-')) ?></span></td>
                            <td><?php if (trim((string) ($row['order_no'] ?? '')) !== ''): ?><span class="tx-order"><?= esc((string) $row['order_no']) ?></span><?php else: ?><span class="tx-empty">—</span><?php endif; ?></td>
                            <td><div class="tx-party-name" title="<?= esc((string) ($row['party_name'] ?? '-'), 'attr') ?>"><?= esc((string) ($row['party_name'] ?? '-')) ?></div><div class="tx-party-type"><?= esc((string) ($row['party_type'] ?? 'Other')) ?></div></td>
                            <td><span class="tx-status <?= esc($statusClass($status)) ?>"><?= esc($status) ?></span></td>
                            <td><div class="tx-weight-list"><?php if ($gold != 0.0): ?><span class="tx-weight gold">Au <?= number_format($gold, 3) ?> gm</span><?php endif; ?><?php if ($diamond != 0.0): ?><span class="tx-weight diamond">Dia <?= number_format($diamond, 3) ?> ct</span><?php endif; ?><?php if ($stone != 0.0): ?><span class="tx-weight stone"><?= esc($quantityLabel) ?> <?= number_format($stone, 3) ?></span><?php endif; ?><?php if ($gold == 0.0 && $diamond == 0.0 && $stone == 0.0): ?><span class="tx-empty">—</span><?php endif; ?></div></td>
                            <td><?php if ($amount != 0.0): ?><span class="tx-amount">₹<?= number_format($amount, 2) ?></span><?php else: ?><span class="tx-empty">—</span><?php endif; ?></td>
                            <td><span class="tx-note" title="<?= esc((string) ($row['notes'] ?? '-'), 'attr') ?>"><?= esc(trim((string) ($row['notes'] ?? '')) ?: '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
