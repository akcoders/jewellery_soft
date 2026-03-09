<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Add Design</h4>
    <a href="<?= site_url('admin/designs') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/designs') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Design Code</label>
                    <input type="text" name="design_code" class="form-control" value="<?= esc(old('design_code')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= esc(old('category')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Image</label>
                    <input type="file" name="design_image" class="form-control">
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Save Design</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

