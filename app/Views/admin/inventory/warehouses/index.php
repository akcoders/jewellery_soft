<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Warehouses</h4>
    <a href="<?= site_url('admin/inventory/warehouses/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Warehouse
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($locations ?? []) === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No warehouses found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($locations ?? []) as $loc): ?>
                        <tr>
                            <td><?= esc((string) ($loc['code'] ?? '-')) ?></td>
                            <td><?= esc((string) $loc['name']) ?></td>
                            <td><?= esc((string) $loc['location_type']) ?></td>
                            <td><?= esc((string) ($loc['address'] ?? '-')) ?></td>
                            <td><?= (int) ($loc['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
                            <td><?= esc((string) ($loc['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-lg-5 d-flex">
        <div class="card w-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Create Bin</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('admin/inventory/bins') ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Warehouse</label>
                        <select name="location_id" class="form-control" required>
                            <option value="">Select warehouse</option>
                            <?php foreach (($locations ?? []) as $loc): ?>
                                <option value="<?= esc((string) $loc['id']) ?>"><?= esc((string) $loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Bin Code</label>
                        <input type="text" name="bin_code" class="form-control" placeholder="MAIN" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Bin Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Main Bin" required>
                    </div>
                    <div class="col-12 mt-1">
                        <button type="submit" class="btn btn-primary w-100">Create Bin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7 d-flex">
        <div class="card w-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Warehouse Bins</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th>Bin Code</th>
                                <th>Bin Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (($bins ?? []) === []): ?>
                                <tr><td colspan="4" class="text-center text-muted">No bins found.</td></tr>
                            <?php endif; ?>
                            <?php foreach (($bins ?? []) as $bin): ?>
                                <tr>
                                    <td><?= esc((string) ($bin['warehouse_name'] ?? '-')) ?></td>
                                    <td><?= esc((string) ($bin['bin_code'] ?? '-')) ?></td>
                                    <td><?= esc((string) ($bin['name'] ?? '-')) ?></td>
                                    <td><?= (int) ($bin['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
