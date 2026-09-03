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
    .ready-thumb { background: #f5f6f8; border: 1px solid #e1e5eb; border-radius: 10px; height: 58px; object-fit: cover; width: 58px; }
    .ready-thumb-empty { align-items: center; color: #98a2b3; display: inline-flex; font-size: 1.15rem; justify-content: center; }
    .completed-table td { vertical-align: middle; }
    .completed-design { color: #172235; font-weight: 750; max-width: 240px; }
    .completed-design small { color: #7b8798; display: block; font-weight: 500; margin-top: 3px; }
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
                <h4 class="mb-0">₹<?= esc(number_format((float) ($labourSummary['outstanding'] ?? 0), 2)) ?></h4>
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
                        <th>Photo</th>
                        <th>Order No</th>
                        <th>Order Name</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignedOrders as $o): ?>
                        <tr>
                            <td><span style="align-items:center;background:#f3f4f6;border:1px solid #e2e5ea;border-radius:8px;display:inline-flex;height:42px;justify-content:center;overflow:hidden;width:42px"><?php if (! empty($o['thumbnail_url'])): ?><img src="<?= esc((string) $o['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" style="height:100%;object-fit:cover;width:100%" onerror="this.style.display='none'"><?php else: ?><i class="fe fe-image text-muted"></i><?php endif; ?></span></td>
                            <td><a href="<?= site_url('admin/orders/' . $o['id']) ?>"><?= esc($o['order_no']) ?></a></td>
                            <td><strong><?= esc((string) (($o['order_name'] ?? '') ?: '-')) ?></strong></td>
                            <td><?= esc($o['status']) ?></td>
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
            <div class="card-header">
                <div><h5 class="card-title mb-0"><i class="fe fe-file-text me-2 text-success"></i>Labour & Payment Account</h5><small>Actual supplier bills, combined job works and separately recorded payments</small></div>
                <a class="btn btn-sm btn-primary" href="<?= site_url('admin/accounts/labour-bills/create?karigar_id=' . (int) $karigar['id']) ?>"><i class="fe fe-plus me-1"></i>Add Labour Bill</a>
            </div>
            <div class="card-body">
                <div class="karigar-ledger-summary">
                    <div class="karigar-ledger-metric"><small>Actual bills</small><strong><?= (int) ($labourSummary['bill_count'] ?? 0) ?></strong></div>
                    <div class="karigar-ledger-metric is-in"><small>Total billed</small><strong>₹<?= esc(number_format((float) ($labourSummary['billed'] ?? 0), 2)) ?></strong></div>
                    <div class="karigar-ledger-metric is-out"><small>Payments entered</small><strong>₹<?= esc(number_format((float) ($labourSummary['paid'] ?? 0), 2)) ?></strong></div>
                    <div class="karigar-ledger-metric is-closing"><small>Outstanding</small><strong>₹<?= esc(number_format((float) ($labourSummary['outstanding'] ?? 0), 2)) ?></strong></div>
                </div>
                <h6 class="mb-2">Bills</h6>
                <div class="table-responsive mb-4">
                    <table id="karigar-payment-ledger-table" class="table datatable table-hover align-middle mb-0 karigar-ledger-table" data-dt-page-length="10">
                        <thead><tr><th>Date</th><th>Bill No</th><th>Job Works</th><th>Taxable</th><th>GST</th><th>Total</th><th>Paid</th><th>Pending</th><th>Status</th><th>Files</th></tr></thead>
                        <tbody>
                            <?php foreach (($labourBills ?? []) as $bill): ?>
                                <?php $billPending = max(0, (float) $bill['total_amount'] - (float) $bill['paid_amount']); ?>
                                <tr>
                                    <td><?= esc((string) $bill['bill_date']) ?></td>
                                    <td><a class="fw-semibold" href="<?= site_url('admin/accounts/labour-bills/' . (int) $bill['id']) ?>"><?= esc((string) $bill['bill_no']) ?></a></td>
                                    <td><span class="badge bg-light text-dark"><?= (int) ($bill['jobwork_count'] ?? 0) ?> work<?= (int) ($bill['jobwork_count'] ?? 0) === 1 ? '' : 's' ?></span><small class="d-block text-muted text-truncate" style="max-width:190px"><?= esc((string) ($bill['order_numbers'] ?: 'Unlinked invoice')) ?></small></td>
                                    <td class="ledger-number">₹<?= number_format((float) ($bill['taxable_amount'] ?? 0), 2) ?></td>
                                    <td class="ledger-number">₹<?= number_format((float) ($bill['gst_amount'] ?? 0), 2) ?></td>
                                    <td class="ledger-number fw-semibold">₹<?= number_format((float) $bill['total_amount'], 2) ?></td>
                                    <td class="ledger-number ledger-in">₹<?= number_format((float) $bill['paid_amount'], 2) ?></td>
                                    <td class="ledger-number <?= $billPending > 0 ? 'ledger-out' : '' ?>">₹<?= number_format($billPending, 2) ?></td>
                                    <td><span class="badge <?= $billPending <= 0 ? 'bg-success' : ((float) $bill['paid_amount'] > 0 ? 'bg-info text-dark' : 'bg-warning text-dark') ?>"><?= $billPending <= 0 ? 'Paid' : ((float) $bill['paid_amount'] > 0 ? 'Partial' : 'Pending') ?></span></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="<?= site_url('api/documents/labour-bill/' . (int) $bill['id']) ?>?download=1" target="_blank" title="Generated bill"><i class="fe fe-download"></i></a><?php if (! empty($bill['attachment_path'])): ?> <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/accounts/labour-bills/' . (int) $bill['id'] . '/attachment') ?>" title="Source attachment"><i class="fe fe-paperclip"></i></a><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <h6 class="mb-2">Payments</h6>
                <div class="table-responsive">
                    <table class="table datatable table-hover align-middle mb-0 karigar-ledger-table" data-dt-page-length="10">
                        <thead><tr><th>Date</th><th>Payment No</th><th>Against Bill</th><th>Mode</th><th>Reference</th><th>Notes</th><th>Amount</th></tr></thead>
                        <tbody><?php foreach (($labourPayments ?? []) as $payment): ?><tr><td><?= esc((string) $payment['payment_date']) ?></td><td><?= esc((string) $payment['payment_no']) ?></td><td><span class="badge bg-light text-dark"><?= esc((string) ($payment['bill_no'] ?: 'Unallocated')) ?></span></td><td><?= esc((string) ($payment['payment_mode'] ?: '-')) ?></td><td><?= esc((string) ($payment['reference_no'] ?: '-')) ?></td><td><?= esc((string) ($payment['notes'] ?: '-')) ?></td><td class="ledger-number ledger-out">₹<?= number_format((float) $payment['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div><h5 class="card-title mb-0">Completed Jewellery</h5><small class="text-muted">Ready pieces received from this karigar with inventory status</small></div>
        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/jewellery-inventory?karigar_id=' . (int) $karigar['id']) ?>">Open Jewellery Inventory</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover align-middle mb-0 completed-table" data-dt-page-length="25">
                <thead><tr><th>Ready Piece</th><th>Date / Order</th><th>Design</th><th>Weight</th><th>Labour</th><th>Billing</th><th>Inventory</th></tr></thead>
                <tbody>
                    <?php foreach (($finishedItems ?? []) as $item): ?>
                        <tr>
                            <td><?php if (! empty($item['image_path'])): ?><a href="<?= site_url('admin/orders/ready-image/' . (int) $item['id']) ?>" target="_blank"><img class="ready-thumb" loading="lazy" src="<?= site_url('admin/orders/ready-image/' . (int) $item['id']) ?>" alt="<?= esc((string) ($item['design_name'] ?? 'Ready jewellery')) ?>"></a><?php else: ?><span class="ready-thumb ready-thumb-empty"><i class="fe fe-image"></i></span><?php endif; ?></td>
                            <td><strong><?= esc((string) ($item['ready_date'] ?? '-')) ?></strong><?php if (! empty($item['order_id'])): ?><small class="d-block mt-1"><a href="<?= site_url('admin/orders/' . (int) $item['order_id']) ?>"><?= esc((string) ($item['order_no'] ?: 'Open order')) ?></a></small><?php else: ?><small class="d-block text-muted mt-1">Imported ready item</small><?php endif; ?></td>
                            <td class="completed-design"><?= esc((string) ($item['design_name'] ?: 'Unnamed design')) ?><small><?= esc((string) (($item['tag_no'] ?: $item['reference_no']) ?: ($item['ready_group'] ?: '-'))) ?></small></td>
                            <td><strong><?= number_format((float) ($item['gross_weight_gm'] ?? 0), 3) ?> gm</strong><small class="d-block text-muted">Net <?= number_format((float) ($item['net_weight_gm'] ?? 0), 3) ?> gm</small></td>
                            <td class="fw-semibold">₹<?= number_format((float) ($item['labour_charges'] ?? 0), 2) ?></td>
                            <td><span class="badge <?= (string) ($item['payment_status'] ?? '') === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc((string) ($item['payment_status'] ?? 'Pending')) ?></span></td>
                            <td><span class="badge bg-light text-dark"><?= esc((string) (($item['showroom_stock_status'] ?? '') ?: ($item['inventory_status'] ?? 'Ready'))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>
