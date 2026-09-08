<?php
$bag = is_array($bag ?? null) ? $bag : [];
$oldItemIds = old('inventory_item_id');
$rows = [];
if (is_array($oldItemIds)) {
    $shapeIds = (array) old('shape_master_id');
    $sizeIds = (array) old('size_master_id');
    $pcsRows = (array) old('pcs');
    $ctsRows = (array) old('weight_cts');
    foreach ($oldItemIds as $i => $itemId) {
        $rows[] = ['inventory_item_id' => $itemId, 'shape_master_id' => $shapeIds[$i] ?? '', 'size_master_id' => $sizeIds[$i] ?? '', 'pcs_total' => $pcsRows[$i] ?? '', 'weight_cts_total' => $ctsRows[$i] ?? ''];
    }
} elseif (($items ?? []) !== []) {
    $rows = $items;
}
if ($rows === []) {
    $rows[] = ['inventory_item_id' => '', 'shape_master_id' => '', 'size_master_id' => '', 'pcs_total' => '', 'weight_cts_total' => ''];
}
$selectedLocation = (string) old('location_id', (string) ($selectedLocationId ?? ''));
?>
<div class="card erp-form-shell mb-3">
    <div class="card-header"><h6 class="mb-0">Bag Header</h6></div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Prepared Date *</label><input type="date" name="prepared_date" class="form-control" required value="<?= esc((string) old('prepared_date', (string) ($bag['prepared_date'] ?? date('Y-m-d')))) ?>"></div>
        <div class="col-md-4"><label class="form-label">Inventory Location *</label><select name="location_id" class="form-select js-select2" required><option value="">Select location</option><?php foreach (($locations ?? []) as $location): ?><option value="<?= (int) $location['id'] ?>" <?= $selectedLocation === (string) $location['id'] ? 'selected' : '' ?>><?= esc((string) $location['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-5"><label class="form-label">Bag Photo</label><input type="file" name="audit_image" class="form-control" accept="image/*"><?php if (! empty($bag['audit_image_path'])): ?><a class="small" target="_blank" href="<?= base_url((string) $bag['audit_image_path']) ?>">Open current photo</a><?php endif; ?></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="500" value="<?= esc((string) old('notes', (string) ($bag['notes'] ?? ''))) ?>" placeholder="Packet seal, sorter or identification remarks"></div>
    </div></div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start"><i class="fe fe-info mt-1"></i><div><strong>Bag is not tied to one order.</strong> Select this bag during issuement and split its PCS/CTS into as many order rows as needed.</div></div>
<div class="card erp-form-shell mb-3">
    <div class="card-header d-flex justify-content-between align-items-center"><div><h6 class="mb-1">Calibrated Bag Rows</h6><small class="text-muted">Shape, size, PCS and CTS are mandatory.</small></div><button type="button" class="btn btn-sm btn-primary" id="add-bag-row"><i class="fe fe-plus me-1"></i>Add Size Row</button></div>
    <div class="table-responsive"><table class="table table-bordered align-middle mb-0" data-dt-skip="true"><thead><tr><th style="min-width:280px">Diamond Inventory Item *</th><th style="min-width:150px">Shape *</th><th style="min-width:180px">Size *</th><th style="min-width:110px">PCS *</th><th style="min-width:120px">CTS *</th><th style="width:65px"></th></tr></thead><tbody id="bag-lines-body">
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><select name="inventory_item_id[]" class="form-select js-bag-item" required><option value="">Select stock item</option><?php foreach (($inventoryItems ?? []) as $item): ?><?php $label = trim((string) $item['diamond_type'] . ' / ' . (string) ($item['shape'] ?? '-') . ' / ' . (string) ($item['color'] ?? '-') . ' / ' . (string) ($item['clarity'] ?? '-')); ?><option value="<?= (int) $item['id'] ?>" <?= (string) ($row['inventory_item_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>><?= esc($label) ?> · <?= number_format((float) $item['carat_balance'], 3) ?> cts stock</option><?php endforeach; ?></select></td>
            <td><select name="shape_master_id[]" class="form-select js-bag-shape" required><option value="">Select shape</option><?php foreach (($shapes ?? []) as $shape): ?><option value="<?= (int) $shape['id'] ?>" <?= (string) ($row['shape_master_id'] ?? '') === (string) $shape['id'] ? 'selected' : '' ?>><?= esc((string) $shape['name']) ?></option><?php endforeach; ?></select></td>
            <td><select name="size_master_id[]" class="form-select js-bag-size" required><option value="">Select size</option><?php foreach (($sizes ?? []) as $size): ?><option value="<?= (int) $size['id'] ?>" data-shape="<?= (int) $size['shape_id'] ?>" <?= (string) ($row['size_master_id'] ?? '') === (string) $size['id'] ? 'selected' : '' ?>><?= esc((string) $size['size_label']) ?></option><?php endforeach; ?></select></td>
            <td><input type="number" name="pcs[]" class="form-control" min="1" step="1" required value="<?= esc((string) ($row['pcs_total'] ?? '')) ?>"></td>
            <td><input type="number" name="weight_cts[]" class="form-control" min="0.001" step="0.001" required value="<?= esc((string) ($row['weight_cts_total'] ?? '')) ?>"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-bag-row"><i class="fe fe-trash-2"></i></button></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <div class="card-footer d-flex justify-content-between flex-wrap gap-2"><a href="<?= site_url('admin/diamond-inventory/shape-sizes') ?>" class="btn btn-outline-secondary"><i class="fe fe-grid me-1"></i>Manage Shape &amp; Size</a><button class="btn btn-primary"><i class="fe fe-save me-1"></i><?= $bag === [] ? 'Prepare Bag' : 'Update Bag' ?></button></div>
</div>

<script>
(function () {
    const body = document.getElementById('bag-lines-body');
    const add = document.getElementById('add-bag-row');
    if (!body || !add) return;
    function filterSizes(row) {
        const shape = row.querySelector('.js-bag-shape');
        const size = row.querySelector('.js-bag-size');
        if (!shape || !size) return;
        const selected = size.value;
        Array.from(size.options).forEach(function (option) {
            if (!option.value) return;
            option.hidden = option.getAttribute('data-shape') !== shape.value;
            option.disabled = option.hidden;
        });
        if (selected && size.options[size.selectedIndex] && size.options[size.selectedIndex].disabled) size.value = '';
    }
    function bind(row) {
        row.querySelector('.js-bag-shape')?.addEventListener('change', function () { filterSizes(row); });
        row.querySelector('.js-remove-bag-row')?.addEventListener('click', function () { if (body.querySelectorAll('tr').length > 1) row.remove(); });
        filterSizes(row);
    }
    body.querySelectorAll('tr').forEach(bind);
    add.addEventListener('click', function () {
        const source = body.querySelector('tr');
        if (!source) return;
        const clone = source.cloneNode(true);
        clone.querySelectorAll('select').forEach(function (select) { select.value = ''; });
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        body.appendChild(clone);
        bind(clone);
    });
})();
</script>
