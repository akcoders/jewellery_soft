<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
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
                <h4 class="mb-0"><?= esc(number_format((float) $paymentSummary['balance'], 2)) ?></h4>
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
                            <?php if ($documents === []): ?>
                                <tr><td colspan="4" class="text-muted text-center">No documents uploaded.</td></tr>
                            <?php endif; ?>
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

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Order Assignment History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
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
                    <?php if ($assignedOrders === []): ?>
                        <tr><td colspan="6" class="text-muted text-center">No assigned orders.</td></tr>
                    <?php endif; ?>
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

<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Gold Ledger Stats</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>Issued:</strong> <?= esc(number_format((float) $goldSummary['issue_weight'], 3)) ?> gm</p>
                <p class="mb-1"><strong>Received:</strong> <?= esc(number_format((float) $goldSummary['receive_weight'], 3)) ?> gm</p>
                <p class="mb-1"><strong>Balance:</strong> <?= esc(number_format((float) $goldSummary['balance_weight'], 3)) ?> gm</p>
                <p class="mb-1"><strong>Pure Issued:</strong> <?= esc(number_format((float) $goldSummary['issue_pure'], 3)) ?> gm</p>
                <p class="mb-1"><strong>Pure Received:</strong> <?= esc(number_format((float) $goldSummary['receive_pure'], 3)) ?> gm</p>
                <p class="mb-0"><strong>Pure Balance:</strong> <?= esc(number_format((float) $goldSummary['balance_pure'], 3)) ?> gm</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Diamond Ledger Stats</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>Issued:</strong> <?= esc(number_format((float) $diamondSummary['issue_weight'], 3)) ?> cts / <?= esc(number_format((float) $diamondSummary['issue_pcs'], 0)) ?> pcs</p>
                <p class="mb-1"><strong>Received:</strong> <?= esc(number_format((float) $diamondSummary['receive_weight'], 3)) ?> cts / <?= esc(number_format((float) $diamondSummary['receive_pcs'], 0)) ?> pcs</p>
                <p class="mb-0"><strong>Balance:</strong> <?= esc(number_format((float) $diamondSummary['balance_weight'], 3)) ?> cts / <?= esc(number_format((float) $diamondSummary['balance_pcs'], 0)) ?> pcs</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Stone Ledger Stats</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>Issued:</strong> <?= esc(number_format((float) $stoneSummary['issue_weight'], 3)) ?> cts / <?= esc(number_format((float) $stoneSummary['issue_pcs'], 0)) ?> pcs</p>
                <p class="mb-1"><strong>Received:</strong> <?= esc(number_format((float) $stoneSummary['receive_weight'], 3)) ?> cts / <?= esc(number_format((float) $stoneSummary['receive_pcs'], 0)) ?> pcs</p>
                <p class="mb-0"><strong>Balance:</strong> <?= esc(number_format((float) $stoneSummary['balance_weight'], 3)) ?> cts / <?= esc(number_format((float) $stoneSummary['balance_pcs'], 0)) ?> pcs</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Issue / Receive Movement History</h5></div>
    <div class="card-body">
        <div class="mb-3">
            <span class="me-3"><strong>Gold Issued:</strong> <?= esc(number_format((float) $movementSummary['issue_gold'], 3)) ?> gm</span>
            <span class="me-3"><strong>Gold Received:</strong> <?= esc(number_format((float) $movementSummary['receive_gold'], 3)) ?> gm</span>
            <span class="me-3"><strong>Gold Balance:</strong> <?= esc(number_format((float) $movementSummary['balance_gold'], 3)) ?> gm</span>
            <span class="me-3"><strong>Diamond Issued:</strong> <?= esc(number_format((float) $movementSummary['issue_diamond'], 3)) ?> cts</span>
            <span><strong>Diamond Balance:</strong> <?= esc(number_format((float) $movementSummary['balance_diamond'], 3)) ?> cts</span>
        </div>
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
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
                    <?php if ($materialMovements === []): ?>
                        <tr><td colspan="9" class="text-muted text-center">No movement entries.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($materialMovements as $mv): ?>
                        <tr>
                            <td><?= esc((string) $mv['created_at']) ?></td>
                            <td><?= esc($mv['order_no'] ?: '-') ?></td>
                            <td><?= esc(ucfirst((string) $mv['movement_type'])) ?></td>
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
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Gold Ledger (Opening / Debit / Credit / Closing)</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Opening (gm)</th>
                                <th>Debit (Issue)</th>
                                <th>Credit (Return)</th>
                                <th>Closing (gm)</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($goldStatement === []): ?>
                                <tr><td colspan="9" class="text-muted text-center">No gold ledger entries.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($goldStatement as $gl): ?>
                                <tr>
                                    <td><?= esc((string) $gl['created_at']) ?></td>
                                    <td><?= esc($gl['order_no'] ?: '-') ?></td>
                                    <td><?= esc(ucfirst((string) $gl['entry_type'])) ?></td>
                                    <td><?= esc($gl['location_name'] ?: '-') ?></td>
                                    <td><?= esc(number_format((float) $gl['opening_gm'], 3)) ?></td>
                                    <td><?= esc(number_format((float) $gl['debit_gm'], 3)) ?></td>
                                    <td><?= esc(number_format((float) $gl['credit_gm'], 3)) ?></td>
                                    <td class="fw-bold"><?= esc(number_format((float) $gl['closing_gm'], 3)) ?></td>
                                    <td><?= esc(($gl['reference_type'] ?: '-') . ($gl['reference_id'] ? ' #' . $gl['reference_id'] : '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Diamond Ledger (Opening / Debit / Credit / Closing)</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Opening</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Closing</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($diamondStatement === []): ?>
                                <tr><td colspan="9" class="text-muted text-center">No diamond ledger entries.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($diamondStatement as $dl): ?>
                                <tr>
                                    <td><?= esc((string) $dl['created_at']) ?></td>
                                    <td><?= esc($dl['order_no'] ?: '-') ?></td>
                                    <td><?= esc(ucfirst((string) $dl['entry_type'])) ?></td>
                                    <td><?= esc($dl['location_name'] ?: '-') ?></td>
                                    <td><?= esc(number_format((float) $dl['opening_weight'], 3)) ?> cts / <?= esc(number_format((float) $dl['opening_pcs'], 0)) ?> pcs</td>
                                    <td><?= esc(number_format((float) $dl['debit_weight'], 3)) ?> cts / <?= esc(number_format((float) $dl['debit_pcs'], 0)) ?> pcs</td>
                                    <td><?= esc(number_format((float) $dl['credit_weight'], 3)) ?> cts / <?= esc(number_format((float) $dl['credit_pcs'], 0)) ?> pcs</td>
                                    <td class="fw-bold"><?= esc(number_format((float) $dl['closing_weight'], 3)) ?> cts / <?= esc(number_format((float) $dl['closing_pcs'], 0)) ?> pcs</td>
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
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Stone Ledger (Opening / Debit / Credit / Closing)</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Opening</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Closing</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stoneStatement === []): ?>
                                <tr><td colspan="9" class="text-muted text-center">No stone ledger entries.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($stoneStatement as $sl): ?>
                                <tr>
                                    <td><?= esc((string) $sl['created_at']) ?></td>
                                    <td><?= esc($sl['order_no'] ?: '-') ?></td>
                                    <td><?= esc(ucfirst((string) $sl['entry_type'])) ?></td>
                                    <td><?= esc($sl['location_name'] ?: '-') ?></td>
                                    <td><?= esc(number_format((float) $sl['opening_weight'], 3)) ?> cts / <?= esc(number_format((float) $sl['opening_pcs'], 0)) ?> pcs</td>
                                    <td><?= esc(number_format((float) $sl['debit_weight'], 3)) ?> cts / <?= esc(number_format((float) $sl['debit_pcs'], 0)) ?> pcs</td>
                                    <td><?= esc(number_format((float) $sl['credit_weight'], 3)) ?> cts / <?= esc(number_format((float) $sl['credit_pcs'], 0)) ?> pcs</td>
                                    <td class="fw-bold"><?= esc(number_format((float) $sl['closing_weight'], 3)) ?> cts / <?= esc(number_format((float) $sl['closing_pcs'], 0)) ?> pcs</td>
                                    <td><?= esc(($sl['reference_type'] ?: '-') . ($sl['reference_id'] ? ' #' . $sl['reference_id'] : '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Payment Ledger</h5></div>
            <div class="card-body">
                <?php if (! $paymentLedgerEnabled): ?>
                    <div class="alert alert-warning mb-3">Payment ledger is not available. Run migration to enable.</div>
                <?php else: ?>
                    <form method="post" action="<?= site_url('admin/karigars/' . $karigar['id'] . '/payment') ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <select name="entry_type" class="form-control" required>
                                    <option value="charge">Charge</option>
                                    <option value="payment">Payment</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <select name="order_id" class="form-control">
                                    <option value="">Order (Optional)</option>
                                    <?php foreach ($assignedOrders as $o): ?>
                                        <option value="<?= esc((string) $o['id']) ?>"><?= esc($o['order_no']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <input type="text" name="reference_no" class="form-control" placeholder="Reference No">
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="text" name="notes" class="form-control" placeholder="Notes">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button class="btn btn-primary w-100"><i class="fe fe-plus"></i></button>
                            </div>
                        </div>
                    </form>

                    <div class="mb-3">
                        <span class="me-3"><strong>Total Charge:</strong> <?= esc(number_format((float) $paymentSummary['charge'], 2)) ?></span>
                        <span class="me-3"><strong>Total Paid:</strong> <?= esc(number_format((float) $paymentSummary['paid'], 2)) ?></span>
                        <span><strong>Outstanding:</strong> <?= esc(number_format((float) $paymentSummary['balance'], 2)) ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Order</th>
                                    <th>Opening</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Closing</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($paymentStatement === []): ?>
                                    <tr><td colspan="9" class="text-muted text-center">No payment ledger entries.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($paymentStatement as $pl): ?>
                                    <tr>
                                        <td><?= esc((string) $pl['created_at']) ?></td>
                                        <td><?= esc(ucfirst((string) $pl['entry_type'])) ?></td>
                                        <td><?= esc($pl['order_no'] ?: '-') ?></td>
                                        <td><?= esc(number_format((float) $pl['opening_amount'], 2)) ?></td>
                                        <td><?= esc(number_format((float) $pl['debit_amount'], 2)) ?></td>
                                        <td><?= esc(number_format((float) $pl['credit_amount'], 2)) ?></td>
                                        <td class="fw-bold"><?= esc(number_format((float) $pl['closing_amount'], 2)) ?></td>
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
<?= $this->endSection() ?>
