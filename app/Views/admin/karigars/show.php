<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .karigar-ledger-zone { margin-top: 1.5rem; }
    .karigar-ledger-heading {
        align-items: center;
        background: linear-gradient(135deg, #fff 0%, #fffaf0 100%);
        border: 1px solid #eadfc9;
        border-left: 4px solid var(--erp-gold);
        border-radius: 14px;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 18px 20px;
    }
    .karigar-ledger-heading h4 { color: #172235; font-size: 1.15rem; margin: 0 0 3px; }
    .karigar-ledger-heading p { color: #6b778c; font-size: .82rem; margin: 0; }
    .karigar-ledger-jump { display: flex; flex-wrap: wrap; gap: 7px; }
    .karigar-ledger-jump a {
        background: #fff;
        border: 1px solid #d9e0e8;
        border-radius: 999px;
        color: #344054;
        font-size: .75rem;
        font-weight: 700;
        padding: 7px 11px;
    }
    .karigar-ledger-jump a:hover { border-color: var(--erp-red); color: var(--erp-red); }
    .karigar-ledger-filter {
        background: #fff;
        border: 1px solid #dfe5ec;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(26, 39, 57, .05);
        padding: 15px 18px;
    }
    .karigar-ledger-card { border: 1px solid #dfe5ec !important; border-radius: 14px !important; overflow: hidden; scroll-margin-top: 90px; }
    .karigar-ledger-card .card-header {
        align-items: center;
        background: #fff;
        border-bottom: 1px solid var(--erp-border);
        display: flex;
        justify-content: space-between;
        padding: 15px 18px;
    }
    .karigar-ledger-card .card-header h5 { color: #172235; font-size: .98rem; font-weight: 800; }
    .karigar-ledger-card .card-header small { color: #748094; display: block; font-size: .72rem; font-weight: 500; margin-top: 3px; }
    .karigar-ledger-card .card-body { padding: 18px; }
    .karigar-ledger-card .table-responsive { max-width: 100%; }
    .karigar-ledger-table { min-width: 820px; width: 100% !important; }
    .karigar-ledger-table thead tr:first-child th { background: #f4f6f9 !important; color: #425066 !important; font-size: .69rem !important; letter-spacing: .035em; padding: .72rem .65rem !important; text-transform: uppercase; }
    .karigar-ledger-table tbody td { color: #354158; font-size: .78rem; padding: .68rem .65rem !important; vertical-align: middle; }
    .karigar-ledger-table tbody tr:nth-child(even) td { background: #fbfcfd; }
    .karigar-ledger-table .ledger-number { font-variant-numeric: tabular-nums; text-align: right; }
    .karigar-ledger-table .ledger-balance { background: #f5f3ff !important; color: #382e72; font-weight: 800; }
    .karigar-ledger-table .ledger-in { color: #137647; font-weight: 750; }
    .karigar-ledger-table .ledger-out { color: #b42331; font-weight: 750; }
    .karigar-ledger-summary { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(145px, 1fr)); margin-bottom: 16px; }
    .karigar-ledger-metric { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 10px; padding: 11px 13px; }
    .karigar-ledger-metric small { color: #778397; display: block; font-size: .67rem; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
    .karigar-ledger-metric strong { color: #1e293b; display: block; font-size: .98rem; margin-top: 3px; }
    .karigar-ledger-metric.is-in { background: #f0faf5; border-color: #ccebdc; }
    .karigar-ledger-metric.is-in strong { color: #137647; }
    .karigar-ledger-metric.is-out { background: #fff5f5; border-color: #f1d1d4; }
    .karigar-ledger-metric.is-out strong { color: #b42331; }
    .karigar-ledger-metric.is-closing { background: #f4f1ff; border-color: #ddd5fa; }
    .karigar-ledger-metric.is-closing strong { color: #4a3a88; }
    .karigar-entry-badge { border-radius: 999px; display: inline-flex; font-size: .68rem; font-weight: 800; padding: .28rem .52rem; text-transform: uppercase; }
    .karigar-entry-badge.is-issue { background: #eaf8f1; color: #137647; }
    .karigar-entry-badge.is-receive { background: #fff0f1; color: #b42331; }
    .karigar-ledger-legend { color: #6f7b8e; display: flex; flex-wrap: wrap; font-size: .72rem; gap: 12px; }
    .karigar-ledger-legend span::before { border-radius: 50%; content: ''; display: inline-block; height: 7px; margin-right: 5px; width: 7px; }
    .karigar-ledger-legend .given::before { background: #22a06b; }
    .karigar-ledger-legend .returned::before { background: #df4c59; }
    @media (min-width: 768px) {
        .karigar-ledger-table { min-width: 980px; }
    }
    @media (max-width: 991.98px) {
        .karigar-ledger-heading { align-items: flex-start; flex-direction: column; }
        .karigar-ledger-summary { grid-template-columns: repeat(2, minmax(130px, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .karigar-ledger-heading, .karigar-ledger-filter, .karigar-ledger-card .card-body { padding: 13px; }
        .karigar-ledger-summary { gap: 8px; }
        .karigar-ledger-metric { padding: 9px 10px; }
        .karigar-ledger-metric strong { font-size: .88rem; }
        .karigar-ledger-card .dataTables_length, .karigar-ledger-card .dataTables_filter { text-align: left !important; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statementRange = static function (array $rows, string $openingKey, string $closingKey): array {
    if ($rows === []) return ['opening' => 0.0, 'closing' => 0.0];
    $newest = $rows[0];
    $oldest = $rows[count($rows) - 1];
    return ['opening' => (float) ($oldest[$openingKey] ?? 0), 'closing' => (float) ($newest[$closingKey] ?? 0)];
};
$goldRange = $statementRange($goldStatement ?? [], 'opening_gm', 'closing_gm');
$diamondRange = $statementRange($diamondStatement ?? [], 'opening_weight', 'closing_weight');
$stoneRange = $statementRange($stoneStatement ?? [], 'opening_weight', 'closing_weight');
$paymentRange = $statementRange($paymentStatement ?? [], 'opening_amount', 'closing_amount');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Karigar Profile: <?= esc($karigar['name']) ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/karigars/' . $karigar['id'] . '/edit') ?>" class="btn btn-outline-warning">
            <i class="fe fe-edit"></i> Edit
        </a>
        <form method="post" action="<?= site_url('admin/karigars/' . $karigar['id'] . '/status') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="is_active" value="<?= (int) $karigar['is_active'] === 1 ? '0' : '1' ?>">
            <button
                type="submit"
                class="btn <?= (int) $karigar['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                onclick="return confirm('Are you sure you want to <?= (int) $karigar['is_active'] === 1 ? 'deactivate' : 'activate' ?> this karigar?');"
            >
                <i class="fe <?= (int) $karigar['is_active'] === 1 ? 'fe-user-x' : 'fe-user-check' ?>"></i>
                <?= (int) $karigar['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>
        <a href="<?= site_url('admin/karigars') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Orders</div>
                <h4 class="mb-0"><?= esc((string) $orderStats['total_orders']) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Pending</div>
                <h4 class="mb-0"><?= esc((string) $orderStats['pending_orders']) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Gold Balance</div>
                <h4 class="mb-0"><?= esc(number_format((float) $goldSummary['balance_weight'], 3)) ?> gm</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Diamond Balance</div>
                <h4 class="mb-0"><?= esc(number_format((float) $diamondSummary['balance_weight'], 3)) ?> cts</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Stone Balance</div>
                <h4 class="mb-0"><?= esc(number_format((float) $stoneSummary['balance_weight'], 3)) ?> cts</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Payment Due</div>
                <h4 class="mb-0"><?= esc(number_format($paymentRange['closing'], 2)) ?></h4>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Documents</div>
                <h5 class="mb-0"><?= esc((string) $profileStats['documents']) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Ledger Entries</div>
                <h5 class="mb-0"><?= esc((string) $profileStats['ledger_entries']) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Overdue Orders</div>
                <h5 class="mb-0"><?= esc((string) $profileStats['overdue_orders']) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body py-3">
                <div class="text-muted">Last Activity</div>
                <h6 class="mb-0"><?= esc((string) $profileStats['last_activity']) ?></h6>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">General Details</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>Status:</strong> <?= (int) $karigar['is_active'] === 1 ? 'Active' : 'Inactive' ?></p>
                <p class="mb-1"><strong>Phone:</strong> <?= esc($karigar['phone'] ?: '-') ?></p>
                <p class="mb-1"><strong>Email:</strong> <?= esc($karigar['email'] ?: '-') ?></p>
                <p class="mb-1"><strong>Department:</strong> <?= esc($karigar['department'] ?: '-') ?></p>
                <p class="mb-1"><strong>Skills:</strong> <?= esc($karigar['skills_text'] ?: '-') ?></p>
                <p class="mb-1"><strong>Rate per gram:</strong> <?= esc(number_format((float) $karigar['rate_per_gm'], 2)) ?></p>
                <p class="mb-1"><strong>Allowed Wastage:</strong> <?= esc(number_format((float) ($karigar['wastage_percentage'] ?? 0), 2)) ?>%</p>
                <p class="mb-1"><strong>Joining Date:</strong> <?= esc($karigar['joining_date'] ?: '-') ?></p>
                <p class="mb-1"><strong>Address:</strong> <?= esc($karigar['address'] ?: '-') ?></p>
                <p class="mb-1"><strong>City/State:</strong> <?= esc(trim(($karigar['city'] ?? '') . ' / ' . ($karigar['state'] ?? '')) ?: '-') ?></p>
                <p class="mb-1"><strong>Pincode:</strong> <?= esc($karigar['pincode'] ?: '-') ?></p>
                <p class="mb-1"><strong>Aadhaar:</strong> <?= esc($karigar['aadhaar_no'] ?: '-') ?></p>
                <p class="mb-1"><strong>PAN:</strong> <?= esc($karigar['pan_no'] ?: '-') ?></p>
                <p class="mb-1"><strong>Bank:</strong> <?= esc($karigar['bank_name'] ?: '-') ?></p>
                <p class="mb-1"><strong>Account No:</strong> <?= esc($karigar['bank_account_no'] ?: '-') ?></p>
                <p class="mb-1"><strong>IFSC:</strong> <?= esc($karigar['ifsc_code'] ?: '-') ?></p>
                <p class="mb-0"><strong>Notes:</strong> <?= esc($karigar['notes'] ?: '-') ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Documents</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0" data-dt-page-length="5">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File</th>
                                <th>Remarks</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><?= esc($d['document_type']) ?></td>
                                    <td><a href="<?= base_url($d['file_path']) ?>" target="_blank"><?= esc($d['file_name']) ?></a></td>
                                    <td><?= esc($d['remarks'] ?: '-') ?></td>
                                    <td><?= esc((string) $d['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="karigar-ledger-zone">
<div class="karigar-ledger-heading mb-3">
    <div>
        <h4><i class="fe fe-book-open me-2 text-warning"></i>Karigar Account Ledgers</h4>
        <p>Material given increases the karigar balance. Material returned or jewellery received reduces it.</p>
    </div>
    <div class="karigar-ledger-jump">
        <a href="#pure-gold-ledger">Pure Gold</a>
        <a href="#diamond-ledger">Diamond</a>
        <a href="#stone-ledger">Stone</a>
        <a href="#payment-ledger">Payments</a>
    </div>
</div>

<div class="karigar-ledger-filter mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-3"><label class="form-label mb-1">From date</label><input type="date" name="ledger_from" class="form-control" value="<?= esc((string) ($ledgerFilters['from'] ?? '')) ?>"></div>
        <div class="col-sm-6 col-lg-3"><label class="form-label mb-1">To date</label><input type="date" name="ledger_to" class="form-control" value="<?= esc((string) ($ledgerFilters['to'] ?? '')) ?>"></div>
        <div class="col-lg-6"><button class="btn btn-primary"><i class="fe fe-filter me-1"></i>Apply date range</button> <a class="btn btn-light" href="<?= site_url('admin/karigars/' . $karigar['id']) ?>">Clear</a><small class="d-block text-muted mt-1">The selected date range applies to every ledger below.</small></div>
    </form>
</div>

<div class="card karigar-ledger-card">
    <div class="card-header"><h5 class="card-title mb-0">Order Assignment History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignedOrders as $o): ?>
                        <tr>
                            <td><a href="<?= site_url('admin/orders/' . $o['id']) ?>"><?= esc($o['order_no']) ?></a></td>
                            <td><?= esc($o['customer_name'] ?: '-') ?></td>
                            <td><?= esc($o['status']) ?></td>
                            <td><?= esc($o['priority']) ?></td>
                            <td><?= esc($o['due_date'] ?: '-') ?></td>
                            <td><?= esc((string) $o['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card karigar-ledger-card">
    <div class="card-header"><div><h5 class="card-title mb-0">Combined Material Movement</h5><small>Quick audit trail of gold and diamond given or received</small></div></div>
    <div class="card-body">
        <div class="karigar-ledger-summary">
            <div class="karigar-ledger-metric is-in"><small>Gold given</small><strong><?= esc(number_format((float) $movementSummary['issue_gold'], 3)) ?> gm</strong></div>
            <div class="karigar-ledger-metric is-out"><small>Gold returned</small><strong><?= esc(number_format((float) $movementSummary['receive_gold'], 3)) ?> gm</strong></div>
            <div class="karigar-ledger-metric is-closing"><small>Gold with karigar</small><strong><?= esc(number_format((float) $movementSummary['balance_gold'], 3)) ?> gm</strong></div>
            <div class="karigar-ledger-metric is-closing"><small>Diamond with karigar</small><strong><?= esc(number_format((float) $movementSummary['balance_diamond'], 3)) ?> cts</strong></div>
        </div>
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Gold Purity</th>
                        <th>Gold (gm)</th>
                        <th>Diamond (cts)</th>
                        <th>Pure Gold (gm)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materialMovements as $mv): ?>
                        <tr>
                            <td><?= esc((string) $mv['created_at']) ?></td>
                            <td><?= esc($mv['order_no'] ?: '-') ?></td>
                            <td><span class="karigar-entry-badge <?= (string) $mv['movement_type'] === 'issue' ? 'is-issue' : 'is-receive' ?>"><?= (string) $mv['movement_type'] === 'issue' ? 'Given' : 'Returned' ?></span></td>
                            <td><?= esc($mv['location_name'] ?: '-') ?></td>
                            <td><?= esc(trim(($mv['purity_code'] ?? '') . ' ' . ($mv['color_name'] ?? '')) ?: '-') ?></td>
                            <td><?= esc(number_format((float) $mv['gold_gm'], 3)) ?></td>
                            <td><?= esc(number_format((float) $mv['diamond_cts'], 3)) ?></td>
                            <td><?= esc(number_format((float) ($mv['pure_gold_weight_gm'] ?? 0), 3)) ?></td>
                            <td><?= esc($mv['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 d-flex">
        <div class="card w-100 karigar-ledger-card" id="pure-gold-ledger">
            <div class="card-header">
                <div><h5 class="card-title mb-0"><i class="fe fe-circle me-2 text-warning"></i>Pure Gold Account</h5><small>Running balance of pure gold held by this karigar</small></div>
                <div class="karigar-ledger-legend"><span class="given">Given</span><span class="returned">Returned</span></div>
            </div>
            <div class="card-body">
                <div class="karigar-ledger-summary">
                    <div class="karigar-ledger-metric"><small>Opening balance</small><strong><?= esc(number_format($goldRange['opening'], 3)) ?> gm</strong></div>
                    <div class="karigar-ledger-metric is-in"><small>Material given</small><strong>+<?= esc(number_format((float) $goldSummary['issue_pure'], 3)) ?> gm</strong></div>
                    <div class="karigar-ledger-metric is-out"><small>Material returned</small><strong>-<?= esc(number_format((float) $goldSummary['receive_pure'], 3)) ?> gm</strong></div>
                    <div class="karigar-ledger-metric is-closing"><small>Closing with karigar</small><strong><?= esc(number_format($goldRange['closing'], 3)) ?> gm</strong></div>
                </div>
                <div class="table-responsive">
                    <table id="karigar-pure-gold-ledger-table" class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Movement</th>
                                <th>Location</th>
                                <th>Opening (gm)</th>
                                <th>Given (gm)</th>
                                <th>Returned (gm)</th>
                                <th>Closing (gm)</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goldStatement as $gl): ?>
                                <tr>
                                    <td><?= esc((string) $gl['created_at']) ?></td>
                                    <td><?= esc($gl['order_no'] ?: '-') ?></td>
                                    <td><span class="karigar-entry-badge <?= (string) $gl['entry_type'] === 'issue' ? 'is-issue' : 'is-receive' ?>"><?= (string) $gl['entry_type'] === 'issue' ? 'Given' : 'Returned' ?></span></td>
                                    <td><?= esc($gl['location_name'] ?: '-') ?></td>
                                    <td class="ledger-number"><?= esc(number_format((float) $gl['opening_gm'], 3)) ?></td>
                                    <td class="ledger-number ledger-in"><?= (float) $gl['debit_gm'] > 0 ? '+' . esc(number_format((float) $gl['debit_gm'], 3)) : '-' ?></td>
                                    <td class="ledger-number ledger-out"><?= (float) $gl['credit_gm'] > 0 ? '-' . esc(number_format((float) $gl['credit_gm'], 3)) : '-' ?></td>
                                    <td class="ledger-number ledger-balance"><?= esc(number_format((float) $gl['closing_gm'], 3)) ?></td>
                                    <td><?= esc(($gl['reference_type'] ?: '-') . ($gl['reference_id'] ? ' #' . $gl['reference_id'] : '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex">
        <div class="card w-100 karigar-ledger-card" id="diamond-ledger">
            <div class="card-header">
                <div><h5 class="card-title mb-0"><i class="fe fe-gitlab me-2 text-primary"></i>Diamond Account</h5><small>Carat and pieces currently held by this karigar</small></div>
                <div class="karigar-ledger-legend"><span class="given">Given</span><span class="returned">Returned</span></div>
            </div>
            <div class="card-body">
                <div class="karigar-ledger-summary">
                    <div class="karigar-ledger-metric"><small>Opening balance</small><strong><?= esc(number_format($diamondRange['opening'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-in"><small>Diamonds given</small><strong>+<?= esc(number_format((float) $diamondSummary['issue_weight'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-out"><small>Diamonds returned</small><strong>-<?= esc(number_format((float) $diamondSummary['receive_weight'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-closing"><small>Closing with karigar</small><strong><?= esc(number_format($diamondRange['closing'], 3)) ?> cts</strong></div>
                </div>
                <div class="table-responsive">
                    <table id="karigar-diamond-ledger-table" class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Movement</th>
                                <th>Location</th>
                                <th>Opening</th>
                                <th>Given</th>
                                <th>Returned</th>
                                <th>Closing</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diamondStatement as $dl): ?>
                                <tr>
                                    <td><?= esc((string) $dl['created_at']) ?></td>
                                    <td><?= esc($dl['order_no'] ?: '-') ?></td>
                                    <td><span class="karigar-entry-badge <?= (string) $dl['entry_type'] === 'issue' ? 'is-issue' : 'is-receive' ?>"><?= (string) $dl['entry_type'] === 'issue' ? 'Given' : 'Returned' ?></span></td>
                                    <td><?= esc($dl['location_name'] ?: '-') ?></td>
                                    <td class="ledger-number"><?= esc(number_format((float) $dl['opening_weight'], 3)) ?> cts <small class="d-block text-muted"><?= esc(number_format((float) $dl['opening_pcs'], 0)) ?> pcs</small></td>
                                    <td class="ledger-number ledger-in"><?= (float) $dl['debit_weight'] > 0 ? '+' . esc(number_format((float) $dl['debit_weight'], 3)) . ' cts' : '-' ?><small class="d-block"><?= (float) $dl['debit_pcs'] > 0 ? esc(number_format((float) $dl['debit_pcs'], 0)) . ' pcs' : '' ?></small></td>
                                    <td class="ledger-number ledger-out"><?= (float) $dl['credit_weight'] > 0 ? '-' . esc(number_format((float) $dl['credit_weight'], 3)) . ' cts' : '-' ?><small class="d-block"><?= (float) $dl['credit_pcs'] > 0 ? esc(number_format((float) $dl['credit_pcs'], 0)) . ' pcs' : '' ?></small></td>
                                    <td class="ledger-number ledger-balance"><?= esc(number_format((float) $dl['closing_weight'], 3)) ?> cts <small class="d-block"><?= esc(number_format((float) $dl['closing_pcs'], 0)) ?> pcs</small></td>
                                    <td><?= esc(($dl['reference_type'] ?: '-') . ($dl['reference_id'] ? ' #' . $dl['reference_id'] : '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 d-flex">
        <div class="card w-100 karigar-ledger-card" id="stone-ledger">
            <div class="card-header">
                <div><h5 class="card-title mb-0"><i class="fe fe-disc me-2 text-info"></i>Stone Account</h5><small>Stone weight and pieces currently held by this karigar</small></div>
                <div class="karigar-ledger-legend"><span class="given">Given</span><span class="returned">Returned</span></div>
            </div>
            <div class="card-body">
                <div class="karigar-ledger-summary">
                    <div class="karigar-ledger-metric"><small>Opening balance</small><strong><?= esc(number_format($stoneRange['opening'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-in"><small>Stones given</small><strong>+<?= esc(number_format((float) $stoneSummary['issue_weight'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-out"><small>Stones returned</small><strong>-<?= esc(number_format((float) $stoneSummary['receive_weight'], 3)) ?> cts</strong></div>
                    <div class="karigar-ledger-metric is-closing"><small>Closing with karigar</small><strong><?= esc(number_format($stoneRange['closing'], 3)) ?> cts</strong></div>
                </div>
                <div class="table-responsive">
                    <table id="karigar-stone-ledger-table" class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Movement</th>
                                <th>Location</th>
                                <th>Opening</th>
                                <th>Given</th>
                                <th>Returned</th>
                                <th>Closing</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stoneStatement as $sl): ?>
                                <tr>
                                    <td><?= esc((string) $sl['created_at']) ?></td>
                                    <td><?= esc($sl['order_no'] ?: '-') ?></td>
                                    <td><span class="karigar-entry-badge <?= (string) $sl['entry_type'] === 'issue' ? 'is-issue' : 'is-receive' ?>"><?= (string) $sl['entry_type'] === 'issue' ? 'Given' : 'Returned' ?></span></td>
                                    <td><?= esc($sl['location_name'] ?: '-') ?></td>
                                    <td class="ledger-number"><?= esc(number_format((float) $sl['opening_weight'], 3)) ?> cts <small class="d-block text-muted"><?= esc(number_format((float) $sl['opening_pcs'], 0)) ?> pcs</small></td>
                                    <td class="ledger-number ledger-in"><?= (float) $sl['debit_weight'] > 0 ? '+' . esc(number_format((float) $sl['debit_weight'], 3)) . ' cts' : '-' ?><small class="d-block"><?= (float) $sl['debit_pcs'] > 0 ? esc(number_format((float) $sl['debit_pcs'], 0)) . ' pcs' : '' ?></small></td>
                                    <td class="ledger-number ledger-out"><?= (float) $sl['credit_weight'] > 0 ? '-' . esc(number_format((float) $sl['credit_weight'], 3)) . ' cts' : '-' ?><small class="d-block"><?= (float) $sl['credit_pcs'] > 0 ? esc(number_format((float) $sl['credit_pcs'], 0)) . ' pcs' : '' ?></small></td>
                                    <td class="ledger-number ledger-balance"><?= esc(number_format((float) $sl['closing_weight'], 3)) ?> cts <small class="d-block"><?= esc(number_format((float) $sl['closing_pcs'], 0)) ?> pcs</small></td>
                                    <td><?= esc(($sl['reference_type'] ?: '-') . ($sl['reference_id'] ? ' #' . $sl['reference_id'] : '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex">
        <div class="card w-100 karigar-ledger-card" id="payment-ledger">
            <div class="card-header"><div><h5 class="card-title mb-0"><i class="fe fe-credit-card me-2 text-success"></i>Labour & Payment Account</h5><small>Charges increase the payable amount; payments reduce it</small></div></div>
            <div class="card-body">
                <?php if (! $paymentLedgerEnabled): ?>
                    <div class="alert alert-warning mb-3">Payment ledger is not available. Run migration to enable.</div>
                <?php else: ?>
                    <div class="karigar-ledger-summary">
                        <div class="karigar-ledger-metric"><small>Opening payable</small><strong>₹<?= esc(number_format($paymentRange['opening'], 2)) ?></strong></div>
                        <div class="karigar-ledger-metric is-in"><small>Labour charges</small><strong>+₹<?= esc(number_format((float) $paymentSummary['charge'], 2)) ?></strong></div>
                        <div class="karigar-ledger-metric is-out"><small>Payments made</small><strong>-₹<?= esc(number_format((float) $paymentSummary['paid'], 2)) ?></strong></div>
                        <div class="karigar-ledger-metric is-closing"><small>Closing payable</small><strong>₹<?= esc(number_format($paymentRange['closing'], 2)) ?></strong></div>
                    </div>
                    <form method="post" action="<?= site_url('admin/karigars/' . $karigar['id'] . '/payment') ?>" class="bg-light border rounded-3 p-3 mb-3">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label mb-1">Entry type</label>
                                <select name="entry_type" class="form-select" required>
                                    <option value="charge">Add labour charge</option>
                                    <option value="payment">Record payment</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label mb-1">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1">Related order</label><select name="order_id" class="form-select">
                                    <option value="">Order (Optional)</option>
                                    <?php foreach ($assignedOrders as $o): ?>
                                        <option value="<?= esc((string) $o['id']) ?>"><?= esc($o['order_no']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label mb-1">Reference</label><input type="text" name="reference_no" class="form-control" placeholder="Voucher / UTR / reference">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1">Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional notes">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label mb-1 d-none d-md-block">&nbsp;</label><button class="btn btn-primary w-100"><i class="fe fe-plus me-1"></i>Add</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="karigar-payment-ledger-table" class="table datatable table-hover mb-0 karigar-ledger-table" data-dt-page-length="10">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entry</th>
                                    <th>Order</th>
                                    <th>Opening</th>
                                    <th>Charge</th>
                                    <th>Paid</th>
                                    <th>Closing</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentStatement as $pl): ?>
                                    <tr>
                                        <td><?= esc((string) $pl['created_at']) ?></td>
                                        <td><span class="karigar-entry-badge <?= (string) $pl['entry_type'] === 'charge' ? 'is-issue' : 'is-receive' ?>"><?= (string) $pl['entry_type'] === 'charge' ? 'Charge' : 'Payment' ?></span></td>
                                        <td><?= esc($pl['order_no'] ?: '-') ?></td>
                                        <td class="ledger-number">₹<?= esc(number_format((float) $pl['opening_amount'], 2)) ?></td>
                                        <td class="ledger-number ledger-in"><?= (float) $pl['debit_amount'] > 0 ? '+₹' . esc(number_format((float) $pl['debit_amount'], 2)) : '-' ?></td>
                                        <td class="ledger-number ledger-out"><?= (float) $pl['credit_amount'] > 0 ? '-₹' . esc(number_format((float) $pl['credit_amount'], 2)) : '-' ?></td>
                                        <td class="ledger-number ledger-balance">₹<?= esc(number_format((float) $pl['closing_amount'], 2)) ?></td>
                                        <td><?= esc($pl['reference_no'] ?: '-') ?></td>
                                        <td><?= esc($pl['notes'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h5 class="card-title mb-0">Imported Diamond Issuement Details</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0" data-dt-page-length="25">
                <thead><tr><th>Date</th><th>Group</th><th>Design</th><th>Quality</th><th>Shade</th><th>Size</th><th>Pcs</th><th>Cts</th><th>Bag</th><th>Source</th></tr></thead>
                <tbody>
                    <?php foreach (($sourceIssueLines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['issue_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['issue_group'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['design_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['quality'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['shade'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['size_label'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($line['pcs'] ?? 0), 0) ?></td>
                            <td><?= number_format((float) ($line['weight_cts'] ?? 0), 3) ?></td>
                            <td><?= esc((string) ($line['bag_label'] ?? '-')) ?></td>
                            <td><?= esc((string) (($line['source_sheet'] ?? '') . ':' . ($line['source_row'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Completed Jewellery</h5>
        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/jewellery-inventory?karigar_id=' . (int) $karigar['id']) ?>">Open Jewellery Inventory</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0" data-dt-page-length="25">
                <thead><tr><th>Ready Date</th><th>Tag</th><th>Group</th><th>Design</th><th>Gross</th><th>Net Gold</th><th>Labour</th><th>Payment</th><th>Inventory</th></tr></thead>
                <tbody>
                    <?php foreach (($finishedItems ?? []) as $item): ?>
                        <tr>
                            <td><?= esc((string) ($item['ready_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($item['tag_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($item['ready_group'] ?? '-')) ?></td>
                            <td><?= esc((string) ($item['design_name'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($item['gross_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($item['net_weight_gm'] ?? 0), 3) ?></td>
                            <td>₹<?= number_format((float) ($item['labour_charges'] ?? 0), 2) ?></td>
                            <td><span class="badge <?= (string) ($item['payment_status'] ?? '') === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc((string) ($item['payment_status'] ?? 'Pending')) ?></span></td>
                            <td><?= esc((string) (($item['showroom_stock_status'] ?? '') ?: ($item['inventory_status'] ?? '-'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>
