<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Design Master</h4>
    <a href="<?= site_url('admin/designs/create') ?>" class="btn btn-primary">Add Design</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Design Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($designs === []): ?>
                        <tr><td colspan="4" class="text-center text-muted">No designs found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($designs as $design): ?>
                        <tr>
                            <td><?= esc($design['design_code']) ?></td>
                            <td><?= esc($design['name']) ?></td>
                            <td><?= esc($design['category'] ?: '-') ?></td>
                            <td>
                                <?php if (! empty($design['image_path'])): ?>
                                    <a href="<?= base_url($design['image_path']) ?>" target="_blank">View</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


