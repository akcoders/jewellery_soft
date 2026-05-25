<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $vendorBalance = (float) ($summary['vendor_outstanding'] ?? 0);
    $karigarBalance = (float) ($summary['karigar_outstanding'] ?? 0);
    $customerBalance = (float) ($summary['customer_outstanding'] ?? 0);
    $expensePosted = (float) ($journalSummary['expenditure_amount'] ?? 0);

    $vendorRows = $vendorRows ?? [];
    $karigarRows = $karigarRows ?? [];
    $customerRows = $customerRows ?? [];
    $totalPayable = $vendorBalance + $karigarBalance;
    $pendingParties = count($vendorRows) + count($karigarRows) + count($customerRows);

    $statCards = [
        ['label' => 'Total Payable', 'value' => $totalPayable, 'sub' => 'Vendor + Karigar', 'icon' => 'fe-trending-down', 'url' => site_url('admin/accounts/outstanding-summary'), 'tone' => 'red'],
        ['label' => 'Vendor Payable', 'value' => $vendorBalance, 'sub' => count($vendorRows) . ' vendors pending', 'icon' => 'fe-shopping-bag', 'url' => site_url('admin/accounts/party-balances/vendor'), 'tone' => 'red'],
        ['label' => 'Karigar Payable', 'value' => $karigarBalance, 'sub' => count($karigarRows) . ' karigars pending', 'icon' => 'fe-tool', 'url' => site_url('admin/accounts/party-balances/karigar'), 'tone' => 'gold'],
        ['label' => 'Sales Receivable', 'value' => $customerBalance, 'sub' => count($customerRows) . ' customers pending', 'icon' => 'fe-credit-card', 'url' => site_url('admin/accounts/party-balances/customer'), 'tone' => 'green'],
        ['label' => 'Expenditure', 'value' => $expensePosted, 'sub' => 'Posted JV expenses', 'icon' => 'fe-file-text', 'url' => site_url('admin/accounts/general-ledger?party_type=expense'), 'tone' => 'blue'],
    ];

    $actions = [
        ['label' => 'Journal Voucher', 'url' => site_url('admin/accounts/journal-vouchers'), 'icon' => 'fe-edit-3', 'primary' => true],
        ['label' => 'Payments', 'url' => site_url('admin/accounts/payments'), 'icon' => 'fe-send'],
        ['label' => 'All Ledger', 'url' => site_url('admin/accounts/general-ledger'), 'icon' => 'fe-list'],
        ['label' => 'Issue Receive', 'url' => site_url('admin/accounts/vendor-transaction-ledger'), 'icon' => 'fe-repeat'],
        ['label' => 'GST Report', 'url' => site_url('admin/accounts/gst-report'), 'icon' => 'fe-percent'],
        ['label' => 'Outstanding', 'url' => site_url('admin/accounts/outstanding-summary'), 'icon' => 'fe-bar-chart-2'],
    ];

    $pendingSections = [
        ['title' => 'Vendor Pending', 'type' => 'vendor', 'rows' => $vendorRows, 'empty' => 'No pending vendors', 'url' => site_url('admin/accounts/party-balances/vendor'), 'total' => $vendorBalance],
        ['title' => 'Karigar Pending', 'type' => 'karigar', 'rows' => $karigarRows, 'empty' => 'No pending karigars', 'url' => site_url('admin/accounts/party-balances/karigar'), 'total' => $karigarBalance],
        ['title' => 'Customer Pending', 'type' => 'customer', 'rows' => $customerRows, 'empty' => 'No pending customers', 'url' => site_url('admin/accounts/party-balances/customer'), 'total' => $customerBalance],
    ];
?>

<style>
    .accounts-dashboard {
        --acc-ink: #120022;
        --acc-muted: #7f7890;
        --acc-red: #b80f1d;
        --acc-red-dark: #850712;
        --acc-gold: #bb8a13;
        --acc-green: #0c7a54;
        --acc-blue: #3156c9;
        --acc-line: #e5eaf2;
        --acc-soft: #f7f9fc;
        color: var(--acc-ink);
    }
    .accounts-topbar {
        border: 1px solid #f0d5d8;
        border-radius: 18px;
        background:
            radial-gradient(circle at top right, rgba(255, 217, 123, 0.32), transparent 28%),
            linear-gradient(135deg, #fff9f4 0%, #ffffff 50%, #f7fbff 100%);
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
    }
    .accounts-topbar h4 {
        color: var(--acc-ink);
        font-weight: 800;
        letter-spacing: -0.03em;
    }
    .mini-stat {
        border-left: 1px solid rgba(184, 15, 29, 0.16);
        padding-left: 18px;
    }
    .mini-stat span {
        display: block;
        color: var(--acc-muted);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .mini-stat strong {
        color: var(--acc-red);
        font-size: 22px;
        font-weight: 800;
        line-height: 1.15;
    }
    .stat-card {
        display: block;
        height: 100%;
        border: 1px solid var(--acc-line);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.055);
        overflow: hidden;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }
    .stat-card:before {
        content: "";
        display: block;
        height: 4px;
        background: var(--acc-red);
    }
    .stat-card[data-tone="gold"]:before { background: var(--acc-gold); }
    .stat-card[data-tone="green"]:before { background: var(--acc-green); }
    .stat-card[data-tone="blue"]:before { background: var(--acc-blue); }
    .stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(184, 15, 29, 0.28);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.09);
    }
    .stat-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: #fff0f1;
        color: var(--acc-red);
        font-size: 18px;
    }
    .stat-card[data-tone="gold"] .stat-icon { background: #fff7dd; color: var(--acc-gold); }
    .stat-card[data-tone="green"] .stat-icon { background: #eafaf3; color: var(--acc-green); }
    .stat-card[data-tone="blue"] .stat-icon { background: #eef3ff; color: var(--acc-blue); }
    .stat-label {
        color: var(--acc-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .stat-value {
        color: var(--acc-ink);
        font-size: clamp(20px, 1.8vw, 28px);
        font-weight: 850;
        letter-spacing: -0.03em;
        white-space: nowrap;
    }
    .stat-sub {
        color: var(--acc-muted);
        font-size: 12px;
        font-weight: 600;
    }
    .action-card,
    .pending-card {
        border: 1px solid var(--acc-line);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    .action-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px;
    }
    .action-tile {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 48px;
        border: 1px solid var(--acc-line);
        border-radius: 13px;
        background: var(--acc-soft);
        color: var(--acc-ink);
        font-weight: 800;
        text-align: center;
        transition: transform 0.16s ease, border-color 0.16s ease, background 0.16s ease;
    }
    .action-tile:hover {
        transform: translateY(-1px);
        border-color: rgba(184, 15, 29, 0.35);
        background: #fff7f8;
        color: var(--acc-red-dark);
    }
    .action-tile.primary {
        border-color: transparent;
        background: linear-gradient(135deg, var(--acc-red), var(--acc-red-dark));
        color: #fff;
        box-shadow: 0 10px 20px rgba(184, 15, 29, 0.22);
    }
    .pending-card .card-header {
        padding: 16px 18px;
        border-bottom: 1px solid var(--acc-line);
        background: linear-gradient(180deg, #fff, #fbfcff);
        border-radius: 16px 16px 0 0;
    }
    .pending-card h5 {
        color: var(--acc-ink);
        font-weight: 850;
        letter-spacing: -0.025em;
    }
    .pending-total {
        color: var(--acc-muted);
        font-size: 12px;
        font-weight: 700;
    }
    .pending-table-wrap {
        max-height: 438px;
        overflow: auto;
    }
    .pending-table {
        min-width: 460px;
        margin: 0;
    }
    .pending-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        border: 0;
        background: #f1f4fb;
        color: #5c5870;
        font-size: 11px;
        font-weight: 850;
        letter-spacing: 0.04em;
        padding: 10px 14px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .pending-table tbody td {
        border-top: 1px solid #eef2f7;
        padding: 12px 14px;
        vertical-align: middle;
        background: #fff;
    }
    .pending-table tbody tr:nth-child(odd) td {
        background: #fbfcff;
    }
    .pending-table tbody tr:hover td {
        background: #fff7f8;
    }
    .party-link {
        color: var(--acc-red);
        font-weight: 800;
    }
    .party-link:hover {
        color: var(--acc-red-dark);
    }
    .bill-pill {
        display: inline-flex;
        min-width: 34px;
        justify-content: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: #eef2f8;
        color: var(--acc-ink);
        font-weight: 850;
    }
    .amount-strong {
        color: var(--acc-ink);
        font-weight: 850;
        white-space: nowrap;
    }
    .empty-row {
        height: 96px;
        color: var(--acc-muted);
        text-align: center;
    }
    @media (max-width: 1399px) {
        .action-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 767px) {
        .accounts-topbar .mini-stat {
            border-left: 0;
            padding-left: 0;
        }
        .action-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .stat-value {
            white-space: normal;
        }
    }
    @media (max-width: 420px) {
        .action-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="accounts-dashboard">
    <div class="accounts-topbar p-3 p-lg-4 mb-3">
        <div class="row g-3 align-items-center">
            <div class="col-xl-5 col-lg-4">
                <h4 class="mb-1">Key Details</h4>
                <div class="text-muted small">Today: <?= esc(date('d M Y')) ?></div>
            </div>
            <div class="col-xl-7 col-lg-8">
                <div class="row g-3">
                    <div class="col-sm-4">
                        <div class="mini-stat">
                            <span>Payable</span>
                            <strong>Rs <?= number_format($totalPayable, 2) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mini-stat">
                            <span>Receivable</span>
                            <strong>Rs <?= number_format($customerBalance, 2) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mini-stat">
                            <span>Pending Parties</span>
                            <strong><?= (int) $pendingParties ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ($statCards as $card): ?>
            <div class="col-xxl col-xl-4 col-md-6">
                <a href="<?= esc((string) $card['url']) ?>" class="stat-card text-decoration-none text-reset" data-tone="<?= esc((string) $card['tone']) ?>">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <div class="stat-label"><?= esc((string) $card['label']) ?></div>
                                <div class="stat-value">Rs <?= number_format((float) $card['value'], 2) ?></div>
                            </div>
                            <span class="stat-icon"><i class="fe <?= esc((string) $card['icon']) ?>"></i></span>
                        </div>
                        <div class="stat-sub"><?= esc((string) $card['sub']) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="action-card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Quick Actions</h5>
            <span class="text-muted small">Accounts tools</span>
        </div>
        <div class="action-grid">
            <?php foreach ($actions as $action): ?>
                <a href="<?= esc((string) $action['url']) ?>" class="action-tile text-decoration-none <?= ! empty($action['primary']) ? 'primary' : '' ?>">
                    <i class="fe <?= esc((string) $action['icon']) ?>"></i>
                    <span><?= esc((string) $action['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($pendingSections as $section): ?>
            <div class="col-xl-4">
                <div class="card pending-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1"><?= esc((string) $section['title']) ?></h5>
                            <div class="pending-total">Total: Rs <?= number_format((float) $section['total'], 2) ?></div>
                        </div>
                        <a href="<?= esc((string) $section['url']) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="pending-table-wrap">
                        <table class="table pending-table" data-dt-skip="1">
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
                                <tr><td colspan="3" class="empty-row"><?= esc((string) $section['empty']) ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
