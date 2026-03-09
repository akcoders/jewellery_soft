<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldProductNames = (array) old('product_name');
    $oldStoneTypes = (array) old('stone_type');
    $oldQty = (array) old('qty');
    $oldRates = (array) old('rate');
    $oldReasons = (array) old('reason');

    $max = max(count($oldItemIds), count($oldProductNames), count($oldStoneTypes), count($oldQty), count($oldRates), count($oldReasons));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'product_name' => (string) ($oldProductNames[$i] ?? ''),
            'stone_type' => (string) ($oldStoneTypes[$i] ?? ''),
            'qty' => (string) ($oldQty[$i] ?? ''),
            'rate' => (string) ($oldRates[$i] ?? ''),
            'reason' => (string) ($oldReasons[$i] ?? ''),
        ];
    }
} elseif (($lines ?? []) !== []) {
    foreach ($lines as $line) {
        $rows[] = [
            'item_id' => (string) ($line['item_id'] ?? ''),
            'product_name' => (string) ($line['product_name'] ?? ''),
            'stone_type' => (string) ($line['stone_type'] ?? ''),
            'qty' => (string) ($line['qty'] ?? ''),
            'rate' => (string) ($line['rate'] ?? ''),
            'reason' => (string) ($line['reason'] ?? ''),
        ];
    }
}

if ($rows === []) {
    $rows[] = [
        'item_id' => '',
        'product_name' => '',
        'stone_type' => '',
        'qty' => '',
        'rate' => '',
        'reason' => '',
    ];
}

$adjustmentDate = old('adjustment_date', (string) ($adjustment['adjustment_date'] ?? date('Y-m-d')));
$adjustmentType = old('adjustment_type', (string) ($adjustment['adjustment_type'] ?? 'add'));
$notes = old('notes', (string) ($adjustment['notes'] ?? ''));
$locationId = old('location_id', (string) ($adjustment['location_id'] ?? ''));
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Adjustment Date <span class="text-danger">*</span></label>
                <input type="date" name="adjustment_date" class="form-control" required value="<?= esc((string) $adjustmentDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Adjustment Type <span class="text-danger">*</span></label>
                <select name="adjustment_type" class="form-select" required>
                    <option value="add" <?= $adjustmentType === 'add' ? 'selected' : '' ?>>Add</option>
                    <option value="subtract" <?= $adjustmentType === 'subtract' ? 'selected' : '' ?>>Subtract</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Location <span class="text-danger">*</span></label>
                <select name="location_id" class="form-select" required>
                    <option value="">Select location</option>
                    <?php foreach (($locations ?? []) as $loc): ?>
                        <option value="<?= (int) $loc['id'] ?>" <?= (string) $locationId === (string) $loc['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Adjustment Lines</h6>
        <button type="button" class="btn btn-sm btn-primary" id="add-line"><i class="fe fe-plus"></i> Add Line</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="line-table">
                <thead>
                    <tr>
                        <th style="min-width:240px;">Existing Item</th>
                        <th style="min-width:180px;">Product Name</th>
                        <th style="min-width:150px;">Stone Type</th>
                        <th style="min-width:120px;">Quantity</th>
                        <th style="min-width:120px;">Rate</th>
                        <th style="min-width:140px;">Line Value</th>
                        <th style="min-width:220px;">Reason</th>
                        <th style="min-width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="line-body">
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <select name="item_id[]" class="form-select existing-item">
                                    <option value="">Select existing (optional)</option>
                                    <?php foreach (($items ?? []) as $item): ?>
                                        <?php $label = trim((string) $item['product_name'] . (($item['stone_type'] ?? '') !== '' ? (' / ' . $item['stone_type']) : '')); ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            data-product_name="<?= esc((string) $item['product_name']) ?>"
                                            data-stone_type="<?= esc((string) ($item['stone_type'] ?? '')) ?>"
                                            data-default_rate="<?= esc(number_format((float) ($item['default_rate'] ?? 0), 2, '.', '')) ?>"
                                            <?= (string) $row['item_id'] === (string) $item['id'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="product_name[]" class="form-control line-product-name" value="<?= esc((string) $row['product_name']) ?>"></td>
                            <td><input type="text" name="stone_type[]" class="form-control line-stone-type" value="<?= esc((string) $row['stone_type']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="qty[]" class="form-control line-qty" value="<?= esc((string) $row['qty']) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="rate[]" class="form-control line-rate" value="<?= esc((string) $row['rate']) ?>"></td>
                            <td><input type="text" class="form-control line-value-display" readonly></td>
                            <td><input type="text" name="reason[]" class="form-control line-reason" value="<?= esc((string) $row['reason']) ?>"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Adjustment</button>
</div>

<template id="line-template">
    <tr>
        <td>
            <select name="item_id[]" class="form-select existing-item">
                <option value="">Select existing (optional)</option>
                <?php foreach (($items ?? []) as $item): ?>
                    <?php $label = trim((string) $item['product_name'] . (($item['stone_type'] ?? '') !== '' ? (' / ' . $item['stone_type']) : '')); ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-product_name="<?= esc((string) $item['product_name']) ?>"
                        data-stone_type="<?= esc((string) ($item['stone_type'] ?? '')) ?>"
                        data-default_rate="<?= esc(number_format((float) ($item['default_rate'] ?? 0), 2, '.', '')) ?>"
                    >
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" name="product_name[]" class="form-control line-product-name"></td>
        <td><input type="text" name="stone_type[]" class="form-control line-stone-type"></td>
        <td><input type="number" step="0.001" min="0" name="qty[]" class="form-control line-qty"></td>
        <td><input type="number" step="0.01" min="0" name="rate[]" class="form-control line-rate"></td>
        <td><input type="text" class="form-control line-value-display" readonly></td>
        <td><input type="text" name="reason[]" class="form-control line-reason"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button>
        </td>
    </tr>
</template>

<script>
    (function() {
        const body = document.getElementById('line-body');
        const addBtn = document.getElementById('add-line');
        const tpl = document.getElementById('line-template');

        if (!body || !addBtn || !tpl) {
            return;
        }

        function recalcRow(row) {
            const qty = parseFloat((row.querySelector('.line-qty') || {}).value || '0') || 0;
            const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
            const output = row.querySelector('.line-value-display');
            if (output) {
                output.value = rate > 0 ? (qty * rate).toFixed(2) : '';
            }
        }

        function bindRow(row) {
            const itemSelect = row.querySelector('.existing-item');
            if (itemSelect) {
                itemSelect.addEventListener('change', function() {
                    const selected = itemSelect.options[itemSelect.selectedIndex];
                    if (!selected || !selected.value) {
                        return;
                    }
                    const productInput = row.querySelector('.line-product-name');
                    const typeInput = row.querySelector('.line-stone-type');
                    const rateInput = row.querySelector('.line-rate');
                    if (productInput) productInput.value = selected.getAttribute('data-product_name') || '';
                    if (typeInput) typeInput.value = selected.getAttribute('data-stone_type') || '';
                    if (rateInput && ((rateInput.value || '').trim() === '')) {
                        rateInput.value = selected.getAttribute('data-default_rate') || '';
                    }
                    recalcRow(row);
                });
            }

            ['.line-qty', '.line-rate'].forEach(function(selector) {
                const el = row.querySelector(selector);
                if (el) {
                    el.addEventListener('input', function() {
                        recalcRow(row);
                    });
                }
            });

            const removeBtn = row.querySelector('.remove-line');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    const rowCount = body.querySelectorAll('tr').length;
                    if (rowCount <= 1) {
                        row.querySelectorAll('input').forEach(function(input) {
                            input.value = '';
                        });
                        const select = row.querySelector('select');
                        if (select) {
                            select.value = '';
                        }
                        recalcRow(row);
                        return;
                    }
                    row.remove();
                });
            }

            recalcRow(row);
        }

        addBtn.addEventListener('click', function() {
            const fragment = tpl.content.cloneNode(true);
            const row = fragment.querySelector('tr');
            if (row) {
                bindRow(row);
            }
            body.appendChild(fragment);
        });

        body.querySelectorAll('tr').forEach(function(row) {
            bindRow(row);
        });
    })();
</script>
