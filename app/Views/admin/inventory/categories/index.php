<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Product Categories</h4>
    <a href="<?= site_url('admin/inventory/categories/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Category
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($categories ?? []) === []): ?>
                        <tr><td colspan="5" class="text-center text-muted">No categories found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($categories ?? []) as $category): ?>
                        <tr>
                            <td><?= esc((string) $category['name']) ?></td>
                            <td><?= esc((string) ($category['description'] ?: '-')) ?></td>
                            <td>
                                <?php if ((int) $category['is_active'] === 1): ?>
                                    <span class="badge bg-success-light text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-light text-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc((string) ($category['product_count'] ?? 0)) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/inventory/categories/' . $category['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form action="<?= site_url('admin/inventory/categories/' . $category['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this category?');">
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

