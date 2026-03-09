<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Products</h4>
    <a href="<?= site_url('admin/inventory/products/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Product
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
                        <th>Category</th>
                        <th>Item Type</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($products ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No products found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($products ?? []) as $product): ?>
                        <tr>
                            <td><?= esc((string) $product['product_code']) ?></td>
                            <td><?= esc((string) $product['product_name']) ?></td>
                            <td><?= esc((string) ($product['category_name'] ?: '-')) ?></td>
                            <td><?= esc((string) $product['item_type']) ?></td>
                            <td><?= esc((string) $product['unit_type']) ?></td>
                            <td>
                                <?php if ((int) $product['is_active'] === 1): ?>
                                    <span class="badge bg-success-light text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-light text-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/inventory/products/' . $product['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form action="<?= site_url('admin/inventory/products/' . $product['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this product?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fe fe-trash-2"></i>
                                        </button>
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

