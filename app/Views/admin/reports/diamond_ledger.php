<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Issued (cts)</small>
            <strong><?= number_format((float) ($cards['issue_cts'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Returned (cts)</small>
            <strong><?= number_format((float) ($cards['return_cts'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Balance (cts)</small>
            <strong><?= number_format((float) ($cards['balance_cts'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Balance (pcs)</small>
            <strong><?= number_format((float) ($cards['balance_pcs'] ?? 0), 3) ?></strong>
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label">Order No</label>
                <input type="text" name="order_no" class="form-control" value="<?= esc((string) ($filters['order_no'] ?? '')) ?>" placeholder="Order no">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/diamond-ledger') ?>" class="btn btn-light">Reset</a>
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
                        <th>Party</th>
                        <th>Purpose</th>
                        <th>Item</th>
                        <th>Chalni</th>
                        <th>Color</th>
                        <th>Clarity</th>
                        <th>PCS</th>
                        <th>CTS</th>
                        <th>Rate/cts</th>
                        <th>Value</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="15" class="text-center text-muted">No records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <?php $chalni = ($row['chalni_from'] !== null && $row['chalni_to'] !== null) ? ($row['chalni_from'] . '-' . $row['chalni_to']) : 'NA'; ?>
                        <tr>
                            <td><?= esc((string) ($row['txn_date'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['txn_type'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['party_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['purpose'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['diamond_type'] ?? '-') . ' / ' . ($row['shape'] ?? '-'))) ?></td>
                            <td><?= esc((string) $chalni) ?></td>
                            <td><?= esc((string) ($row['color'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['clarity'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['carat'] ?? 0), 3) ?></td>
                            <td><?= $row['rate_per_carat'] === null ? '-' : number_format((float) $row['rate_per_carat'], 2) ?></td>
                            <td><?= $row['line_value'] === null ? '-' : number_format((float) $row['line_value'], 2) ?></td>
                            <td><?= esc((string) ($row['reference_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
