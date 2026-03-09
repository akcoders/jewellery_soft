<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Purity Master</h4>
    <a href="<?= site_url('admin/gold-inventory/purities/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Purity
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc((string) ($q ?? '')) ?>" class="form-control" placeholder="Purity code / percent / color">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-search"></i> Search</button>
                <a href="<?= site_url('admin/gold-inventory/purities') ?>" class="btn btn-light">Reset</a>
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
                        <th>ID</th>
                        <th>Purity Code</th>
                        <th>Purity %</th>
                        <th>Color</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No purity records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= esc((string) $row['purity_code']) ?></td>
                            <td><?= number_format((float) $row['purity_percent'], 3) ?></td>
                            <td><?= esc((string) ($row['color_name'] ?? '-')) ?></td>
                            <td><?= (int) ($row['product_count'] ?? 0) ?></td>
                            <td>
                                <?php if ((int) ($row['is_active'] ?? 0) === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/gold-inventory/purities/' . $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('admin/gold-inventory/purities/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Delete this purity?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

