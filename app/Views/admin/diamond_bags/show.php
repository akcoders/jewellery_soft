<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Bag: <?= esc($bag['bag_no']) ?></h4>
    <div class="d-flex gap-2">
        <?php if (! empty($bag['has_issue'])): ?>
            <button type="button" class="btn btn-outline-secondary" disabled title="Cannot edit after issue">
                <i class="fe fe-edit"></i> Edit
            </button>
            <button type="button" class="btn btn-outline-secondary" disabled title="Cannot delete after issue">
                <i class="fe fe-trash"></i> Delete
            </button>
        <?php else: ?>
            <a href="<?= site_url('admin/diamond-bags/' . $bag['id'] . '/edit') ?>" class="btn btn-outline-warning">
                <i class="fe fe-edit"></i> Edit
            </a>
            <form method="post" action="<?= site_url('admin/diamond-bags/' . $bag['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this bag? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fe fe-trash"></i> Delete
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('admin/diamond-bags') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <p class="mb-1"><strong>Order Reference:</strong> <?= esc($bag['order_no'] ?: '-') ?></p>
        <p class="mb-1"><strong>Status:</strong> <?= ! empty($bag['has_issue']) ? 'Issued' : 'Not Issued' ?></p>
        <p class="mb-0"><strong>Notes:</strong> <?= esc($bag['notes'] ?: '-') ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Bag Rows</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Color</th>
                        <th>Quality</th>
                        <th>Total PCS</th>
                        <th>Total CTS</th>
                        <th>Available PCS</th>
                        <th>Available CTS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No rows found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><?= esc($row['diamond_type']) ?></td>
                            <td><?= esc($row['size']) ?></td>
                            <td><?= esc($row['color']) ?></td>
                            <td><?= esc($row['quality']) ?></td>
                            <td><?= esc((string) $row['pcs_total']) ?></td>
                            <td><?= esc(number_format((float) $row['weight_cts_total'], 3)) ?></td>
                            <td><?= esc((string) $row['pcs_available']) ?></td>
                            <td><?= esc(number_format((float) $row['weight_cts_available'], 3)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
