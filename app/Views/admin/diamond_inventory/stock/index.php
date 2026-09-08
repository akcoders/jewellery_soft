<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$ledgerCts = (float) ($stats['ledger_cts'] ?? 0);
$tracedCts = (float) ($stats['traced_cts'] ?? 0);
$untracedCts = (float) ($stats['untraced_cts'] ?? 0);
$overTracedCts = (float) ($stats['over_traced_cts'] ?? 0);
$coverage = $ledgerCts > 0 ? min(100, ($tracedCts / $ledgerCts) * 100) : ($tracedCts > 0 ? 100 : 0);
$defaultShapeId = 0;
if (is_array($product ?? null)) {
    $itemShape = strtoupper(trim((string) ($product['shape'] ?? '')));
    foreach (($shapes ?? []) as $shape) {
        if (strtoupper((string) ($shape['code'] ?? '')) === $itemShape || strtoupper((string) ($shape['name'] ?? '')) === $itemShape) {
            $defaultShapeId = (int) $shape['id'];
            break;
        }
    }
}
if ($defaultShapeId <= 0 && (($shapes ?? []) !== [])) {
    foreach ($shapes as $shape) {
        if (strtoupper((string) ($shape['code'] ?? '')) === 'ROUND') {
            $defaultShapeId = (int) $shape['id'];
            break;
        }
    }
}
?>
<style>
.chalni-page{--ink:#172033;--muted:#667085;--line:#e5eaf1;--blue:#3157d5;--violet:#6941c6;--green:#079455;--amber:#b54708}
.chalni-hero{align-items:center;background:linear-gradient(120deg,#fff 0%,#f6f8ff 65%,#fff8e8 100%);border:1px solid var(--line);border-left:4px solid #be1622;border-radius:18px;display:flex;gap:22px;justify-content:space-between;margin-bottom:18px;padding:20px 22px}
.chalni-eyebrow{color:#a56b00;font-size:.72rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.chalni-hero h4{color:#281044;font-size:1.45rem;font-weight:800;margin:4px 0}.chalni-hero p{color:var(--muted);margin:0}
.product-picker{min-width:360px}.product-picker label{color:#475467;font-size:.75rem;font-weight:700;margin-bottom:6px}
.metric-grid{display:grid;gap:14px;grid-template-columns:repeat(5,minmax(0,1fr));margin-bottom:18px}.metric{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 17px;position:relative;overflow:hidden}.metric:after{background:var(--metric,#3157d5);border-radius:0 0 0 18px;content:"";height:5px;position:absolute;right:0;top:0;width:54px}.metric-label{color:#667085;font-size:.72rem;font-weight:700;text-transform:uppercase}.metric-value{color:var(--ink);display:block;font-size:1.35rem;font-weight:800;line-height:1.25;margin-top:7px}.metric-note{color:#98a2b3;font-size:.72rem;margin-top:3px}
.stock-workspace{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden}.stock-toolbar{align-items:center;border-bottom:1px solid var(--line);display:flex;gap:16px;justify-content:space-between;padding:17px 20px}.stock-toolbar h5{color:var(--ink);font-size:1rem;font-weight:800;margin:0}.stock-toolbar p{color:var(--muted);font-size:.78rem;margin:4px 0 0}.coverage{align-items:center;display:flex;gap:10px}.coverage-track{background:#eef2f7;border-radius:20px;height:8px;overflow:hidden;width:130px}.coverage-track span{background:linear-gradient(90deg,#3157d5,#7f56d9);display:block;height:100%}.coverage strong{color:#344054;font-size:.78rem;white-space:nowrap}
.stock-table-wrap{overflow-x:auto}.stock-table{margin:0;min-width:960px}.stock-table thead th{background:#f8fafc;border-bottom:1px solid #dfe5ee;color:#475467;font-size:.7rem;letter-spacing:.05em;padding:13px 15px;text-transform:uppercase;white-space:nowrap}.stock-table td{border-color:#edf0f4;color:#344054;padding:13px 15px;vertical-align:middle}.chalni-code{background:#f2f4ff;border:1px solid #dfe3ff;border-radius:8px;color:#3538cd;display:inline-flex;font-weight:800;letter-spacing:.02em;padding:5px 9px}.stock-number{font-variant-numeric:tabular-nums;font-weight:750}.source-pill{background:#ecfdf3;border-radius:20px;color:#027a48;display:inline-flex;font-size:.7rem;font-weight:700;padding:5px 9px}.source-pill.manual{background:#fff7e8;color:#b54708}.empty-stock{color:#98a2b3;padding:46px 20px!important;text-align:center}.empty-stock i{display:block;font-size:2rem;margin-bottom:10px}
.reconcile-alert{align-items:flex-start;background:#fff8e7;border:1px solid #f8d895;border-radius:14px;color:#7a4d08;display:flex;gap:11px;margin-bottom:18px;padding:13px 15px}.reconcile-alert.danger{background:#fff1f3;border-color:#fecdd6;color:#9f1239}.reconcile-alert i{font-size:1.1rem;margin-top:2px}.reconcile-alert strong{display:block}.reconcile-alert small{display:block;margin-top:2px}
.all-products{background:#fff;border:1px solid var(--line);border-radius:18px;margin-top:18px;overflow:hidden}.all-products-title{border-bottom:1px solid var(--line);padding:16px 20px}.all-products-title h5{font-size:1rem;font-weight:800;margin:0}.stock-modal-note{background:#f8fafc;border:1px dashed #d0d5dd;border-radius:10px;color:#667085;font-size:.76rem;padding:10px 12px}.required{color:#d92d20}
@media(max-width:1199.98px){.metric-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:767.98px){.chalni-hero{align-items:stretch;flex-direction:column;padding:17px}.product-picker{min-width:0;width:100%}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.metric{padding:13px}.metric-value{font-size:1.1rem}.stock-toolbar{align-items:flex-start;flex-direction:column}.coverage{width:100%}.coverage-track{flex:1}.stock-toolbar .btn{width:100%}}
@media(max-width:420px){.metric-grid{grid-template-columns:1fr}}
</style>

<div class="chalni-page">
    <section class="chalni-hero">
        <div>
            <div class="chalni-eyebrow">Diamond inventory control</div>
            <h4>Chalni-wise Stock</h4>
            <p>Select a product to see its complete shape, size and chalni/category split.</p>
        </div>
        <form method="get" action="<?= site_url('admin/diamond-inventory/stock') ?>" class="product-picker" id="product-filter">
            <label for="stock-product">DIAMOND PRODUCT</label>
            <select name="item_id" id="stock-product" class="form-select js-searchable-select" data-placeholder="Search diamond product">
                <?php foreach (($products ?? []) as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) $selectedItemId === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= esc((string) $item['product_label']) ?> · <?= number_format((float) ($item['carat_balance'] ?? 0), 3) ?> cts
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <?php if (! empty($migrationRequired)): ?>
        <div class="reconcile-alert danger"><i class="fe fe-alert-triangle"></i><div><strong>Database update required</strong><small>Run migration 2026-09-08-000087 before managing chalni stock.</small></div></div>
    <?php elseif ($overTracedCts > .0005): ?>
        <div class="reconcile-alert danger"><i class="fe fe-alert-circle"></i><div><strong>Physical chalni count is <?= number_format($overTracedCts, 3) ?> cts above the product ledger.</strong><small>The source count is preserved, not silently overwritten. Reconcile it through a diamond stock adjustment after verification.</small></div></div>
    <?php elseif ($untracedCts > .0005): ?>
        <div class="reconcile-alert"><i class="fe fe-info"></i><div><strong><?= number_format($untracedCts, 3) ?> cts is not classified by chalni yet.</strong><small>Use Add Chalni Stock to classify the physical balance. It will not increase the product ledger twice.</small></div></div>
    <?php endif; ?>

    <section class="metric-grid">
        <article class="metric" style="--metric:#3157d5"><span class="metric-label">Product Ledger</span><strong class="metric-value"><?= number_format($ledgerCts, 3) ?> cts</strong><div class="metric-note"><?= number_format((float) ($stats['ledger_pcs'] ?? 0), 0) ?> pcs in inventory ledger</div></article>
        <article class="metric" style="--metric:#6941c6"><span class="metric-label">Chalni Traced</span><strong class="metric-value"><?= number_format($tracedCts, 3) ?> cts</strong><div class="metric-note"><?= number_format((float) ($stats['traced_pcs'] ?? 0), 0) ?> pcs classified</div></article>
        <article class="metric" style="--metric:#f79009"><span class="metric-label">Untraced</span><strong class="metric-value"><?= number_format($untracedCts, 3) ?> cts</strong><div class="metric-note"><?= $overTracedCts > .0005 ? 'Variance shown separately' : number_format(100 - $coverage, 1) . '% awaiting classification' ?></div></article>
        <article class="metric" style="--metric:#079455"><span class="metric-label">Prepared Bags</span><strong class="metric-value"><?= number_format((float) ($stats['bagged_cts'] ?? 0), 3) ?> cts</strong><div class="metric-note"><?= number_format((float) ($stats['bagged_pcs'] ?? 0), 0) ?> pcs ready in bags</div></article>
        <article class="metric" style="--metric:#be1622"><span class="metric-label"><?= $overTracedCts > .0005 ? 'Over-traced' : 'Categories' ?></span><strong class="metric-value"><?= $overTracedCts > .0005 ? number_format($overTracedCts, 3) . ' cts' : (int) ($stats['category_count'] ?? 0) ?></strong><div class="metric-note"><?= $overTracedCts > .0005 ? 'Needs physical reconciliation' : 'Active chalni stock buckets' ?></div></article>
    </section>

    <section class="stock-workspace">
        <div class="stock-toolbar">
            <div>
                <h5><?= esc((string) ($product['product_label'] ?? 'Select a product')) ?></h5>
                <p>Physical size/category breakdown. Issue and return movements from traced bags update these balances.</p>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="coverage"><div class="coverage-track"><span style="width:<?= number_format($coverage, 2, '.', '') ?>%"></span></div><strong><?= number_format($coverage, 1) ?>% traced</strong></div>
                <?php if (empty($migrationRequired) && ($product ?? null)): ?><button type="button" class="btn btn-primary" id="new-chalni" data-bs-toggle="modal" data-bs-target="#chalni-stock-modal"><i class="fe fe-plus"></i> Add Chalni Stock</button><?php endif; ?>
            </div>
        </div>
        <div class="stock-table-wrap">
            <table class="table table-hover stock-table datatable">
                <thead><tr><th>Chalni / Category</th><th>Shape</th><th>Mapped Size</th><th>Dimensions</th><th class="text-end">PCS</th><th class="text-end">Carats</th><th class="text-end">% of traced</th><th>Source</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php if (($buckets ?? []) === []): ?><tr><td colspan="9" class="empty-stock"><i class="fe fe-layers"></i>No chalni-wise stock has been classified for this product.</td></tr><?php endif; ?>
                <?php foreach (($buckets ?? []) as $bucket): ?>
                    <?php $share = $tracedCts > 0 ? ((float) $bucket['carat_balance'] / $tracedCts) * 100 : 0; ?>
                    <tr>
                        <td><span class="chalni-code"><?= esc((string) $bucket['category_label']) ?></span></td>
                        <td><strong><?= esc((string) ($bucket['shape_name'] ?? '-')) ?></strong></td>
                        <td><?= esc((string) (($bucket['size_label'] ?? '') ?: 'Custom category')) ?></td>
                        <td><?= esc((string) ($bucket['dimension_label'] ?? '-')) ?></td>
                        <td class="text-end stock-number"><?= number_format((float) ($bucket['pcs_balance'] ?? 0), 0) ?></td>
                        <td class="text-end stock-number"><?= number_format((float) ($bucket['carat_balance'] ?? 0), 3) ?></td>
                        <td class="text-end"><span class="badge bg-light text-dark"><?= number_format($share, 1) ?>%</span></td>
                        <td><span class="source-pill <?= strtoupper((string) ($bucket['source_type'] ?? '')) === 'MANUAL' ? 'manual' : '' ?>"><?= esc(ucwords(strtolower(str_replace('_', ' ', (string) ($bucket['source_type'] ?? 'Manual'))))) ?></span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary edit-chalni" data-bs-toggle="modal" data-bs-target="#chalni-stock-modal" data-id="<?= (int) $bucket['id'] ?>" data-shape="<?= (int) $bucket['shape_id'] ?>" data-size="<?= (int) ($bucket['size_id'] ?? 0) ?>" data-category="<?= esc((string) $bucket['category_label'], 'attr') ?>" data-pcs="<?= esc((string) $bucket['pcs_balance'], 'attr') ?>" data-cts="<?= esc((string) $bucket['carat_balance'], 'attr') ?>" data-notes="<?= esc((string) ($bucket['notes'] ?? ''), 'attr') ?>"><i class="fe fe-edit-2"></i> Edit</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (($buckets ?? []) !== []): ?><tfoot><tr><th colspan="4">Chalni Total</th><th class="text-end"><?= number_format((float) ($stats['traced_pcs'] ?? 0), 0) ?></th><th class="text-end"><?= number_format($tracedCts, 3) ?></th><th class="text-end">100%</th><th colspan="2"></th></tr></tfoot><?php endif; ?>
            </table>
        </div>
    </section>

    <section class="all-products">
        <div class="all-products-title"><h5>All Diamond Product Reconciliation</h5></div>
        <div class="stock-table-wrap">
            <table class="table table-hover stock-table datatable">
                <thead><tr><th>Product</th><th class="text-end">Ledger CTS</th><th class="text-end">Traced CTS</th><th class="text-end">Untraced CTS</th><th class="text-end">Variance</th><th>Status</th></tr></thead>
                <tbody><?php foreach (($products ?? []) as $item): ?>
                    <?php $variance = (float) ($item['traced_cts'] ?? 0) - (float) ($item['carat_balance'] ?? 0); ?>
                    <tr class="<?= (int) $selectedItemId === (int) $item['id'] ? 'table-active' : '' ?>">
                        <td><a href="<?= site_url('admin/diamond-inventory/stock?item_id=' . (int) $item['id']) ?>"><strong><?= esc((string) $item['product_label']) ?></strong></a></td>
                        <td class="text-end stock-number"><?= number_format((float) ($item['carat_balance'] ?? 0), 3) ?></td>
                        <td class="text-end stock-number"><?= number_format((float) ($item['traced_cts'] ?? 0), 3) ?></td>
                        <td class="text-end stock-number"><?= number_format((float) ($item['untraced_cts'] ?? 0), 3) ?></td>
                        <td class="text-end stock-number <?= abs($variance) > .0005 ? 'text-danger' : 'text-success' ?>"><?= ($variance > 0 ? '+' : '') . number_format($variance, 3) ?></td>
                        <td><span class="badge <?= abs($variance) <= .0005 ? 'bg-success' : ((float) ($item['traced_cts'] ?? 0) <= .0005 ? 'bg-secondary' : 'bg-warning text-dark') ?>"><?= abs($variance) <= .0005 ? 'Reconciled' : ((float) ($item['traced_cts'] ?? 0) <= .0005 ? 'Untraced' : 'Review') ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
</div>

<?php if (empty($migrationRequired) && ($product ?? null)): ?>
<div class="modal fade" id="chalni-stock-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <form method="post" action="<?= site_url('admin/diamond-inventory/stock/chalni') ?>" id="chalni-stock-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="chalni-id" value="0"><input type="hidden" name="item_id" value="<?= (int) $selectedItemId ?>">
            <div class="modal-header"><div><h5 class="modal-title" id="chalni-modal-title">Add Chalni Stock</h5><small class="text-muted"><?= esc((string) ($product['product_label'] ?? '')) ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="stock-modal-note mb-3"><i class="fe fe-info me-1"></i> This classifies the existing product balance by physical chalni. It does not create a second product-stock transaction.</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Shape <span class="required">*</span></label><select name="shape_id" id="chalni-shape" class="form-select" required><option value="">Select shape</option><?php foreach (($shapes ?? []) as $shape): ?><option value="<?= (int) $shape['id'] ?>" <?= (int) $shape['id'] === $defaultShapeId ? 'selected' : '' ?>><?= esc((string) $shape['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Exact Master Size <small class="text-muted">(optional)</small></label><select name="size_id" id="chalni-size" class="form-select"><option value="">Custom/grouped category</option><?php foreach (($sizes ?? []) as $size): ?><option value="<?= (int) $size['id'] ?>" data-shape="<?= (int) $size['shape_id'] ?>" data-chalni="<?= esc((string) ($size['chalni_label'] ?? ''), 'attr') ?>"><?= esc((string) $size['size_label']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Chalni / Category <span class="required">*</span></label><input type="text" name="category_label" id="chalni-category" class="form-control" maxlength="80" placeholder="e.g. 1-2 or 000-00" required></div>
                    <div class="col-md-3"><label class="form-label">PCS Balance</label><input type="number" name="pcs_balance" id="chalni-pcs" class="form-control" min="0" step="1" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Carat Balance <span class="required">*</span></label><input type="number" name="carat_balance" id="chalni-cts" class="form-control" min="0" step="0.001" required></div>
                    <div class="col-12"><label class="form-label">Recount / Classification Note</label><input type="text" name="notes" id="chalni-notes" class="form-control" maxlength="500" placeholder="Optional stock count reference"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Save Chalni Stock</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const product = document.getElementById('stock-product');
    if (product) product.addEventListener('change', function () { document.getElementById('product-filter').submit(); });

    const modal = document.getElementById('chalni-stock-modal');
    if (!modal) return;
    const id = document.getElementById('chalni-id'), shape = document.getElementById('chalni-shape'), size = document.getElementById('chalni-size');
    const category = document.getElementById('chalni-category'), pcs = document.getElementById('chalni-pcs'), cts = document.getElementById('chalni-cts'), notes = document.getElementById('chalni-notes');
    const defaultShape = '<?= (int) $defaultShapeId ?>';

    function filterSizes(selectedValue) {
        const shapeId = shape.value;
        Array.from(size.options).forEach(function (option, index) {
            if (index === 0) return;
            option.hidden = option.dataset.shape !== shapeId;
            option.disabled = option.hidden;
        });
        if (selectedValue && size.querySelector('option[value="' + CSS.escape(String(selectedValue)) + '"]:not([disabled])')) size.value = String(selectedValue);
        else if (size.selectedOptions[0] && size.selectedOptions[0].disabled) size.value = '';
    }
    shape.addEventListener('change', function () { filterSizes(''); });
    size.addEventListener('change', function () { const option = size.selectedOptions[0]; if (option && option.dataset.chalni) category.value = option.dataset.chalni; });
    filterSizes('');

    const fresh = document.getElementById('new-chalni');
    if (fresh) fresh.addEventListener('click', function () {
        document.getElementById('chalni-modal-title').textContent = 'Add Chalni Stock'; id.value = '0'; shape.value = defaultShape; filterSizes(''); size.value = ''; category.value = ''; pcs.value = '0'; cts.value = ''; notes.value = '';
    });
    document.querySelectorAll('.edit-chalni').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('chalni-modal-title').textContent = 'Update Chalni Stock'; id.value = button.dataset.id; shape.value = button.dataset.shape; filterSizes(button.dataset.size); category.value = button.dataset.category; pcs.value = button.dataset.pcs; cts.value = button.dataset.cts; notes.value = button.dataset.notes;
        });
    });
});
</script>
<?= $this->endSection() ?>
