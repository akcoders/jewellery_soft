<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Warehouse</h4>
    <a href="<?= site_url('admin/inventory/warehouses') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/inventory/locations') ?>">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse Code</label>
                    <input type="text" name="code" class="form-control" value="<?= esc((string) old('code')) ?>" placeholder="WH-FAC">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Warehouse Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc((string) old('name')) ?>" placeholder="Warehouse name" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Warehouse Type</label>
                    <select name="location_type" class="form-control" required>
                        <?php foreach (($types ?? []) as $type): ?>
                            <option value="<?= esc($type) ?>" <?= old('location_type', 'Store') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Warehouse address"><?= esc((string) old('address')) ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Default Bins</label>
                    <input type="text" name="default_bins" class="form-control" value="<?= esc((string) old('default_bins', 'MAIN')) ?>" placeholder="MAIN, HOLD, QC">
                    <small class="text-muted">Comma separated bin codes. `MAIN` is auto-added if left blank.</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Warehouse</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
