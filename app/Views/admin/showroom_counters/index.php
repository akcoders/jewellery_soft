<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Showroom Counter Master</h5>
        <?php if (admin_can('showroom.masters.manage')): ?>
            <a href="<?= site_url('admin/showroom-counters/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Add Counter</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Showroom</th>
                        <th>Counter Code</th>
                        <th>Counter Name</th>
                        <th>Type</th>
                        <th>Incharge</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['counter_code'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['counter_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['incharge_name'] ?? '-')) ?></td>
                            <td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <?php if (admin_can('showroom.masters.manage')): ?>
                                    <a href="<?= site_url('admin/showroom-counters/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/showroom-counters/' . (int) $row['id'] . '/status') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fe fe-refresh-cw"></i></button>
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
