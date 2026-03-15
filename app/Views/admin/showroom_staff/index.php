<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Showroom Staff Assignment</h5>
        <?php if (admin_can('showroom.masters.manage')): ?>
            <a href="<?= site_url('admin/showroom-staff/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Assign Staff</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Showroom</th>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Role</th>
                        <th>Primary</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['employee_code'] ?? '-')) ?> - <?= esc((string) ($row['full_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['designation_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['role_label'] ?? '-')) ?></td>
                            <td><span class="badge <?= (int) ($row['is_primary'] ?? 0) === 1 ? 'bg-success' : 'bg-light text-dark' ?>"><?= (int) ($row['is_primary'] ?? 0) === 1 ? 'Primary' : 'Secondary' ?></span></td>
                            <td><?= esc((string) ($row['effective_from'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['effective_to'] ?? '-')) ?></td>
                            <td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <?php if (admin_can('showroom.masters.manage')): ?>
                                    <a href="<?= site_url('admin/showroom-staff/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/showroom-staff/' . (int) $row['id'] . '/status') ?>" class="d-inline">
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
