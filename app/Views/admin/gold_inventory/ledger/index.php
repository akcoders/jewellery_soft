<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Ledger</h4>
    <a href="<?= site_url('admin/gold-inventory/stock') ?>" class="btn btn-outline-primary">Stock Summary</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Total In (gm)</small>
            <strong><?= number_format((float) ($summary['debit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Total Out (gm)</small>
            <strong><?= number_format((float) ($summary['credit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Net (gm)</small>
            <strong><?= number_format((float) ($summary['balance_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Fine In (gm)</small>
            <strong><?= number_format((float) ($summary['debit_fine'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Fine Out (gm)</small>
            <strong><?= number_format((float) ($summary['credit_fine'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Fine Net (gm)</small>
            <strong><?= number_format((float) ($summary['balance_fine'] ?? 0), 3) ?></strong>
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
                <label class="form-label">Type</label>
                <select name="txn_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($txnTypes ?? []) as $type): ?>
                        <option value="<?= esc($type) ?>" <?= ($filters['txn_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Item</label>
                <select name="item_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($itemOptions ?? []) as $item): ?>
                        <?php $label = ($item['purity_code'] ?: 'NA') . ' / ' . ($item['color_name'] ?: 'NA') . ' / ' . ($item['form_type'] ?: 'Raw'); ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (string) ($filters['item_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select name="order_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($orderOptions ?? []) as $order): ?>
                        <option value="<?= (int) $order['id'] ?>" <?= (string) ($filters['order_id'] ?? '') === (string) $order['id'] ? 'selected' : '' ?>><?= esc((string) $order['order_no']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($karigarOptions ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (string) ($filters['karigar_id'] ?? '') === (string) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Apply</button>
                <a href="<?= site_url('admin/gold-inventory/ledger') ?>" class="btn btn-light">Reset</a>
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
                        <th>Order</th>
                        <th>Karigar</th>
                        <th>Location</th>
                        <th>Item</th>
                        <th>Dr (gm)</th>
                        <th>Cr (gm)</th>
                        <th>Dr Fine</th>
                        <th>Cr Fine</th>
                        <th>Bal (gm)</th>
                        <th>Bal Fine</th>
                        <th>Rate/gm</th>
                        <th>Value</th>
                        <th>Ref</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="16" class="text-center text-muted">No ledger entries found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['txn_date'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['txn_type'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['location_name'] ?? '-')) ?></td>
                            <td><?= esc((($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA') . ' / ' . ($row['color_name'] ?: 'NA') . ' / ' . ($row['form_type'] ?: 'Raw'))) ?></td>
                            <td><?= number_format((float) ($row['debit_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['credit_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['debit_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['credit_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['balance_weight_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['balance_fine_gm'] ?? 0), 3) ?></td>
                            <td><?= $row['rate_per_gm'] === null ? '-' : number_format((float) $row['rate_per_gm'], 2) ?></td>
                            <td><?= $row['line_value'] === null ? '-' : number_format((float) $row['line_value'], 2) ?></td>
                            <td><?= esc((string) (($row['reference_voucher_no'] ?? '') !== '' ? $row['reference_voucher_no'] : trim((string) (($row['reference_table'] ?? '') . '#' . ($row['reference_id'] ?? '')), '#'))) ?></td>
                            <td><?= esc((string) ($row['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
