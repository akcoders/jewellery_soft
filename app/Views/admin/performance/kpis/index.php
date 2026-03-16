<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">KPI Master</h5><?php if (admin_can('performance.kpis.manage')): ?><a href="<?= site_url('admin/performance/kpis/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Create KPI</a><?php endif; ?></div>
    <div class="card-body"><div class="table-responsive"><table class="table datatable table-hover mb-0"><thead><tr><th>Code</th><th>Name</th><th>Module</th><th>Metric Key</th><th>Unit</th><th>Period</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach (($rows ?? []) as $row): ?><tr><td><?= esc((string) ($row['kpi_code'] ?? '-')) ?></td><td><?= esc((string) ($row['name'] ?? '-')) ?></td><td><?= esc((string) ($row['module_group'] ?? '-')) ?></td><td><?= esc((string) ($row['metric_key'] ?? '-')) ?></td><td><?= esc((string) ($row['unit'] ?? '-')) ?></td><td><?= esc((string) ($row['period_type'] ?? '-')) ?></td><td><span class="badge <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td><td><?php if (admin_can('performance.kpis.manage')): ?><a href="<?= site_url('admin/performance/kpis/' . (int) $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit"></i></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?= $this->endSection() ?>
