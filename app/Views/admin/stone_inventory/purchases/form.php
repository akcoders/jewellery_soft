<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldProductNames = (array) old('product_name');
    $oldStoneTypes = (array) old('stone_type');
    $oldQty = (array) old('qty');
    $oldRates = (array) old('rate');

    $max = max(count($oldItemIds), count($oldProductNames), count($oldStoneTypes), count($oldQty), count($oldRates));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'product_name' => (string) ($oldProductNames[$i] ?? ''),
            'stone_type' => (string) ($oldStoneTypes[$i] ?? ''),
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
        'qty' => '',
        'rate' => '',
    ];
}

$purchaseDate = old('purchase_date', (string) ($purchase['purchase_date'] ?? date('Y-m-d')));
$vendorId = old('vendor_id', (string) ($purchase['vendor_id'] ?? ''));
$invoiceNo = old('invoice_no', (string) ($purchase['invoice_no'] ?? ''));
$dueDate = old('due_date', (string) ($purchase['due_date'] ?? ''));
$taxPercentage = old('tax_percentage', (string) ($purchase['tax_percentage'] ?? '0'));
$invoiceTotal = old('invoice_total', (string) ($purchase['invoice_total'] ?? '0.00'));
$notes = old('notes', (string) ($purchase['notes'] ?? ''));
$supplierName = old('supplier_name', (string) ($purchase['supplier_name'] ?? ''));
$supplierAddress = old('supplier_address', (string) ($purchase['supplier_address'] ?? ''));
$supplierGstin = old('supplier_gstin', (string) ($purchase['supplier_gstin'] ?? ''));
$supplierPhone = old('supplier_phone', (string) ($purchase['supplier_phone'] ?? ''));
$supplierEmail = old('supplier_email', (string) ($purchase['supplier_email'] ?? ''));
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
                        <option value="<?= (int) $vendor['id'] ?>"
                            data-name="<?= esc((string) ($vendor['name'] ?? '')) ?>"
                            data-address="<?= esc((string) ($vendor['address'] ?? '')) ?>"
                            data-gstin="<?= esc((string) ($vendor['gstin'] ?? '')) ?>"
                            data-phone="<?= esc((string) ($vendor['phone'] ?? '')) ?>"
                            data-email="<?= esc((string) ($vendor['email'] ?? '')) ?>"
                            <?= (string) $vendorId === (string) $vendor['id'] ? 'selected' : '' ?>>
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
            <div class="col-md-12"><hr class="my-1"><div class="small fw-semibold text-muted">SUPPLIER DETAILS</div></div>
            <div class="col-md-4"><label class="form-label">Supplier Name</label><input type="text" name="supplier_name" id="supplier_name" class="form-control" readonly value="<?= esc((string) $supplierName) ?>"></div>
            <div class="col-md-8"><label class="form-label">Address</label><input type="text" name="supplier_address" id="supplier_address" class="form-control" readonly value="<?= esc((string) $supplierAddress) ?>"></div>
            <div class="col-md-3"><label class="form-label">GSTIN</label><input type="text" name="supplier_gstin" id="supplier_gstin" class="form-control" readonly value="<?= esc((string) $supplierGstin) ?>"></div>
            <div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="supplier_phone" id="supplier_phone" class="form-control" readonly value="<?= esc((string) $supplierPhone) ?>"></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="supplier_email" id="supplier_email" class="form-control" readonly value="<?= esc((string) $supplierEmail) ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
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
                        <th style="min-width:180px;">Product Name</th>
                        <th style="min-width:150px;">Stone Type</th>
                        <th style="min-width:120px;">Quantity</th>
                        <th style="min-width:120px;">Rate</th>
                        <th style="min-width:140px;">Line Value</th>
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
    <div class="card-header"><h6 class="mb-0">Tax Information</h6></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Taxable Amount</label><input type="text" id="subtotal_display" class="form-control" readonly value="0.00"></div>
            <div class="col-md-3"><label class="form-label">GST %</label><input type="number" step="0.001" min="0" max="100" name="tax_percentage" id="tax_percentage" class="form-control" value="<?= esc((string) $taxPercentage) ?>"></div>
            <div class="col-md-3"><label class="form-label">GST Amount</label><input type="text" id="tax_value_display" class="form-control" readonly value="0.00"></div>
            <div class="col-md-3"><label class="form-label">Invoice Total</label><input type="number" step="0.01" min="0" name="invoice_total" id="invoice_total" class="form-control fw-semibold" readonly value="<?= esc((string) $invoiceTotal) ?>"></div>
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
            const qty = parseFloat((row.querySelector('.line-qty') || {}).value || '0') || 0;
            const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
            const output = row.querySelector('.line-value-display');
            if (output) {
                output.value = (qty * rate).toFixed(2);
            }
            recalcTotals();
        }

        function recalcTotals() {
            let subtotal = 0;
            body.querySelectorAll('tr').forEach(function(row) {
                const qty = parseFloat((row.querySelector('.line-qty') || {}).value || '0') || 0;
                const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
                subtotal += (qty * rate);
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
            jQuery('#vendor_id').select2({ width: '100%' });
        }

        const vendorSelect = document.getElementById('vendor_id');
        function fillVendorDetails() {
            const selected = vendorSelect ? vendorSelect.options[vendorSelect.selectedIndex] : null;
            const fields = {
                supplier_name: 'name',
                supplier_address: 'address',
                supplier_gstin: 'gstin',
                supplier_phone: 'phone',
                supplier_email: 'email'
            };
            Object.keys(fields).forEach(function(id) {
                const input = document.getElementById(id);
                if (input) input.value = selected && selected.value ? (selected.getAttribute('data-' + fields[id]) || '') : '';
            });
        }
        if (vendorSelect) vendorSelect.addEventListener('change', fillVendorDetails);

        recalcTotals();
    })();
</script>
