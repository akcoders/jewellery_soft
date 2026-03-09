<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold gm</small>
            <strong><?= number_format((float) ($cards['gold_weight'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Fine gm</small>
            <strong><?= number_format((float) ($cards['gold_fine'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Value</small>
            <strong><?= number_format((float) ($cards['gold_value'] ?? 0), 2) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond pcs</small>
            <strong><?= number_format((float) ($cards['diamond_pcs'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond cts</small>
            <strong><?= number_format((float) ($cards['diamond_cts'] ?? 0), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond Value</small>
            <strong><?= number_format((float) ($cards['diamond_value'] ?? 0), 2) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/reports/inventory') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Purchase (gm)</small>
            <strong><?= number_format((float) (($movement['gold_purchase']['gm'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Issue (gm)</small>
            <strong><?= number_format((float) (($movement['gold_issue']['gm'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Gold Return (gm)</small>
            <strong><?= number_format((float) (($movement['gold_return']['gm'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond Purchase (cts)</small>
            <strong><?= number_format((float) (($movement['diamond_purchase']['cts'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond Issue (cts)</small>
            <strong><?= number_format((float) (($movement['diamond_issue']['cts'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-2">
            <small class="text-muted d-block">Diamond Return (cts)</small>
            <strong><?= number_format((float) (($movement['diamond_return']['cts'] ?? 0)), 3) ?></strong>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Gold Stock</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Purity</th>
                        <th>Color</th>
                        <th>Form</th>
                        <th>Weight gm</th>
                        <th>Fine gm</th>
                        <th>Avg Cost/gm</th>
                        <th>Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($goldRows ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No gold stock records.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($goldRows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['master_purity_code'] ?: $row['purity_code'] ?: 'NA')) ?></td>
                            <td><?= esc((string) ($row['color_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['form_type'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['weight_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['fine_balance_gm'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_cost_per_gm'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Diamond Stock</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Shape</th>
                        <th>Chalni</th>
                        <th>Color</th>
                        <th>Clarity</th>
                        <th>PCS</th>
                        <th>CTS</th>
                        <th>Avg Cost/cts</th>
                        <th>Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($diamondRows ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No diamond stock records.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($diamondRows ?? []) as $row): ?>
                        <?php $chalni = ($row['chalni_from'] !== null && $row['chalni_to'] !== null) ? ($row['chalni_from'] . '-' . $row['chalni_to']) : 'NA'; ?>
                        <tr>
                            <td><?= esc((string) ($row['diamond_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['shape'] ?? '-')) ?></td>
                            <td><?= esc((string) $chalni) ?></td>
                            <td><?= esc((string) ($row['color'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['clarity'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['pcs_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['carat_balance'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['avg_cost_per_carat'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['stock_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
