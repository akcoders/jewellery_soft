<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .karigar-work-count {
        align-items: center;
        border-radius: 10px;
        display: inline-flex;
        font-size: .82rem;
        font-weight: 800;
        gap: 6px;
        min-width: 66px;
        padding: 7px 10px;
    }
    .karigar-work-count small { font-size: .65rem; font-weight: 600; opacity: .78; }
    .karigar-work-count.is-completed { background: #eaf8f1; color: #137647; }
    .karigar-work-count.is-pending { background: #fff4dc; color: #926600; }
    .karigar-work-count.is-zero { background: #f3f5f7; color: #778397; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Karigar Master</h4>
    <?php if (admin_can('masters.karigars.manage')): ?>
        <a href="<?= site_url('admin/karigars/create') ?>" class="btn btn-primary"><i class="fe fe-plus-circle"></i> Add Karigar</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Work Completed</th>
                        <th>Work Pending</th>
                        <th>Docs</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($karigars === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No karigar records.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($karigars as $k): ?>
                        <tr>
                            <td><?= esc($k['name']) ?></td>
                            <td><?= esc($k['department'] ?: '-') ?></td>
                            <td><?= esc($k['phone'] ?: '-') ?></td>
                            <td><?= esc($k['city'] ?: '-') ?></td>
                            <?php $completedWork = (int) ($k['completed_work_count'] ?? 0); ?>
                            <?php $pendingWork = (int) ($k['pending_work_count'] ?? 0); ?>
                            <td data-order="<?= $completedWork ?>">
                                <span class="karigar-work-count <?= $completedWork > 0 ? 'is-completed' : 'is-zero' ?>">
                                    <i class="fe fe-check-circle"></i><?= $completedWork ?> <small>orders</small>
                                </span>
                            </td>
                            <td data-order="<?= $pendingWork ?>">
                                <span class="karigar-work-count <?= $pendingWork > 0 ? 'is-pending' : 'is-zero' ?>">
                                    <i class="fe fe-clock"></i><?= $pendingWork ?> <small>orders</small>
                                </span>
                            </td>
                            <td><?= esc((string) ($k['document_count'] ?? 0)) ?></td>
                            <td>
                                <?php if ((int) $k['is_active'] === 1): ?>
                                    <span class="badge bg-success-light text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-light text-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= site_url('admin/karigars/' . $k['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fe fe-eye"></i>
                                </a>
                                <?php if (admin_can('masters.karigars.manage')): ?>
                                    <a href="<?= site_url('admin/karigars/' . $k['id'] . '/edit') ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('admin/karigars/' . $k['id'] . '/status') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="is_active" value="<?= (int) $k['is_active'] === 1 ? '0' : '1' ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm <?= (int) $k['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                            title="<?= (int) $k['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>"
                                            onclick="return confirm('Are you sure you want to <?= (int) $k['is_active'] === 1 ? 'deactivate' : 'activate' ?> this karigar?');"
                                        >
                                            <i class="fe <?= (int) $k['is_active'] === 1 ? 'fe-user-x' : 'fe-user-check' ?>"></i>
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
