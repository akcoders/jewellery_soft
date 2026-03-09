<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Product</h4>
    <a href="<?= site_url('admin/inventory/products') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/inventory/products/' . $product['id'] . '/update') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Product Code</label>
                    <input type="text" name="product_code" class="form-control" value="<?= esc((string) old('product_code', (string) $product['product_code'])) ?>" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="product_name" class="form-control" value="<?= esc((string) old('product_name', (string) $product['product_name'])) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">Select category</option>
                        <?php foreach (($categories ?? []) as $category): ?>
                            <option value="<?= esc((string) $category['id']) ?>" <?= (string) old('category_id', (string) ($product['category_id'] ?? '')) === (string) $category['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Item Type</label>
                    <select name="item_type" class="form-control" required>
                        <?php foreach (($itemTypes ?? []) as $itemType): ?>
                            <option value="<?= esc($itemType) ?>" <?= old('item_type', (string) $product['item_type']) === $itemType ? 'selected' : '' ?>><?= esc($itemType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Unit Type</label>
                    <select name="unit_type" class="form-control" required>
                        <?php foreach (($unitTypes ?? []) as $unitType): ?>
                            <option value="<?= esc($unitType) ?>" <?= old('unit_type', (string) $product['unit_type']) === $unitType ? 'selected' : '' ?>><?= esc($unitType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= old('is_active', (string) $product['is_active']) === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= old('is_active', (string) $product['is_active']) === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= esc((string) old('description', (string) ($product['description'] ?? ''))) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Update Product</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

