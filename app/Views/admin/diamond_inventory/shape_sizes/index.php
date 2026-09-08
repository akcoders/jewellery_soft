<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div class="erp-toolbar-copy">
        <span class="erp-eyebrow">Diamond traceability</span>
        <h4>Shape &amp; Size Master</h4>
        <p>Standardise the physical shape and calibrated size printed on every diamond bag.</p>
    </div>
    <a href="<?= site_url('admin/diamond-inventory/bags') ?>" class="btn btn-primary"><i class="fe fe-package me-1"></i>Diamond Bags</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-5">
        <form method="post" action="<?= site_url('admin/diamond-inventory/shape-sizes/shapes') ?>" class="card h-100">
            <?= csrf_field() ?>
            <div class="card-header"><h6 class="mb-0">Add Shape</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-5"><label class="form-label">Code *</label><input name="code" class="form-control text-uppercase" required maxlength="30" placeholder="ROUND"></div>
                    <div class="col-sm-5"><label class="form-label">Shape Name *</label><input name="name" class="form-control" required maxlength="80" placeholder="Round"></div>
                    <div class="col-sm-2"><label class="form-label">Sort</label><input name="sort_order" type="number" min="0" class="form-control" value="0"></div>
                </div>
            </div>
            <div class="card-footer"><button class="btn btn-primary">Add Shape</button></div>
        </form>
    </div>
    <div class="col-xl-7">
        <form method="post" action="<?= site_url('admin/diamond-inventory/shape-sizes/sizes') ?>" class="card h-100">
            <?= csrf_field() ?>
            <div class="card-header"><h6 class="mb-0">Add Shape-wise Size</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Shape *</label><select name="shape_id" class="form-select js-select2" required><option value="">Select</option><?php foreach ($shapes as $shape): ?><?php if ((int) $shape['is_active'] === 1): ?><option value="<?= (int) $shape['id'] ?>"><?= esc((string) $shape['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Size Code *</label><input name="size_code" class="form-control text-uppercase" required placeholder="1.20-1.30"></div>
                    <div class="col-md-4"><label class="form-label">Display Label *</label><input name="size_label" class="form-control" required placeholder="1.20–1.30 mm"></div>
                    <div class="col-md-4"><label class="form-label">Min mm</label><input name="min_mm" type="number" step="0.001" min="0" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Max mm</label><input name="max_mm" type="number" step="0.001" min="0" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Sort</label><input name="sort_order" type="number" min="0" class="form-control" value="0"></div>
                </div>
            </div>
            <div class="card-footer"><button class="btn btn-primary">Add Size</button></div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h6 class="mb-0">Shapes</h6></div><div class="table-responsive">
            <table class="table datatable align-middle mb-0"><thead><tr><th>Code</th><th>Name</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($shapes as $shape): ?><tr><td><span class="badge bg-light text-dark"><?= esc((string) $shape['code']) ?></span></td><td><?= esc((string) $shape['name']) ?></td><td><span class="badge <?= (int) $shape['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $shape['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><form method="post" action="<?= site_url('admin/diamond-inventory/shape-sizes/shapes/' . (int) $shape['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary"><?= (int) $shape['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card"><div class="card-header"><h6 class="mb-0">Shape-wise Sizes</h6></div><div class="table-responsive">
            <table class="table datatable align-middle mb-0"><thead><tr><th>Shape</th><th>Code</th><th>Label</th><th>Range</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($sizes as $size): ?><tr><td><?= esc((string) $size['shape_name']) ?></td><td><strong><?= esc((string) $size['size_code']) ?></strong></td><td><?= esc((string) $size['size_label']) ?></td><td><?= $size['min_mm'] === null ? '-' : esc(number_format((float) $size['min_mm'], 3) . '–' . number_format((float) $size['max_mm'], 3) . ' mm') ?></td><td><span class="badge <?= (int) $size['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $size['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><form method="post" action="<?= site_url('admin/diamond-inventory/shape-sizes/sizes/' . (int) $size['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary"><?= (int) $size['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
