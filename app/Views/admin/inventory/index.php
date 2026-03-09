<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Inventory Stock & Operations</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/inventory/categories') ?>" class="btn btn-outline-primary"><i class="fe fe-folder"></i> Categories</a>
        <a href="<?= site_url('admin/inventory/products') ?>" class="btn btn-outline-primary"><i class="fe fe-package"></i> Products</a>
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
    <div class="card-header">
        <h5 class="card-title mb-0">Location Master</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/inventory/locations') ?>" class="row g-2 mb-3">
            <?= csrf_field() ?>
            <div class="col-md-5"><input type="text" name="name" class="form-control" placeholder="Location name" required></div>
            <div class="col-md-5">
                <select name="location_type" class="form-control" required>
                    <option value="Vault">Vault</option>
                    <option value="Store">Store</option>
                    <option value="WIP">WIP</option>
                    <option value="Showroom">Showroom</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
        </form>
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead><tr><th>Name</th><th>Type</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td><?= esc($loc['name']) ?></td>
                            <td><?= esc($loc['location_type']) ?></td>
                            <td><?= $loc['is_active'] ? 'Active' : 'Inactive' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Inventory Adjustment</h5></div>
            <div class="card-body">
                <form method="post" action="<?= site_url('admin/inventory/adjust') ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><input type="date" name="txn_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required></div>
                    <div class="col-md-6">
                        <select name="adjust_mode" class="form-control" required>
                            <option value="plus">Plus</option>
                            <option value="minus">Minus</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="location_id" class="form-control" required>
                            <option value="">Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= esc((string) $loc['id']) ?>"><?= esc($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="item_type" class="form-control" required>
                            <option value="Gold">Gold</option>
                            <option value="Diamond">Diamond</option>
                            <option value="Stone">Stone</option>
                            <option value="Finished Goods">Finished Goods</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="material_name" class="form-control" required>
                            <option value="">Material</option>
                            <?php foreach ($materialOptions as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="gold_purity_id" class="form-control">
                            <option value="">Gold Purity</option>
                            <?php foreach ($goldPurities as $p): ?>
                                <option value="<?= esc((string) $p['id']) ?>"><?= esc($p['purity_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" name="pcs" class="form-control" min="0" step="1" placeholder="PCS"></div>
                    <div class="col-md-3"><input type="number" name="weight_gm" class="form-control" min="0" step="0.001" placeholder="Weight gm"></div>
                    <div class="col-md-3"><input type="number" name="cts" class="form-control" min="0" step="0.001" placeholder="CTS"></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">Save</button></div>
                    <div class="col-md-12"><input type="text" name="notes" class="form-control" placeholder="Reason"></div>
                    <input type="hidden" name="diamond_shape" value="">
                    <input type="hidden" name="diamond_sieve" value="">
                    <input type="hidden" name="diamond_color" value="">
                    <input type="hidden" name="diamond_clarity" value="">
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Inventory Transfer</h5></div>
            <div class="card-body">
                <form method="post" action="<?= site_url('admin/inventory/transfer') ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><input type="date" name="txn_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required></div>
                    <div class="col-md-6">
                        <select name="item_type" class="form-control" required>
                            <option value="Gold">Gold</option>
                            <option value="Diamond">Diamond</option>
                            <option value="Stone">Stone</option>
                            <option value="Finished Goods">Finished Goods</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="from_location_id" class="form-control" required>
                            <option value="">From Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= esc((string) $loc['id']) ?>"><?= esc($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="to_location_id" class="form-control" required>
                            <option value="">To Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= esc((string) $loc['id']) ?>"><?= esc($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="material_name" class="form-control" required>
                            <option value="">Material</option>
                            <?php foreach ($materialOptions as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="gold_purity_id" class="form-control">
                            <option value="">Gold Purity</option>
                            <?php foreach ($goldPurities as $p): ?>
                                <option value="<?= esc((string) $p['id']) ?>"><?= esc($p['purity_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" name="pcs" class="form-control" min="0" step="1" placeholder="PCS"></div>
                    <div class="col-md-3"><input type="number" name="weight_gm" class="form-control" min="0" step="0.001" placeholder="Weight gm"></div>
                    <div class="col-md-3"><input type="number" name="cts" class="form-control" min="0" step="0.001" placeholder="CTS"></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">Transfer</button></div>
                    <div class="col-md-12"><input type="text" name="notes" class="form-control" placeholder="Transfer note"></div>
                    <input type="hidden" name="diamond_shape" value="">
                    <input type="hidden" name="diamond_sieve" value="">
                    <input type="hidden" name="diamond_color" value="">
                    <input type="hidden" name="diamond_clarity" value="">
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Inventory Master Data</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
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
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No inventory data available.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><?= esc($row['item_type']) ?></td>
                            <td><?= esc($row['material_name']) ?></td>
                            <td>
                                <?php
                                $grading = trim(
                                    ($row['purity_code'] ?? '') . ' ' .
                                    ($row['diamond_shape'] ?? '') . ' ' .
                                    ($row['diamond_sieve'] ?? '') . ' ' .
                                    ($row['diamond_color'] ?? '') . ' ' .
                                    ($row['diamond_clarity'] ?? '')
                                );
                                ?>
                                <?= esc($grading === '' ? '-' : $grading) ?>
                            </td>
                            <td><?= esc(number_format((float) $row['weight_gm'], 3)) ?></td>
                            <td><?= esc(number_format((float) $row['cts'], 3)) ?></td>
                            <td><?= esc((string) $row['pcs']) ?></td>
                            <td><?= esc($row['location_name'] ?? '-') ?></td>
                            <td><?= esc($row['reference_code'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Inventory Transactions</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th><th>Type</th><th>Location</th><th>Counter Location</th><th>Item Type</th><th>Material</th><th>PCS</th><th>gm</th><th>cts</th><th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No transactions.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?= esc((string) $tx['txn_date']) ?></td>
                            <td><?= esc($tx['transaction_type']) ?></td>
                            <td><?= esc($tx['location_name'] ?: '-') ?></td>
                            <td><?= esc($tx['counter_location_name'] ?: '-') ?></td>
                            <td><?= esc($tx['item_type']) ?></td>
                            <td><?= esc($tx['material_name']) ?></td>
                            <td><?= esc((string) $tx['pcs']) ?></td>
                            <td><?= esc(number_format((float) $tx['weight_gm'], 3)) ?></td>
                            <td><?= esc(number_format((float) $tx['cts'], 3)) ?></td>
                            <td><?= esc(($tx['reference_type'] ?: '-') . ($tx['reference_id'] ? ' #' . $tx['reference_id'] : '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


