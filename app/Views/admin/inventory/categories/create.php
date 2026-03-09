<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Product Category</h4>
    <a href="<?= site_url('admin/inventory/categories') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/inventory/categories') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc((string) old('name')) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= old('is_active', '1') === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= old('is_active') === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= esc((string) old('description')) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Category</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

