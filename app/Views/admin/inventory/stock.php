<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Inventory Stock</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/inventory/warehouses') ?>" class="btn btn-outline-primary"><i class="fe fe-home"></i> Warehouses</a>
        <a href="<?= site_url('admin/inventory/adjustments') ?>" class="btn btn-outline-primary"><i class="fe fe-edit-3"></i> Adjustments</a>
        <a href="<?= site_url('admin/inventory/transactions') ?>" class="btn btn-outline-primary"><i class="fe fe-repeat"></i> Transactions</a>
    </div>
</div>

<div class="row">
    <div class="col-xl-4 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Gold Stock</h6>
                <h3 class="mb-0"><?= esc(number_format((float) $summary['gold_weight'], 3)) ?> gm</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Diamond Stock</h6>
                <h3 class="mb-0"><?= esc(number_format((float) $summary['diamond_cts'], 3)) ?> cts</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-6 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">FG SKUs</h6>
                <h3 class="mb-0"><?= esc((string) $summary['finished_count']) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Stock List</h5>
        <?php if (($balances ?? []) !== []): ?>
            <span class="badge bg-success">Live Balance Engine</span>
        <?php else: ?>
            <span class="badge bg-secondary">Legacy Snapshot</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <?php if (($balances ?? []) !== []): ?>
                <table class="table datatable table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Material</th>
                            <th>Purity/Grading</th>
                            <th>Packet/Lot</th>
                            <th>Weight (gm)</th>
                            <th>CTS</th>
                            <th>PCS</th>
                            <th>Fine Gold</th>
                            <th>Warehouse</th>
                            <th>Bin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($balances ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['item_type'] ?? '-')) ?></td>
                                <td><?= esc((string) ($row['material_name'] ?? '-')) ?></td>
                                <td>
                                    <?php
                                    $grading = trim(
                                        (string) ($row['purity_code'] ?? '') . ' ' .
                                        (string) ($row['diamond_shape'] ?? '') . ' ' .
                                        (string) ($row['diamond_sieve'] ?? '') . ' ' .
                                        (string) ($row['diamond_color'] ?? '') . ' ' .
                                        (string) ($row['diamond_clarity'] ?? '')
                                    );
                                    ?>
                                    <?= esc($grading === '' ? '-' : $grading) ?>
                                </td>
                                <td>
                                    <?php
                                    $packetLot = trim((string) (($row['packet_no'] ?? '') !== '' ? 'PKT: ' . $row['packet_no'] : '') . ' ' . (($row['lot_no'] ?? '') !== '' ? 'LOT: ' . $row['lot_no'] : ''));
                                    ?>
                                    <?= esc($packetLot === '' ? '-' : $packetLot) ?>
                                </td>
                                <td><?= esc(number_format((float) ($row['weight_gm_balance'] ?? 0), 3)) ?></td>
                                <td><?= esc(number_format((float) ($row['cts_balance'] ?? 0), 3)) ?></td>
                                <td><?= esc(number_format((float) ($row['pcs_balance'] ?? 0), 3)) ?></td>
                                <td><?= esc(number_format((float) ($row['fine_gold_balance'] ?? 0), 3)) ?></td>
                                <td><?= esc((string) ($row['warehouse_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($row['bin_name'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table datatable table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Material</th>
                            <th>Purity/Grading</th>
                            <th>Weight (gm)</th>
                            <th>CTS</th>
                            <th>PCS</th>
                            <th>Location</th>
                            <th>Ref</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($items ?? []) === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No inventory stock available.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($items ?? []) as $row): ?>
                            <tr>
                                <td><?= esc((string) $row['item_type']) ?></td>
                                <td><?= esc((string) $row['material_name']) ?></td>
                                <td>
                                    <?php
                                    $grading = trim(
                                        (string) ($row['purity_code'] ?? '') . ' ' .
                                        (string) ($row['diamond_shape'] ?? '') . ' ' .
                                        (string) ($row['diamond_sieve'] ?? '') . ' ' .
                                        (string) ($row['diamond_color'] ?? '') . ' ' .
                                        (string) ($row['diamond_clarity'] ?? '')
                                    );
                                    ?>
                                    <?= esc($grading === '' ? '-' : $grading) ?>
                                </td>
                                <td><?= esc(number_format((float) ($row['weight_gm'] ?? 0), 3)) ?></td>
                                <td><?= esc(number_format((float) ($row['cts'] ?? 0), 3)) ?></td>
                                <td><?= esc((string) ($row['pcs'] ?? 0)) ?></td>
                                <td><?= esc((string) ($row['location_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($row['reference_code'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
