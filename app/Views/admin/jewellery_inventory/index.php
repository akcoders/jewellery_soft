<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="mb-1">Jewellery Inventory</h4>
        <p class="text-muted mb-0">Completed jewellery remains traceable after transfer or delivery.</p>
    </div>
    <form method="get" class="d-flex gap-2">
        <select name="karigar_id" class="form-select">
            <option value="">All Karigars</option>
            <?php foreach (($karigars ?? []) as $karigar): ?>
                <option value="<?= (int) $karigar['id'] ?>" <?= (int) ($karigarId ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary">Filter</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">Active Jewellery</small><h4 class="mb-0"><?= (int) ($summary['active_items'] ?? 0) ?></h4></div></div></div>
    <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">Transferred / Delivered</small><h4 class="mb-0"><?= (int) ($summary['closed_items'] ?? 0) ?></h4></div></div></div>
    <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">Active Gross Weight</small><h4 class="mb-0"><?= number_format((float) ($summary['gross_weight'] ?? 0), 3) ?> gm</h4></div></div></div>
    <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">Active Net Gold</small><h4 class="mb-0"><?= number_format((float) ($summary['net_gold_weight'] ?? 0), 3) ?> gm</h4></div></div></div>
    <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">Active Diamond</small><h4 class="mb-0"><?= number_format((float) ($summary['diamond_cts'] ?? 0), 3) ?> cts</h4></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="card-title mb-0">Active Finished Jewellery</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover align-middle mb-0" data-dt-page-length="25">
                <thead><tr><th>Picture</th><th>Tag / Order</th><th>Karigar / Group</th><th>Design</th><th>Weights</th><th>Studded Details</th><th>Value / Labour</th><th>Status</th><th>Transfer / Deliver</th></tr></thead>
                <tbody>
                    <?php if (($activeRows ?? []) === []): ?><tr><td colspan="9" class="text-center text-muted">No active jewellery inventory.</td></tr><?php endif; ?>
                    <?php foreach (($activeRows ?? []) as $row): ?>
                        <?php $stones = json_decode((string) ($row['studded_details_json'] ?? '[]'), true); ?>
                        <tr>
                            <td>
                                <?php if ((int) ($row['production_ready_item_id'] ?? 0) > 0 && trim((string) ($row['source_image_path'] ?? '')) !== ''): ?>
                                    <a href="<?= site_url('admin/jewellery-inventory/image/' . (int) $row['production_ready_item_id']) ?>" target="_blank"><img src="<?= site_url('admin/jewellery-inventory/image/' . (int) $row['production_ready_item_id']) ?>" alt="<?= esc((string) ($row['design_name'] ?? 'Jewellery')) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:10px"></a>
                                <?php else: ?><span class="text-muted">Source image not provided</span><?php endif; ?>
                            </td>
                            <td><strong><?= esc((string) ($row['tag_no'] ?? '-')) ?></strong><br><small class="text-muted"><?= esc((string) ($row['order_no'] ?? '-')) ?></small></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?><br><small class="text-muted"><?= esc((string) ($row['ready_group'] ?? '-')) ?></small></td>
                            <td><?= esc((string) ($row['design_name'] ?? '-')) ?><br><small class="text-muted"><?= esc((string) ($row['purity_label'] ?? '-')) ?></small></td>
                            <td class="text-nowrap">Gross <?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?> gm<br>Net <?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?> gm<br>Diamond <?= number_format((float) ($row['diamond_cts'] ?? 0), 3) ?> cts</td>
                            <td>
                                <?php if (! is_array($stones) || $stones === []): ?><span class="text-muted">None recorded</span><?php endif; ?>
                                <?php foreach (is_array($stones) ? $stones : [] as $stone): ?>
                                    <div class="small"><strong><?= esc((string) (($stone['name'] ?? '') ?: 'Stone')) ?></strong>: <?= number_format((float) ($stone['pcs'] ?? 0), 0) ?> pcs / <?= number_format((float) ($stone['weight'] ?? 0), 3) ?> cts<?= (float) ($stone['rate'] ?? 0) !== 0.0 ? ' @ ₹' . number_format((float) $stone['rate'], 2) : '' ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td>₹<?= number_format((float) ($row['total_value'] ?? 0), 2) ?><br><small class="text-muted">Labour ₹<?= number_format((float) ($row['labour_charges'] ?? 0), 2) ?> · <?= esc((string) ($row['payment_status'] ?? 'Pending')) ?></small></td>
                            <td><span class="badge bg-success"><?= esc((string) (($row['showroom_stock_status'] ?? '') ?: ($row['status'] ?? 'AVAILABLE'))) ?></span></td>
                            <td style="min-width:250px">
                                <form method="post" action="<?= site_url('admin/jewellery-inventory/' . (int) $row['id'] . '/close') ?>" onsubmit="return confirm('Remove this item from active jewellery inventory and retain it in history?')">
                                    <?= csrf_field() ?>
                                    <div class="input-group input-group-sm mb-1"><select name="inventory_status" class="form-select" required><option value="TRANSFERRED">Transferred</option><option value="DELIVERED">Delivered</option></select><button class="btn btn-outline-danger">Save</button></div>
                                    <input type="text" name="remark" class="form-control form-control-sm" maxlength="1000" placeholder="Transfer/delivery remark (required)" required>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Inventory Record &amp; Transfer / Delivery History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0" data-dt-page-length="25">
                <thead><tr><th>Date</th><th>Tag</th><th>Order</th><th>Design</th><th>Action</th><th>Remark</th></tr></thead>
                <tbody>
                    <?php if (($history ?? []) === []): ?><tr><td colspan="6" class="text-center text-muted">No transfer or delivery history.</td></tr><?php endif; ?>
                    <?php foreach (($history ?? []) as $row): ?>
                        <tr><td><?= esc((string) ($row['created_at'] ?? '-')) ?></td><td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td><td><?= esc((string) ($row['order_no'] ?? '-')) ?></td><td><?= esc((string) ($row['design_name'] ?? '-')) ?></td><td><span class="badge bg-secondary"><?= esc(str_replace('INVENTORY_', '', (string) ($row['movement_type'] ?? '-'))) ?></span></td><td><?= esc((string) ($row['remarks'] ?? '-')) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
