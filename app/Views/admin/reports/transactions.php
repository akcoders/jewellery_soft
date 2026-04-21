<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Txn Type</label>
                <select name="transaction_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($transactionTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['transaction_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Material</label>
                <select name="material_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($materialTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['material_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?><option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($customers ?? []) as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= esc((string) $customer['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vendor</label>
                <select name="vendor_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($vendors ?? []) as $vendor): ?><option value="<?= (int) $vendor['id'] ?>" <?= (int) ($filters['vendor_id'] ?? 0) === (int) $vendor['id'] ? 'selected' : '' ?>><?= esc((string) $vendor['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Order No</label><input type="text" name="order_no" class="form-control" value="<?= esc((string) ($filters['order_no'] ?? '')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($statuses ?? []) as $status): ?><option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc($status) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Search</label><input type="text" name="search" class="form-control" value="<?= esc((string) ($filters['search'] ?? '')) ?>" placeholder="Reference, party, notes"></div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= site_url('admin/reports/transactions') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Rows</small><strong><?= (int) ($cards['row_count'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Amount Total</small><strong>Rs <?= number_format((float) ($cards['amount_total'] ?? 0), 2) ?></strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Gold</small><strong><?= number_format((float) ($cards['gold_total'] ?? 0), 3) ?> gm</strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Diamond</small><strong><?= number_format((float) ($cards['diamond_total'] ?? 0), 3) ?> cts</strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Stone / FG Qty</small><strong><?= number_format((float) ($cards['stone_total'] ?? 0), 3) ?></strong></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Transaction Mix</h5></div>
    <div class="card-body">
        <?php $typeMax = ($typeCounts ?? []) !== [] ? max($typeCounts) : 0; ?>
        <div class="row">
            <?php foreach (($typeCounts ?? []) as $label => $count): ?>
                <div class="col-md-4 mb-3">
                    <div class="small d-flex justify-content-between"><span><?= esc((string) $label) ?></span><span><?= (int) $count ?></span></div>
                    <div class="progress" style="height:9px;"><div class="progress-bar bg-dark" style="width: <?= $typeMax > 0 ? round(($count / $typeMax) * 100, 2) : 0 ?>%"></div></div>
                </div>
            <?php endforeach; ?>
            <?php if (($typeCounts ?? []) === []): ?><div class="col-12 text-muted">No transaction summary available.</div><?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Combined Transactions</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Material</th>
                        <th>Reference</th>
                        <th>Order No</th>
                        <th>Party</th>
                        <th>Status</th>
                        <th>Gold</th>
                        <th>Diamond</th>
                        <th>Stone/Qty</th>
                        <th>Amount</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?><tr><td colspan="12" class="text-center text-muted">No transactions found.</td></tr><?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['transaction_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['transaction_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['material_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['reference_no'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['order_no'] ?? '') !== '' ? $row['order_no'] : '-')) ?></td>
                            <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['status'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['gold_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['diamond_cts'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['stone_qty'] ?? 0), 3) ?></td>
                            <td>Rs <?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                            <td><?= esc((string) ($row['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
