<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Design library</span>
        <h4 class="mb-1">Add Design</h4>
        <p class="mb-0">Store the image, classification and production weight standard together.</p>
    </div>
    <a href="<?= site_url('admin/designs') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card erp-form-card">
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
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sub Category</label>
                    <input type="text" name="subcategory" class="form-control" value="<?= esc(old('subcategory')) ?>" placeholder="Ring, Jhumki, Haaram...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Karigar</label>
                    <select name="source_karigar_id" class="form-select js-searchable-select" data-placeholder="Search karigar">
                        <option value="">Select karigar</option>
                        <?php foreach (($karigars ?? []) as $karigar): ?>
                            <option value="<?= (int) $karigar['id'] ?>" <?= (string) old('source_karigar_id') === (string) $karigar['id'] ? 'selected' : '' ?>><?= esc($karigar['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Purity</label>
                    <input type="text" name="purity_label" class="form-control" value="<?= esc(old('purity_label')) ?>" placeholder="18KT / 22KT">
                </div>
                <?php foreach ([
                    'gross_weight_gm' => 'Gross Weight (gm)',
                    'net_gold_weight_gm' => 'Net Gold Weight (gm)',
                    'pure_gold_weight_gm' => 'Pure Gold Weight (gm)',
                    'diamond_weight_cts' => 'Diamond Weight (cts)',
                    'stone_weight_cts' => 'Stone Weight (cts)',
                ] as $field => $label): ?>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><?= esc($label) ?></label>
                        <input type="number" name="<?= esc($field) ?>" class="form-control" min="0" step="0.001" value="<?= esc(old($field)) ?>">
                    </div>
                <?php endforeach; ?>
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
