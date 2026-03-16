<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">FG Items</small><strong><?= (int) ($cards['total_items'] ?? 0) ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">Gross Wt</small><strong><?= number_format((float) ($cards['gross_wt'] ?? 0), 3) ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">Net Gold</small><strong><?= number_format((float) ($cards['net_gold_wt'] ?? 0), 3) ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">Diamond Cts</small><strong><?= number_format((float) ($cards['diamond_cts'] ?? 0), 3) ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">Reserved</small><strong><?= (int) ($cards['reserved_items'] ?? 0) ?></strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2"><small class="text-muted d-block">Counter Items</small><strong><?= (int) ($cards['counter_items'] ?? 0) ?></strong></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Showroom Stock</h5>
        <?php if (admin_can('showroom.stock.manage')): ?>
            <div class="d-flex gap-2">
                <a href="<?= site_url('admin/showroom-stock/transfer') ?>" class="btn btn-primary"><i class="fe fe-arrow-right-circle"></i> Transfer FG</a>
                <a href="<?= site_url('admin/showroom-stock/allocation') ?>" class="btn btn-outline-primary"><i class="fe fe-grid"></i> Counter Allocation</a>
                <?php if (admin_can('showroom.reservations.manage')): ?>
                    <a href="<?= site_url('admin/showroom-stock/reservation') ?>" class="btn btn-outline-secondary"><i class="fe fe-bookmark"></i> Reserve Item</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Order</th>
                        <th>Showroom</th>
                        <th>Counter</th>
                        <th>Gross</th>
                        <th>Net Gold</th>
                        <th>Diamond</th>
                        <th>Status</th>
                        <th>Reserved For</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['diamond_cts'] ?? 0), 3) ?></td>
                            <td><span class="badge bg-info text-dark"><?= esc((string) ($row['showroom_stock_status'] ?? '-')) ?></span></td>
                            <td><?= esc((string) (($row['customer_name'] ?: $row['reserved_for_name']) ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Reservations</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Showroom</th>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Reserved For</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($reservations ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['customer_name'] ?: '-') ?? '-')) ?></td>
                            <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['reserved_for_name'] ?: '-') ?? '-')) ?></td>
                            <td><span class="badge <?= (string) ($row['reservation_status'] ?? '') === 'Reserved' ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= esc((string) ($row['reservation_status'] ?? '-')) ?></span></td>
                            <td><?= esc((string) ($row['expires_on'] ?? '-')) ?></td>
                            <td>
                                <?php if ((string) ($row['reservation_status'] ?? '') === 'Reserved' && admin_can('showroom.reservations.manage')): ?>
                                    <form method="post" action="<?= site_url('admin/showroom-stock/reservations/' . (int) $row['id'] . '/release') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-unlock"></i> Release</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h5 class="card-title mb-0">Movement History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Movement</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Remarks</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($movements ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['movement_type'] ?? '-')) ?></td>
                            <td><?= esc(trim((string) (($row['from_showroom_name'] ?? '-') . ' ' . (($row['from_counter_name'] ?? '') ? '/ ' . $row['from_counter_name'] : '')))) ?></td>
                            <td><?= esc(trim((string) (($row['to_showroom_name'] ?? '-') . ' ' . (($row['to_counter_name'] ?? '') ? '/ ' . $row['to_counter_name'] : '')))) ?></td>
                            <td><?= esc((string) ($row['remarks'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
