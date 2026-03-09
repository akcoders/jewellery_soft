<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldDiamondTypes = (array) old('diamond_type');
    $oldShapes = (array) old('shape');
    $oldChalniFrom = (array) old('chalni_from');
    $oldChalniTo = (array) old('chalni_to');
    $oldColors = (array) old('color');
    $oldClarities = (array) old('clarity');
    $oldCuts = (array) old('cut');
    $oldPcs = (array) old('pcs');
    $oldCarat = (array) old('carat');
    $oldRates = (array) old('rate_per_carat');
    $oldReasons = (array) old('reason');

    $max = max(
        count($oldItemIds),
        count($oldDiamondTypes),
        count($oldShapes),
        count($oldChalniFrom),
        count($oldChalniTo),
        count($oldColors),
        count($oldClarities),
        count($oldCuts),
        count($oldPcs),
        count($oldCarat),
        count($oldRates),
        count($oldReasons)
    );

    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'diamond_type' => (string) ($oldDiamondTypes[$i] ?? ''),
            'shape' => (string) ($oldShapes[$i] ?? ''),
            'chalni_from' => (string) ($oldChalniFrom[$i] ?? ''),
            'chalni_to' => (string) ($oldChalniTo[$i] ?? ''),
            'color' => (string) ($oldColors[$i] ?? ''),
            'clarity' => (string) ($oldClarities[$i] ?? ''),
            'cut' => (string) ($oldCuts[$i] ?? ''),
            'pcs' => (string) ($oldPcs[$i] ?? ''),
            'carat' => (string) ($oldCarat[$i] ?? ''),
            'rate_per_carat' => (string) ($oldRates[$i] ?? ''),
            'reason' => (string) ($oldReasons[$i] ?? ''),
        ];
    }
} elseif (($lines ?? []) !== []) {
    foreach ($lines as $line) {
        $rows[] = [
            'item_id' => (string) ($line['item_id'] ?? ''),
            'diamond_type' => (string) ($line['diamond_type'] ?? ''),
            'shape' => (string) ($line['shape'] ?? ''),
            'chalni_from' => (string) ($line['chalni_from'] ?? ''),
            'chalni_to' => (string) ($line['chalni_to'] ?? ''),
            'color' => (string) ($line['color'] ?? ''),
            'clarity' => (string) ($line['clarity'] ?? ''),
            'cut' => (string) ($line['cut'] ?? ''),
            'pcs' => (string) ($line['pcs'] ?? ''),
            'carat' => (string) ($line['carat'] ?? ''),
            'rate_per_carat' => (string) ($line['rate_per_carat'] ?? ''),
            'reason' => (string) ($line['reason'] ?? ''),
        ];
    }
}

if ($rows === []) {
    $rows[] = [
        'item_id' => '',
        'diamond_type' => '',
        'shape' => '',
        'chalni_from' => '',
        'chalni_to' => '',
        'color' => '',
        'clarity' => '',
        'cut' => '',
        'pcs' => '0',
        'carat' => '',
        'rate_per_carat' => '',
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
                        <th style="min-width:130px;">Type</th>
                        <th style="min-width:110px;">Shape</th>
                        <th style="min-width:80px;">From</th>
                        <th style="min-width:80px;">To</th>
                        <th style="min-width:90px;">Color</th>
                        <th style="min-width:90px;">Clarity</th>
                        <th style="min-width:90px;">Cut</th>
                        <th style="min-width:80px;">PCS</th>
                        <th style="min-width:90px;">Carat</th>
                        <th style="min-width:110px;">Rate/cts</th>
                        <th style="min-width:120px;">Line Value</th>
                        <th style="min-width:180px;">Reason</th>
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
                                        <?php
                                        $label = (string) $item['diamond_type'];
                                        if (!empty($item['shape'])) {
                                            $label .= ' / ' . $item['shape'];
                                        }
                                        if ($item['chalni_from'] !== null && $item['chalni_to'] !== null) {
                                            $label .= ' / ' . $item['chalni_from'] . '-' . $item['chalni_to'];
                                        }
                                        if (!empty($item['color'])) {
                                            $label .= ' / ' . $item['color'];
                                        }
                                        if (!empty($item['clarity'])) {
                                            $label .= ' / ' . $item['clarity'];
                                        }
                                        ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            data-diamond_type="<?= esc((string) $item['diamond_type']) ?>"
                                            data-shape="<?= esc((string) ($item['shape'] ?? '')) ?>"
                                            data-chalni_from="<?= esc((string) ($item['chalni_from'] ?? '')) ?>"
                                            data-chalni_to="<?= esc((string) ($item['chalni_to'] ?? '')) ?>"
                                            data-color="<?= esc((string) ($item['color'] ?? '')) ?>"
                                            data-clarity="<?= esc((string) ($item['clarity'] ?? '')) ?>"
                                            data-cut="<?= esc((string) ($item['cut'] ?? '')) ?>"
                                            <?= (string) $row['item_id'] === (string) $item['id'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="diamond_type[]" class="form-control line-diamond-type" value="<?= esc((string) $row['diamond_type']) ?>"></td>
                            <td><input type="text" name="shape[]" class="form-control line-shape" value="<?= esc((string) $row['shape']) ?>"></td>
                            <td><input type="text" name="chalni_from[]" class="form-control line-chalni-from" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) $row['chalni_from']) ?>"></td>
                            <td><input type="text" name="chalni_to[]" class="form-control line-chalni-to" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) $row['chalni_to']) ?>"></td>
                            <td><input type="text" name="color[]" class="form-control line-color" value="<?= esc((string) $row['color']) ?>"></td>
                            <td><input type="text" name="clarity[]" class="form-control line-clarity" value="<?= esc((string) $row['clarity']) ?>"></td>
                            <td><input type="text" name="cut[]" class="form-control line-cut" value="<?= esc((string) $row['cut']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="pcs[]" class="form-control line-pcs" value="<?= esc((string) $row['pcs']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="carat[]" class="form-control line-carat" value="<?= esc((string) $row['carat']) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="rate_per_carat[]" class="form-control line-rate" value="<?= esc((string) $row['rate_per_carat']) ?>"></td>
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
                    <?php
                    $label = (string) $item['diamond_type'];
                    if (!empty($item['shape'])) {
                        $label .= ' / ' . $item['shape'];
                    }
                    if ($item['chalni_from'] !== null && $item['chalni_to'] !== null) {
                        $label .= ' / ' . $item['chalni_from'] . '-' . $item['chalni_to'];
                    }
                    if (!empty($item['color'])) {
                        $label .= ' / ' . $item['color'];
                    }
                    if (!empty($item['clarity'])) {
                        $label .= ' / ' . $item['clarity'];
                    }
                    ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-diamond_type="<?= esc((string) $item['diamond_type']) ?>"
                        data-shape="<?= esc((string) ($item['shape'] ?? '')) ?>"
                        data-chalni_from="<?= esc((string) ($item['chalni_from'] ?? '')) ?>"
                        data-chalni_to="<?= esc((string) ($item['chalni_to'] ?? '')) ?>"
                        data-color="<?= esc((string) ($item['color'] ?? '')) ?>"
                        data-clarity="<?= esc((string) ($item['clarity'] ?? '')) ?>"
                        data-cut="<?= esc((string) ($item['cut'] ?? '')) ?>"
                    >
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" name="diamond_type[]" class="form-control line-diamond-type"></td>
        <td><input type="text" name="shape[]" class="form-control line-shape"></td>
        <td><input type="text" name="chalni_from[]" class="form-control line-chalni-from" inputmode="numeric" pattern="[0-9]*"></td>
        <td><input type="text" name="chalni_to[]" class="form-control line-chalni-to" inputmode="numeric" pattern="[0-9]*"></td>
        <td><input type="text" name="color[]" class="form-control line-color"></td>
        <td><input type="text" name="clarity[]" class="form-control line-clarity"></td>
        <td><input type="text" name="cut[]" class="form-control line-cut"></td>
        <td><input type="number" step="0.001" min="0" name="pcs[]" class="form-control line-pcs" value="0"></td>
        <td><input type="number" step="0.001" min="0" name="carat[]" class="form-control line-carat"></td>
        <td><input type="number" step="0.01" min="0" name="rate_per_carat[]" class="form-control line-rate"></td>
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
            const carat = parseFloat((row.querySelector('.line-carat') || {}).value || '0') || 0;
            const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
            const output = row.querySelector('.line-value-display');
            if (output) {
                output.value = rate > 0 ? (carat * rate).toFixed(2) : '';
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
                    const map = {
                        '.line-diamond-type': selected.getAttribute('data-diamond_type') || '',
                        '.line-shape': selected.getAttribute('data-shape') || '',
                        '.line-chalni-from': selected.getAttribute('data-chalni_from') || '',
                        '.line-chalni-to': selected.getAttribute('data-chalni_to') || '',
                        '.line-color': selected.getAttribute('data-color') || '',
                        '.line-clarity': selected.getAttribute('data-clarity') || '',
                        '.line-cut': selected.getAttribute('data-cut') || ''
                    };
                    Object.keys(map).forEach(function(selector) {
                        const input = row.querySelector(selector);
                        if (input) {
                            input.value = map[selector];
                        }
                    });
                });
            }

            ['.line-carat', '.line-rate'].forEach(function(selector) {
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
                            if (input.classList.contains('line-pcs')) {
                                input.value = '0';
                            } else {
                                input.value = '';
                            }
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

