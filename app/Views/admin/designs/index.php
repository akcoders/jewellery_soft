<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Reusable production library</span>
        <h4 class="mb-1">Design Master</h4>
        <p class="mb-0">Completed fresh designs become reusable here with their karigar and material specifications.</p>
    </div>
    <?php if (admin_can('masters.designs.manage')): ?>
        <a href="<?= site_url('admin/designs/create') ?>" class="btn btn-primary">Add Design</a>
    <?php endif; ?>
</div>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Design Code</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th>Karigar</th>
                        <th>Gold Weights</th>
                        <th>Studded</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($designs === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No designs found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($designs as $design): ?>
                        <tr>
                            <td>
                                <?php if (! empty($design['image_path'])): ?>
                                    <a href="<?= base_url($design['image_path']) ?>" target="_blank" class="erp-design-thumb">
                                        <img src="<?= base_url($design['image_path']) ?>" alt="<?= esc($design['design_code'], 'attr') ?>">
                                    </a>
                                <?php else: ?>
                                    <span class="erp-design-thumb erp-design-thumb-empty"><i class="fe fe-image"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?= esc($design['design_code']) ?></span></td>
                            <td><div class="fw-semibold"><?= esc($design['name']) ?></div><small class="text-muted"><?= esc((string) ($design['purity_label'] ?: '-')) ?></small></td>
                            <td><div><?= esc($design['category'] ?: '-') ?></div><small class="text-muted"><?= esc((string) ($design['subcategory'] ?: '-')) ?></small></td>
                            <td><?= esc((string) ($design['source_karigar_name'] ?: '-')) ?></td>
                            <td>
                                <div>Gross: <strong><?= number_format((float) ($design['gross_weight_gm'] ?? 0), 3) ?> gm</strong></div>
                                <small class="text-muted">Net <?= number_format((float) ($design['net_gold_weight_gm'] ?? 0), 3) ?> · Pure <?= number_format((float) ($design['pure_gold_weight_gm'] ?? 0), 3) ?></small>
                            </td>
                            <td>
                                <div>Diamond: <strong><?= number_format((float) ($design['diamond_weight_cts'] ?? 0), 3) ?> cts</strong></div>
                                <small class="text-muted">Stone <?= number_format((float) ($design['stone_weight_cts'] ?? 0), 3) ?> cts</small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= esc((string) ($design['source_type'] ?: 'Manual')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

