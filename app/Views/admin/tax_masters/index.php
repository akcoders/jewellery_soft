<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .tax-master-grid { display:grid; gap:18px; grid-template-columns:minmax(280px,.75fr) minmax(0,1.6fr); }
    .tax-master-panel { background:#fff; border:1px solid #dfe5ec; border-radius:14px; overflow:hidden; }
    .tax-master-panel-header { background:#f8fafc; border-bottom:1px solid #e4e9ef; padding:16px 18px; }
    .tax-master-panel-body { padding:18px; }
    .tax-component-row { display:grid; gap:10px; grid-template-columns:minmax(0,1fr) 130px 42px; margin-bottom:10px; }
    .tax-chip { background:#eef4ff; border:1px solid #d6e2f5; border-radius:999px; color:#29456d; display:inline-flex; font-size:.72rem; font-weight:700; margin:2px; padding:.25rem .55rem; }
    @media (max-width:991.98px) { .tax-master-grid { grid-template-columns:1fr; } }
    @media (max-width:575.98px) { .tax-component-row { grid-template-columns:1fr 100px 38px; } .tax-master-panel-body { padding:14px; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div><span class="erp-eyebrow">Accounts setup</span><h4 class="mb-1">Tax &amp; GST Masters</h4><p class="mb-0">Create reusable tax combinations once and select them on purchase and labour bills.</p></div>
</div>

<div class="tax-master-grid">
    <section class="tax-master-panel">
        <div class="tax-master-panel-header"><h5 class="mb-1">Tax Types</h5><small class="text-muted">Names only, such as CGST, SGST or IGST.</small></div>
        <div class="tax-master-panel-body">
            <form method="post" action="<?= site_url('admin/accounts/tax-masters/tax-types') ?>" class="d-flex gap-2 mb-3">
                <?= csrf_field() ?><input type="text" name="name" class="form-control" maxlength="80" placeholder="Tax type name" required><button class="btn btn-primary">Add</button>
            </form>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Status</th><th></th></tr></thead><tbody>
                <?php foreach (($taxTypes ?? []) as $type): ?><tr><td class="fw-semibold"><?= esc((string) $type['name']) ?></td><td><span class="badge <?= (int) $type['is_active'] === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= (int) $type['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><form method="post" action="<?= site_url('admin/accounts/tax-masters/tax-types/' . (int) $type['id'] . '/status') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= (int) $type['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </section>

    <section class="tax-master-panel">
        <div class="tax-master-panel-header"><h5 class="mb-1">GST Masters</h5><small class="text-muted">Each master combines one or more named tax components and percentages.</small></div>
        <div class="tax-master-panel-body">
            <form method="post" action="<?= site_url('admin/accounts/tax-masters/gst') ?>" class="border rounded-3 p-3 mb-4">
                <?= csrf_field() ?>
                <div class="row g-2 mb-3"><div class="col-md-8"><label class="form-label">Master name</label><input type="text" name="name" class="form-control" maxlength="120" placeholder="Example: Local GST 5%" required></div><div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="allow_zero_tax" value="1" id="zero-tax"><label for="zero-tax" class="form-check-label">Zero-tax master</label></div></div></div>
                <label class="form-label">Components</label>
                <div id="tax-component-list"><div class="tax-component-row"><select name="tax_type_id[]" class="form-select"><option value="">Select tax type</option><?php foreach (($taxTypes ?? []) as $type): ?><?php if ((int) $type['is_active'] === 1): ?><option value="<?= (int) $type['id'] ?>"><?= esc((string) $type['name']) ?></option><?php endif; ?><?php endforeach; ?></select><input type="number" name="percentage[]" class="form-control" min="0" max="100" step="0.001" placeholder="%"><button type="button" class="btn btn-outline-danger js-remove-tax-row" aria-label="Remove">×</button></div></div>
                <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" id="add-tax-component">Add component</button><button class="btn btn-primary">Save GST Master</button></div>
            </form>

            <div class="table-responsive"><table class="table datatable table-hover align-middle mb-0" data-dt-page-length="10"><thead><tr><th>Master</th><th>Components</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
                <?php foreach (($gstMasters ?? []) as $master): ?><tr><td class="fw-semibold"><?= esc((string) $master['name']) ?></td><td><?php if (($master['components'] ?? []) === []): ?><span class="text-muted">No tax</span><?php endif; ?><?php foreach (($master['components'] ?? []) as $component): ?><span class="tax-chip"><?= esc((string) $component['name']) ?> <?= number_format((float) $component['percentage'], 3) ?>%</span><?php endforeach; ?></td><td><?= number_format((float) $master['total_percentage'], 3) ?>%</td><td><span class="badge <?= (int) $master['is_active'] === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= (int) $master['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><form method="post" action="<?= site_url('admin/accounts/tax-masters/gst/' . (int) $master['id'] . '/status') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= (int) $master['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </section>
</div>
<template id="tax-component-template"><div class="tax-component-row"><select name="tax_type_id[]" class="form-select"><option value="">Select tax type</option><?php foreach (($taxTypes ?? []) as $type): ?><?php if ((int) $type['is_active'] === 1): ?><option value="<?= (int) $type['id'] ?>"><?= esc((string) $type['name']) ?></option><?php endif; ?><?php endforeach; ?></select><input type="number" name="percentage[]" class="form-control" min="0" max="100" step="0.001" placeholder="%"><button type="button" class="btn btn-outline-danger js-remove-tax-row" aria-label="Remove">×</button></div></template>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const list = document.getElementById('tax-component-list');
    const template = document.getElementById('tax-component-template');
    document.getElementById('add-tax-component')?.addEventListener('click', function () { list?.appendChild(template.content.cloneNode(true)); });
    document.addEventListener('click', function (event) { const button = event.target instanceof Element ? event.target.closest('.js-remove-tax-row') : null; if (!button) return; const rows = list?.querySelectorAll('.tax-component-row') || []; if (rows.length > 1) button.closest('.tax-component-row')?.remove(); });
})();
</script>
<?= $this->endSection() ?>
