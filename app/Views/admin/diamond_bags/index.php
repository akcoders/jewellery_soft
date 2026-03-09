<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Diamond Bagging</h4>
    <a href="<?= site_url('admin/diamond-bags/create') ?>" class="btn btn-primary">Create Bag</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Bag No</th>
                        <th>Order Ref</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bags === []): ?>
                        <tr><td colspan="5" class="text-center text-muted">No bags found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($bags as $bag): ?>
                        <tr>
                            <td><?= esc($bag['bag_no']) ?></td>
                            <td><?= esc($bag['order_no'] ?: '-') ?></td>
                            <td>
                                <?php if (! empty($bag['has_issue'])): ?>
                                    <span class="badge bg-danger">Issued</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Not Issued</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc((string) $bag['created_at']) ?></td>
                            <td class="d-flex gap-1">
                                <a href="<?= site_url('admin/diamond-bags/' . $bag['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fe fe-eye"></i>
                                </a>
                                <?php if (! empty($bag['has_issue'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Cannot edit after issue" disabled>
                                        <i class="fe fe-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Cannot delete after issue" disabled>
                                        <i class="fe fe-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="<?= site_url('admin/diamond-bags/' . $bag['id'] . '/edit') ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('admin/diamond-bags/' . $bag['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this bag? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fe fe-trash"></i>
                                        </button>
                                    </form>
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
