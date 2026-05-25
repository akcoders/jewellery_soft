<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $vendorBalance = (float) ($summary['vendor_outstanding'] ?? 0);
    $karigarBalance = (float) ($summary['karigar_outstanding'] ?? 0);
    $customerBalance = (float) ($summary['customer_outstanding'] ?? 0);
    $expensePosted = (float) ($journalSummary['expenditure_amount'] ?? 0);

    $vendorCount = count($vendorRows ?? []);
    $karigarCount = count($karigarRows ?? []);
    $customerCount = count($customerRows ?? []);
    $totalPayable = $vendorBalance + $karigarBalance;

    $metricCards = [
        [
            'title' => 'Vendor Payable',
            'amount' => $vendorBalance,
            'hint' => $vendorCount . ' pending vendors',
            'url' => site_url('admin/accounts/party-balances/vendor'),
            'cta' => 'Review vendors',
            'icon' => 'fe-shopping-bag',
            'tone' => 'danger',
        ],
        [
            'title' => 'Karigar Payable',
            'amount' => $karigarBalance,
            'hint' => $karigarCount . ' pending karigars',
            'url' => site_url('admin/accounts/party-balances/karigar'),
            'cta' => 'Review karigars',
            'icon' => 'fe-tool',
            'tone' => 'gold',
        ],
        [
            'title' => 'Sales Receivable',
            'amount' => $customerBalance,
            'hint' => $customerCount . ' pending customers',
            'url' => site_url('admin/accounts/party-balances/customer'),
            'cta' => 'Review customers',
            'icon' => 'fe-credit-card',
            'tone' => 'success',
        ],
        [
            'title' => 'Expenditure Posted',
            'amount' => $expensePosted,
            'hint' => 'Posted journal expenses',
            'url' => site_url('admin/accounts/general-ledger?party_type=expense'),
            'cta' => 'Open expense ledger',
            'icon' => 'fe-file-text',
            'tone' => 'ink',
        ],
    ];

    $actions = [
        ['label' => 'Journal Voucher', 'hint' => 'Party transfer and expense entry', 'url' => site_url('admin/accounts/journal-vouchers'), 'icon' => 'fe-edit-3', 'primary' => true],
        ['label' => 'Payments', 'hint' => 'Vendor and karigar payments', 'url' => site_url('admin/accounts/payments'), 'icon' => 'fe-send', 'primary' => false],
        ['label' => 'All Ledgers', 'hint' => 'Full debit and credit book', 'url' => site_url('admin/accounts/general-ledger'), 'icon' => 'fe-list', 'primary' => false],
        ['label' => 'Issue Receive', 'hint' => 'Material movement ledger', 'url' => site_url('admin/accounts/vendor-transaction-ledger'), 'icon' => 'fe-repeat', 'primary' => false],
        ['label' => 'GST Report', 'hint' => 'Input, output and net GST', 'url' => site_url('admin/accounts/gst-report'), 'icon' => 'fe-percent', 'primary' => false],
        ['label' => 'Outstanding', 'hint' => 'All pending balances', 'url' => site_url('admin/accounts/outstanding-summary'), 'icon' => 'fe-bar-chart-2', 'primary' => false],
    ];

    $pendingSections = [
        ['title' => 'Vendor Pending', 'type' => 'vendor', 'rows' => $vendorRows ?? [], 'empty' => 'No pending vendors', 'url' => site_url('admin/accounts/party-balances/vendor')],
        ['title' => 'Karigar Pending', 'type' => 'karigar', 'rows' => $karigarRows ?? [], 'empty' => 'No pending karigars', 'url' => site_url('admin/accounts/party-balances/karigar')],
        ['title' => 'Customer Pending', 'type' => 'customer', 'rows' => $customerRows ?? [], 'empty' => 'No pending customers', 'url' => site_url('admin/accounts/party-balances/customer')],
    ];
?>

<style>
    .accounts-shell {
        --accounts-ink: #150028;
        --accounts-red: #b80f1d;
        --accounts-red-soft: #fff0f1;
        --accounts-gold: #b98714;
        --accounts-gold-soft: #fff7dd;
        --accounts-green: #0f7a55;
        --accounts-green-soft: #eafaf3;
        --accounts-blue-soft: #f4f7fb;
        --accounts-line: #e7ebf2;
        color: var(--accounts-ink);
    }
    .accounts-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #f0d8dc;
        border-radius: 22px;
        background:
            radial-gradient(circle at 86% 18%, rgba(255, 214, 128, 0.38), transparent 28%),
            linear-gradient(135deg, #fff8f1 0%, #fff 42%, #f8fbff 100%);
        box-shadow: 0 18px 45px rgba(32, 12, 24, 0.08);
    }
    .accounts-hero:after {
        content: "";
        position: absolute;
        right: -70px;
        bottom: -95px;
        width: 250px;
        height: 250px;
        border-radius: 999px;
        background: rgba(184, 15, 29, 0.08);
    }
    .accounts-hero .card-body {
        position: relative;
        z-index: 1;
    }
    .accounts-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(184, 15, 29, 0.08);
        color: var(--accounts-red);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .accounts-hero h3 {
        color: var(--accounts-ink);
        font-size: clamp(26px, 3vw, 38px);
        font-weight: 800;
        letter-spacing: -0.04em;
    }
    .accounts-hero-stat {
        border-left: 1px solid rgba(184, 15, 29, 0.18);
        padding-left: 22px;
    }
    .accounts-hero-stat small {
        color: #8a8196;
        font-weight: 600;
    }
    .accounts-hero-stat strong {
        display: block;
        color: var(--accounts-red);
        font-size: 26px;
        line-height: 1.15;
    }
    .metric-card {
        display: block;
        height: 100%;
        border: 1px solid var(--accounts-line);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 10px 28px rgba(28, 34, 48, 0.06);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .metric-card:hover {
        transform: translateY(-3px);
        border-color: rgba(184, 15, 29, 0.28);
        box-shadow: 0 18px 36px rgba(28, 34, 48, 0.10);
    }
    .metric-card .metric-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        font-size: 20px;
    }
    .metric-card[data-tone="danger"] .metric-icon { background: var(--accounts-red-soft); color: var(--accounts-red); }
    .metric-card[data-tone="gold"] .metric-icon { background: var(--accounts-gold-soft); color: var(--accounts-gold); }
    .metric-card[data-tone="success"] .metric-icon { background: var(--accounts-green-soft); color: var(--accounts-green); }
    .metric-card[data-tone="ink"] .metric-icon { background: #f1eef8; color: var(--accounts-ink); }
    .metric-title {
        color: #8a8196;
        font-size: 13px;
        font-weight: 700;
    }
    .metric-amount {
        color: var(--accounts-ink);
        font-size: clamp(22px, 2vw, 30px);
        font-weight: 800;
        letter-spacing: -0.03em;
    }
    .metric-cta {
        color: var(--accounts-red);
        font-size: 13px;
        font-weight: 700;
    }
    .quick-panel,
    .pending-card {
        border: 1px solid var(--accounts-line);
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(28, 34, 48, 0.05);
    }
    .quick-action {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 78px;
        padding: 14px;
        border: 1px solid var(--accounts-line);
        border-radius: 16px;
        background: #fff;
        color: var(--accounts-ink);
        transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }
    .quick-action:hover {
        transform: translateY(-2px);
        border-color: rgba(184, 15, 29, 0.32);
        background: #fffafa;
        color: var(--accounts-ink);
    }
    .quick-action.primary {
        background: linear-gradient(135deg, #b80f1d 0%, #7d0711 100%);
        color: #fff;
        border-color: transparent;
    }
    .quick-action .quick-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: var(--accounts-blue-soft);
        color: var(--accounts-red);
        flex: 0 0 auto;
    }
    .quick-action.primary .quick-icon {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }
    .quick-action strong {
        display: block;
        font-size: 14px;
    }
    .quick-action span {
        display: block;
        color: #8a8196;
        font-size: 12px;
        line-height: 1.3;
    }
    .quick-action.primary span {
        color: rgba(255, 255, 255, 0.76);
    }
    .pending-card .card-header {
        border-bottom: 1px solid var(--accounts-line);
        background: linear-gradient(180deg, #fff, #fbfcfe);
        border-radius: 18px 18px 0 0;
        padding: 18px 20px;
    }
    .pending-card h5 {
        color: var(--accounts-ink);
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .pending-table {
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    .pending-table thead th {
        border: 0;
        color: #9a92a8;
        font-size: 12px;
        font-weight: 800;
        padding: 0 12px 4px;
        text-transform: uppercase;
    }
    .pending-table tbody tr {
        background: #fff;
        box-shadow: 0 6px 16px rgba(20, 28, 42, 0.05);
    }
    .pending-table tbody td {
        border: 0;
        padding: 12px;
        vertical-align: middle;
    }
    .pending-table tbody td:first-child {
        border-radius: 12px 0 0 12px;
    }
    .pending-table tbody td:last-child {
        border-radius: 0 12px 12px 0;
    }
    .party-link {
        color: var(--accounts-red);
        font-weight: 700;
    }
    .party-link:hover {
        color: #7d0711;
    }
    .bill-pill {
        display: inline-flex;
        min-width: 34px;
        justify-content: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f3f5f9;
        color: var(--accounts-ink);
        font-weight: 700;
    }
    .amount-strong {
        color: var(--accounts-ink);
        font-weight: 800;
        white-space: nowrap;
    }
    .empty-state {
        padding: 32px 12px;
        color: #9a92a8;
        text-align: center;
    }
    @media (max-width: 991px) {
        .accounts-hero-stat {
            border-left: 0;
            padding-left: 0;
        }
    }
</style>

<div class="accounts-shell">
    <div class="card accounts-hero mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="accounts-eyebrow"><i class="fe fe-activity"></i> Accounts overview</span>
                    <h3 class="mt-3 mb-2">Clean balances first. Details one click away.</h3>
                    <p class="text-muted mb-0">Track payable, receivable, expenditure and party-wise pending accounts without crowding the dashboard.</p>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="accounts-hero-stat">
                                <small>Total Payable</small>
                                <strong>Rs <?= number_format($totalPayable, 2) ?></strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="accounts-hero-stat">
                                <small>Receivable</small>
                                <strong>Rs <?= number_format($customerBalance, 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($metricCards as $card): ?>
            <div class="col-xl-3 col-md-6">
                <a href="<?= esc((string) $card['url']) ?>" class="metric-card text-decoration-none text-reset" data-tone="<?= esc((string) $card['tone']) ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <div class="metric-title"><?= esc((string) $card['title']) ?></div>
                                <div class="metric-amount mt-1">Rs <?= number_format((float) $card['amount'], 2) ?></div>
                            </div>
                            <span class="metric-icon"><i class="fe <?= esc((string) $card['icon']) ?>"></i></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><?= esc((string) $card['hint']) ?></span>
                            <span class="metric-cta"><?= esc((string) $card['cta']) ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card quick-panel mb-4">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Fast Accounts Actions</h5>
                    <small class="text-muted">Common work stays visible. Deeper reports remain one click away.</small>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($actions as $action): ?>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="<?= esc((string) $action['url']) ?>" class="quick-action text-decoration-none <?= ! empty($action['primary']) ? 'primary' : '' ?>">
                            <span class="quick-icon"><i class="fe <?= esc((string) $action['icon']) ?>"></i></span>
                            <span>
                                <strong><?= esc((string) $action['label']) ?></strong>
                                <span><?= esc((string) $action['hint']) ?></span>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($pendingSections as $section): ?>
            <div class="col-xl-4">
                <div class="card pending-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?= esc((string) $section['title']) ?></h5>
                            <small class="text-muted">Top pending accounts</small>
                        </div>
                        <a href="<?= esc((string) $section['url']) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table pending-table mb-0" data-dt-skip="1">
                                <thead>
                                    <tr>
                                        <th>Party</th>
                                        <th class="text-center">Bills</th>
                                        <th class="text-end">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($section['rows'] ?? []) as $row): ?>
                                    <?php $partyId = (int) ($row['party_id'] ?? 0); ?>
                                    <tr>
                                        <td>
                                            <?php if ($partyId > 0): ?>
                                                <a class="party-link" href="<?= site_url('admin/accounts/party-ledger/' . (string) $section['type'] . '/' . $partyId) ?>"><?= esc((string) ($row['party_name'] ?? '-')) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted"><?= esc((string) ($row['party_name'] ?? '-')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="bill-pill"><?= (int) ($row['bill_count'] ?? 0) ?></span></td>
                                        <td class="text-end"><span class="amount-strong">Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($section['rows'] ?? []) === []): ?>
                                    <tr><td colspan="3"><div class="empty-state"><?= esc((string) $section['empty']) ?></div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
