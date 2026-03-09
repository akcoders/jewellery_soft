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
        count($oldRates)
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
    ];
}

$purchaseDate = old('purchase_date', (string) ($purchase['purchase_date'] ?? date('Y-m-d')));
$vendorId = old('vendor_id', (string) ($purchase['vendor_id'] ?? ''));
$invoiceNo = old('invoice_no', (string) ($purchase['invoice_no'] ?? ''));
$dueDate = old('due_date', (string) ($purchase['due_date'] ?? ''));
$taxPercentage = old('tax_percentage', (string) ($purchase['tax_percentage'] ?? '0'));
$invoiceTotal = old('invoice_total', (string) ($purchase['invoice_total'] ?? '0.00'));
$notes = old('notes', (string) ($purchase['notes'] ?? ''));
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                <input type="date" name="purchase_date" class="form-control" required value="<?= esc((string) $purchaseDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vendor_id" class="form-select select2" required>
                    <option value="">Select vendor</option>
                    <?php foreach (($vendors ?? []) as $vendor): ?>
                        <option value="<?= (int) $vendor['id'] ?>" <?= (string) $vendorId === (string) $vendor['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $vendor['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Invoice No</label>
                <input type="text" name="invoice_no" class="form-control" value="<?= esc((string) $invoiceNo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control" value="<?= esc((string) $dueDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tax %</label>
                <input type="number" step="0.001" min="0" max="100" name="tax_percentage" id="tax_percentage" class="form-control" value="<?= esc((string) $taxPercentage) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Subtotal</label>
                <input type="text" id="subtotal_display" class="form-control" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tax Value</label>
                <input type="text" id="tax_value_display" class="form-control" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label">Invoice Total</label>
                <input type="number" step="0.01" min="0" name="invoice_total" id="invoice_total" class="form-control" readonly value="<?= esc((string) $invoiceTotal) ?>">
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Purchase Lines</h6>
        <button type="button" class="btn btn-sm btn-primary" id="add-purchase-line"><i class="fe fe-plus"></i> Add Line</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="purchase-lines-table">
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
                        <th style="min-width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="purchase-lines-body">
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

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Attachments</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Upload Files</label>
                <input type="file" name="attachments[]" class="form-control" multiple>
                <small class="text-muted">Allowed: jpg, png, webp, pdf, doc, docx, xls, xlsx, csv, txt (max 10MB each)</small>
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Existing Files</label>
                <?php if (($attachments ?? []) === []): ?>
                    <div class="text-muted">No attachments uploaded.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach (($attachments ?? []) as $file): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= esc((string) ($file['file_name'] ?? 'File')) ?></span>
                                <a href="<?= base_url((string) ($file['file_path'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Purchase</button>
</div>

<template id="purchase-line-template">
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
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button>
        </td>
    </tr>
</template>

<script>
    (function() {
        const body = document.getElementById('purchase-lines-body');
        const addBtn = document.getElementById('add-purchase-line');
        const tpl = document.getElementById('purchase-line-template');

        if (!body || !addBtn || !tpl) {
            return;
        }

        function recalcRow(row) {
            const carat = parseFloat((row.querySelector('.line-carat') || {}).value || '0') || 0;
            const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
            const output = row.querySelector('.line-value-display');
            if (output) {
                output.value = (carat * rate).toFixed(2);
            }
            recalcTotals();
        }

        function recalcTotals() {
            let subtotal = 0;
            body.querySelectorAll('tr').forEach(function(row) {
                const carat = parseFloat((row.querySelector('.line-carat') || {}).value || '0') || 0;
                const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
                subtotal += (carat * rate);
            });

            const taxInput = document.getElementById('tax_percentage');
            const taxPercent = Math.max(0, Math.min(100, parseFloat((taxInput || {}).value || '0') || 0));
            const taxValue = subtotal * (taxPercent / 100);
            const invoiceTotal = subtotal + taxValue;

            const subtotalEl = document.getElementById('subtotal_display');
            const taxValueEl = document.getElementById('tax_value_display');
            const invoiceTotalEl = document.getElementById('invoice_total');
            if (subtotalEl) subtotalEl.value = subtotal.toFixed(2);
            if (taxValueEl) taxValueEl.value = taxValue.toFixed(2);
            if (invoiceTotalEl) invoiceTotalEl.value = invoiceTotal.toFixed(2);
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
                    recalcTotals();
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

        const taxInput = document.getElementById('tax_percentage');
        if (taxInput) {
            taxInput.addEventListener('input', recalcTotals);
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('#vendor_id').select2({
                width: '100%'
            });
        }

        recalcTotals();
    })();
</script>
