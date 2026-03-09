<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldProductNames = (array) old('product_name');
    $oldStoneTypes = (array) old('stone_type');
    $oldPcs = (array) old('pcs');
    $oldQty = (array) old('qty');
    $oldRates = (array) old('rate');

    $max = max(count($oldItemIds), count($oldProductNames), count($oldStoneTypes), count($oldPcs), count($oldQty), count($oldRates));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'product_name' => (string) ($oldProductNames[$i] ?? ''),
            'stone_type' => (string) ($oldStoneTypes[$i] ?? ''),
            'pcs' => (string) ($oldPcs[$i] ?? ''),
            'qty' => (string) ($oldQty[$i] ?? ''),
            'rate' => (string) ($oldRates[$i] ?? ''),
        ];
    }
} elseif (($lines ?? []) !== []) {
    foreach ($lines as $line) {
        $rows[] = [
            'item_id' => (string) ($line['item_id'] ?? ''),
            'product_name' => (string) ($line['product_name'] ?? ''),
            'stone_type' => (string) ($line['stone_type'] ?? ''),
            'pcs' => (string) ($line['pcs'] ?? ''),
            'qty' => (string) ($line['qty'] ?? ''),
            'rate' => (string) ($line['rate'] ?? ''),
        ];
    }
}

if ($rows === []) {
    $rows[] = [
        'item_id' => '',
        'product_name' => '',
        'stone_type' => '',
        'pcs' => '',
        'qty' => '',
        'rate' => '',
    ];
}

$issueDate = old('issue_date', (string) ($issue['issue_date'] ?? date('Y-m-d')));
$selectedOrderId = old('order_id', (string) ($issue['order_id'] ?? (string) ($preselectedOrderId ?? '')));
$selectedKarigarId = old('karigar_id', (string) ($issue['karigar_id'] ?? ''));
$selectedLocationId = old('location_id', (string) ($issue['location_id'] ?? ''));
$purpose = old('purpose', (string) ($issue['purpose'] ?? ''));
$notes = old('notes', (string) ($issue['notes'] ?? ''));
$existingAttachment = (string) ($issue['attachment_path'] ?? '');
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_date" class="form-control" required value="<?= esc((string) $issueDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Order (Assigned Only) <span class="text-danger">*</span></label>
                <select name="order_id" id="order_id" class="form-select js-select2" required>
                    <option value="">Select order</option>
                    <?php foreach (($orders ?? []) as $order): ?>
                        <option
                            value="<?= (int) $order['id'] ?>"
                            data-karigar-id="<?= (int) ($order['assigned_karigar_id'] ?? 0) ?>"
                            data-default-purpose="<?= esc((string) ($order['default_purpose'] ?? 'Jobwork')) ?>"
                            data-issued="<?= esc(number_format((float) ($order['issued_qty'] ?? 0), 3, '.', '')) ?>"
                            data-returned="<?= esc(number_format((float) ($order['returned_qty'] ?? 0), 3, '.', '')) ?>"
                            data-pending="<?= esc(number_format((float) ($order['pending_qty'] ?? 0), 3, '.', '')) ?>"
                            <?= (string) $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>
                        >
                            <?= esc((string) $order['order_no']) ?> - <?= esc((string) ($order['karigar_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Karigar <span class="text-danger">*</span></label>
                <select name="karigar_id" id="karigar_id" class="form-select js-select2" required>
                    <option value="">Select karigar</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?>
                        <option value="<?= (int) $karigar['id'] ?>" <?= (string) $selectedKarigarId === (string) $karigar['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $karigar['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="issue_to" id="issue_to" value="">
            </div>
            <div class="col-md-2">
                <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                <select name="location_id" class="form-select" required>
                    <option value="">Select warehouse</option>
                    <?php foreach (($locations ?? []) as $location): ?>
                        <option value="<?= (int) $location['id'] ?>" <?= (string) $selectedLocationId === (string) $location['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $location['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Purpose <span class="text-danger">*</span></label>
                <input type="text" name="purpose" id="purpose" class="form-control" required value="<?= esc((string) $purpose) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Order Qty Monitor</label>
                <div id="budget_text" class="form-control bg-light" style="height:auto;">
                    Select order to view issued, returned and pending quantity.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Attachment <span class="text-danger">*</span></label>
                <input type="file" name="attachment" class="form-control" <?= $existingAttachment === '' ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.webp,.pdf">
                <small class="text-muted">Attachment is mandatory for issuance voucher.</small>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <?php if ($existingAttachment !== ''): ?>
                    <a href="<?= base_url($existingAttachment) ?>" target="_blank" class="btn btn-outline-primary">Open Current Attachment</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Issue Lines</h6>
        <button type="button" class="btn btn-sm btn-primary" id="add-issue-line"><i class="fe fe-plus"></i> Add Line</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="issue-lines-table">
                <thead>
                    <tr>
                        <th style="min-width:240px;">Existing Item</th>
                        <th style="min-width:180px;">Product Name</th>
                        <th style="min-width:150px;">Stone Type</th>
                        <th style="min-width:110px;">PCS</th>
                        <th style="min-width:120px;">Quantity</th>
                        <th style="min-width:120px;">Rate</th>
                        <th style="min-width:140px;">Line Value</th>
                        <th style="min-width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="issue-lines-body">
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
                            <td><input type="number" step="0.001" min="0" name="pcs[]" class="form-control line-pcs" value="<?= esc((string) $row['pcs']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="qty[]" class="form-control line-qty" value="<?= esc((string) $row['qty']) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="rate[]" class="form-control line-rate" value="<?= esc((string) $row['rate']) ?>"></td>
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

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Issue</button>
</div>

<template id="issue-line-template">
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
        <td><input type="number" step="0.001" min="0" name="pcs[]" class="form-control line-pcs"></td>
        <td><input type="number" step="0.001" min="0" name="qty[]" class="form-control line-qty"></td>
        <td><input type="number" step="0.01" min="0" name="rate[]" class="form-control line-rate"></td>
        <td><input type="text" class="form-control line-value-display" readonly></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button>
        </td>
    </tr>
</template>

<script>
    (function() {
        const body = document.getElementById('issue-lines-body');
        const addBtn = document.getElementById('add-issue-line');
        const tpl = document.getElementById('issue-line-template');
        const orderSelect = document.getElementById('order_id');
        const karigarSelect = document.getElementById('karigar_id');
        const purposeInput = document.getElementById('purpose');
        const budgetText = document.getElementById('budget_text');
        const issueTo = document.getElementById('issue_to');

        if (!body || !addBtn || !tpl) {
            return;
        }

        function refreshIssueTo() {
            if (!karigarSelect || !issueTo) return;
            const selected = karigarSelect.options[karigarSelect.selectedIndex];
            issueTo.value = selected && selected.value ? selected.text : '';
        }

        function updateMonitorPanel() {
            if (!orderSelect || !budgetText) return;
            const selected = orderSelect.options[orderSelect.selectedIndex];
            if (!selected || !selected.value) {
                budgetText.textContent = 'Select order to view issued, returned and pending quantity.';
                return;
            }
            const issued = parseFloat(selected.getAttribute('data-issued') || '0') || 0;
            const returned = parseFloat(selected.getAttribute('data-returned') || '0') || 0;
            const pending = parseFloat(selected.getAttribute('data-pending') || '0') || 0;
            budgetText.textContent = 'Issued: ' + issued.toFixed(3) + ' | Returned: ' + returned.toFixed(3) + ' | Pending: ' + pending.toFixed(3);
        }

        function applyOrderDefaults() {
            if (!orderSelect) return;
            const selected = orderSelect.options[orderSelect.selectedIndex];
            if (!selected || !selected.value) return;

            const kId = selected.getAttribute('data-karigar-id') || '';
            const defaultPurpose = selected.getAttribute('data-default-purpose') || 'Jobwork';

            if (karigarSelect && kId) {
                karigarSelect.value = kId;
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery(karigarSelect).trigger('change.select2');
                }
            }
            if (purposeInput && (!purposeInput.value || purposeInput.value.trim() === '')) {
                purposeInput.value = defaultPurpose;
            }
            refreshIssueTo();
            updateMonitorPanel();
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
                        if (select) select.value = '';
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
            if (row) bindRow(row);
            body.appendChild(fragment);
        });

        body.querySelectorAll('tr').forEach(function(row) {
            bindRow(row);
        });

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery('.js-select2').select2({ width: '100%' });
        }

        if (orderSelect) {
            orderSelect.addEventListener('change', applyOrderDefaults);
        }
        if (karigarSelect) {
            karigarSelect.addEventListener('change', refreshIssueTo);
        }

        applyOrderDefaults();
        updateMonitorPanel();
        refreshIssueTo();
    })();
</script>
