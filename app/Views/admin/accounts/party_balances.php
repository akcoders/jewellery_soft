<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $type = (string) ($type ?? '');
    $label = ['vendor' => 'Vendor', 'karigar' => 'Karigar', 'customer' => 'Customer'][$type] ?? ucfirst($type);
    $balanceLabel = $type === 'customer' ? 'Receivable' : 'Payable';
    $paidLabel = $type === 'customer' ? 'Received' : 'Paid';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0"><?= esc($label) ?> Pending Balances</h4>
        <small class="text-muted">Only accounts with pending balance are shown here.</small>
    </div>
    <a href="<?= site_url('admin/accounts') ?>" class="btn btn-light">Back to Dashboard</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Pending Parties</small><h5 class="mb-0"><?= (int) ($summary['party_count'] ?? 0) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Total Bills</small><h5 class="mb-0"><?= (int) ($summary['bill_count'] ?? 0) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block">Total Amount</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['amount'] ?? 0), 2) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body py-3"><small class="text-muted d-block"><?= esc($balanceLabel) ?> Balance</small><h5 class="mb-0">Rs <?= number_format((float) ($summary['pending'] ?? 0), 2) ?></h5></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Pending <?= esc($label) ?> Accounts</h5>
        <a href="<?= site_url('admin/accounts/general-ledger?party_type=' . $type) ?>" class="btn btn-sm btn-outline-primary">View All Ledger</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-striped align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th><?= esc($label) ?></th>
                        <th>Bills</th>
                        <th>Total</th>
                        <th><?= esc($paidLabel) ?></th>
                        <th><?= esc($balanceLabel) ?></th>
                        <th>Account</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="6" class="text-center text-muted">No pending <?= esc(strtolower($label)) ?> balance found.</td></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?>
                    <?php $partyId = (int) ($row['party_id'] ?? 0); ?>
                    <tr>
                        <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                        <td><?= (int) ($row['bill_count'] ?? 0) ?></td>
                        <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                        <td>Rs <?= number_format((float) ($row['paid'] ?? 0), 2) ?></td>
                        <td><strong>Rs <?= number_format((float) ($row['pending'] ?? 0), 2) ?></strong></td>
                        <td>
                            <?php if ($partyId > 0): ?>
                                <a href="<?= site_url('admin/accounts/party-ledger/' . $type . '/' . $partyId) ?>" class="btn btn-sm btn-outline-primary">Open Account</a>
                            <?php else: ?>
                                <span class="text-muted">No linked master</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
