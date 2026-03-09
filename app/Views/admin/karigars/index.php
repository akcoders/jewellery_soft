<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Karigar Master</h4>
    <a href="<?= site_url('admin/karigars/create') ?>" class="btn btn-primary"><i class="fe fe-plus-circle"></i> Add Karigar</a>
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
                        <th>Rate / gm</th>
                        <th>Wastage %</th>
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
                            <td><?= esc(number_format((float) $k['rate_per_gm'], 2)) ?></td>
                            <td><?= esc(number_format((float) ($k['wastage_percentage'] ?? 0), 2)) ?>%</td>
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
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
