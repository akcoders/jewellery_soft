<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="mb-1">Gold Ledger</h4>
        <div class="text-muted small">One clean movement per transaction. Edited entries show their latest values.</div>
    </div>
    <a href="<?= site_url('admin/gold-inventory/stock') ?>" class="btn btn-outline-primary">Stock Summary</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold In</small>
            <strong><?= number_format((float) ($summary['debit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Out</small>
            <strong><?= number_format((float) ($summary['credit_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Balance</small>
            <strong><?= number_format((float) ($summary['balance_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Pure Gold Balance</small>
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
            <table class="table datatable table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Movement</th>
                        <th>Reference</th>
                        <th>Party / Location</th>
                        <th>Item</th>
                        <th class="text-end">In</th>
                        <th class="text-end">Out</th>
                        <th class="text-end">Gold Balance</th>
                        <th class="text-end">Pure Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <?php
                            $type = strtolower((string) ($row['txn_type'] ?? 'movement'));
                            $labels = [
                                'purchase' => ['Purchase', 'bg-success-subtle text-success'],
                                'issue' => ['Issued', 'bg-warning-subtle text-warning'],
                                'return' => ['Returned', 'bg-info-subtle text-info'],
                                'opening' => ['Opening', 'bg-primary-subtle text-primary'],
                                'adjustment' => ['Adjustment', 'bg-secondary-subtle text-secondary'],
                            ];
                            $typeKey = str_contains($type, 'purchase') ? 'purchase' : (str_contains($type, 'issue') ? 'issue' : (str_contains($type, 'return') ? 'return' : (str_contains($type, 'opening') ? 'opening' : 'adjustment')));
                            [$typeLabel, $typeClass] = $labels[$typeKey];
                            $reference = (string) (($row['reference_voucher_no'] ?? '') !== '' ? $row['reference_voucher_no'] : trim((string) (($row['reference_table'] ?? '') . '#' . ($row['reference_id'] ?? '')), '#'));
                        ?>
                        <tr>
                            <td><?= esc((string) ($row['txn_date'] ?? '')) ?></td>
                            <td><span class="badge rounded-pill <?= esc($typeClass) ?>"><?= esc($typeLabel) ?></span></td>
                            <td>
                                <strong class="d-block text-body"><?= esc($reference !== '' ? $reference : '-') ?></strong>
                                <?php if (! empty($row['notes'])): ?><small class="text-muted text-truncate d-block" style="max-width:220px"><?= esc((string) $row['notes']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <span class="d-block"><?= esc((string) ($row['karigar_name'] ?? '-')) ?></span>
                                <small class="text-muted"><?= esc((string) ($row['location_name'] ?? '-')) ?></small>
                            </td>
                            <td>
                                <strong><?= esc((string) ($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA')) ?></strong>
                                <small class="text-muted d-block"><?= esc((string) (($row['color_name'] ?: 'NA') . ' · ' . ($row['form_type'] ?: 'Raw'))) ?></small>
                            </td>
                            <td class="text-end text-success fw-semibold"><?= (float) ($row['debit_weight_gm'] ?? 0) > 0 ? number_format((float) $row['debit_weight_gm'], 3) : '-' ?></td>
                            <td class="text-end text-danger fw-semibold"><?= (float) ($row['credit_weight_gm'] ?? 0) > 0 ? number_format((float) $row['credit_weight_gm'], 3) : '-' ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float) ($row['balance_weight_gm'] ?? 0), 3) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float) ($row['balance_fine_gm'] ?? 0), 3) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
