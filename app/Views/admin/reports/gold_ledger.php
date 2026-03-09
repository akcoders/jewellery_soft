<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Debit (gm)</small>
            <strong><?= number_format((float) ($cards['debit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Credit (gm)</small>
            <strong><?= number_format((float) ($cards['credit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Balance (gm)</small>
            <strong><?= number_format((float) ($cards['balance_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Fine Balance (gm)</small>
            <strong><?= number_format((float) ($cards['balance_fine'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $karigar['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Txn Type</label>
                <select name="txn_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($txnTypes ?? []) as $type): ?>
                        <option value="<?= esc($type) ?>" <?= ($filters['txn_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order No</label>
                <input type="text" name="order_no" class="form-control" value="<?= esc((string) ($filters['order_no'] ?? '')) ?>" placeholder="Order no">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/gold-ledger') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Karigar</th>
                        <th>Order</th>
                        <th>Item</th>
                        <th>Location</th>
                        <th>Debit gm</th>
                        <th>Credit gm</th>
                        <th>Debit Fine</th>
                        <th>Credit Fine</th>
                        <th>Balance gm</th>
                        <th>Balance Fine</th>
                        <th>Rate</th>
                        <th>Value</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="15" class="text-center text-muted">No records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <?php $itemLabel = (($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA') . ' / ' . ($row['color_name'] ?: 'NA') . ' / ' . ($row['form_type'] ?: 'Raw')); ?>
                        <tr>
                            <td><?= esc((string) ($row['txn_date'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['txn_type'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc($itemLabel) ?></td>
                            <td><?= esc((string) ($row['location_name'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['debit_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['credit_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['debit_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['credit_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['balance_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['balance_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= $row['rate_per_gm'] === null ? '-' : number_format((float) $row['rate_per_gm'], 2) ?></td>
                            <td><?= $row['line_value'] === null ? '-' : number_format((float) $row['line_value'], 2) ?></td>
                            <td><?= esc((string) (($row['reference_voucher_no'] ?? '') !== '' ? $row['reference_voucher_no'] : trim((string) (($row['reference_table'] ?? '') . '#' . ($row['reference_id'] ?? '')), '#'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
