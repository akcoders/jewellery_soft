<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Add Lead</h4>
    <a href="<?= site_url('admin/leads') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/leads') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc(old('phone')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Source</label>
                    <select name="source_id" class="form-control">
                        <option value="">Select source</option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= esc((string) $source['id']) ?>" <?= old('source_id') == $source['id'] ? 'selected' : '' ?>>
                                <?= esc($source['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= esc(old('city')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stage</label>
                    <select name="stage" class="form-control" required>
                        <?php foreach ($leadStages as $stage): ?>
                            <option value="<?= esc($stage) ?>" <?= old('stage') === $stage ? 'selected' : '' ?>><?= esc($stage) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Requirement</label>
                    <textarea name="requirement_text" class="form-control" rows="3"><?= esc(old('requirement_text')) ?></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Reference Images</label>
                    <input type="file" name="lead_images[]" class="form-control" multiple>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Save Lead</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

