<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldPurityIds = (array) old('gold_purity_id');
    $oldColors = (array) old('color_name');
    $oldForms = (array) old('form_type');
    $oldWeights = (array) old('weight_gm');
    $oldRates = (array) old('rate_per_gm');
    $oldDescriptions = (array) old('line_description');
    $oldHsnCodes = (array) old('hsn_sac');
    $oldUnits = (array) old('unit');

    $max = max(count($oldItemIds), count($oldPurityIds), count($oldColors), count($oldForms), count($oldWeights), count($oldRates));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'gold_purity_id' => (string) ($oldPurityIds[$i] ?? ''),
            'color_name' => (string) ($oldColors[$i] ?? ''),
            'form_type' => (string) ($oldForms[$i] ?? ''),
            'description' => (string) ($oldDescriptions[$i] ?? ''),
            'hsn_sac' => (string) ($oldHsnCodes[$i] ?? ''),
            'unit' => (string) ($oldUnits[$i] ?? 'GMS'),
            'weight_gm' => (string) ($oldWeights[$i] ?? ''),
            'rate_per_gm' => (string) ($oldRates[$i] ?? ''),
        ];
    }
} elseif (($lines ?? []) !== []) {
    foreach ($lines as $line) {
        $rows[] = [
            'item_id' => (string) ($line['item_id'] ?? ''),
            'gold_purity_id' => (string) ($line['gold_purity_id'] ?? ''),
            'color_name' => (string) ($line['color_name'] ?? ''),
            'form_type' => (string) ($line['form_type'] ?? ''),
            'description' => (string) ($line['description'] ?? ''),
            'hsn_sac' => (string) ($line['hsn_sac'] ?? ''),
            'unit' => (string) ($line['unit'] ?? 'GMS'),
            'weight_gm' => (string) ($line['weight_gm'] ?? ''),
            'rate_per_gm' => (string) ($line['rate_per_gm'] ?? ''),
        ];
    }
}

if ($rows === []) {
    $rows[] = [
        'item_id' => '',
        'gold_purity_id' => '',
        'color_name' => '',
        'form_type' => '',
        'description' => '',
        'hsn_sac' => '',
        'unit' => 'GMS',
        'weight_gm' => '',
        'rate_per_gm' => '',
    ];
}

$purchaseDate = old('purchase_date', (string) ($purchase['purchase_date'] ?? date('Y-m-d')));
$supplierName = old('supplier_name', (string) ($purchase['supplier_name'] ?? ''));
$vendorId = old('vendor_id', (string) ($purchase['vendor_id'] ?? ''));
$supplierAddress = old('supplier_address', (string) ($purchase['supplier_address'] ?? ''));
$supplierGstin = old('supplier_gstin', (string) ($purchase['supplier_gstin'] ?? ''));
$supplierPhone = old('supplier_phone', (string) ($purchase['supplier_phone'] ?? ''));
$supplierEmail = old('supplier_email', (string) ($purchase['supplier_email'] ?? ''));
$invoiceNo = old('invoice_no', (string) ($purchase['invoice_no'] ?? ''));
$dueDate = old('due_date', (string) ($purchase['due_date'] ?? ''));
$placeOfSupply = old('place_of_supply', (string) ($purchase['place_of_supply'] ?? ''));
$purchaseDescription = old('purchase_description', (string) ($purchase['purchase_description'] ?? ''));
$gstMasterId = old('gst_master_id', (string) ($purchase['gst_master_id'] ?? ''));
$taxableAmount = old('taxable_amount', (string) ($purchase['taxable_amount'] ?? ''));
$cgstRate = old('cgst_rate', (string) ($purchase['cgst_rate'] ?? ''));
$cgstAmount = old('cgst_amount', (string) ($purchase['cgst_amount'] ?? ''));
$sgstRate = old('sgst_rate', (string) ($purchase['sgst_rate'] ?? ''));
$sgstAmount = old('sgst_amount', (string) ($purchase['sgst_amount'] ?? ''));
$igstRate = old('igst_rate', (string) ($purchase['igst_rate'] ?? ''));
$igstAmount = old('igst_amount', (string) ($purchase['igst_amount'] ?? ''));
$roundOff = old('round_off_amount', (string) ($purchase['round_off_amount'] ?? ''));
$invoiceTotal = old('invoice_total', (string) ($purchase['invoice_total'] ?? ''));
$paymentStatus = old('payment_status', (string) ($purchase['payment_status'] ?? 'Pending'));
$paidAmount = old('paid_amount', (string) ($purchase['paid_amount'] ?? '0'));
$paymentDate = old('payment_date', (string) ($purchase['payment_date'] ?? ''));
$notes = old('notes', (string) ($purchase['notes'] ?? ''));
$locationId = old('location_id', (string) ($purchase['location_id'] ?? ''));
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                <input type="date" name="purchase_date" class="form-control" required value="<?= esc((string) $purchaseDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Vendor</label>
                <select name="vendor_id" id="vendor_id" class="form-select select2">
                    <option value="">Select vendor</option>
                    <?php foreach (($vendors ?? []) as $vendor): ?>
                        <option value="<?= (int) $vendor['id'] ?>"
                            data-name="<?= esc((string) ($vendor['name'] ?? '')) ?>"
                            data-address="<?= esc((string) ($vendor['address'] ?? '')) ?>"
                            data-gstin="<?= esc((string) ($vendor['gstin'] ?? '')) ?>"
                            data-phone="<?= esc((string) ($vendor['phone'] ?? '')) ?>"
                            data-email="<?= esc((string) ($vendor['email'] ?? '')) ?>"
                            <?= (string) $vendorId === (string) $vendor['id'] ? 'selected' : '' ?>><?= esc((string) $vendor['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Supplier Name</label>
                <input type="text" name="supplier_name" id="supplier_name" class="form-control" value="<?= esc((string) $supplierName) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Invoice No</label>
                <input type="text" name="invoice_no" class="form-control" value="<?= esc((string) $invoiceNo) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Purchase Location <span class="text-danger">*</span></label>
                <select name="location_id" class="form-select" required>
                    <option value="">Select location</option>
                    <?php foreach (($locations ?? []) as $loc): ?>
                        <option value="<?= (int) $loc['id'] ?>" <?= (string) $locationId === (string) $loc['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Supplier Address</label>
                <input type="text" name="supplier_address" id="supplier_address" class="form-control" value="<?= esc((string) $supplierAddress) ?>">
            </div>
            <div class="col-md-3"><label class="form-label">Supplier GSTIN</label><input type="text" name="supplier_gstin" id="supplier_gstin" class="form-control" value="<?= esc((string) $supplierGstin) ?>"></div>
            <div class="col-md-3"><label class="form-label">Supplier Phone</label><input type="text" name="supplier_phone" id="supplier_phone" class="form-control" value="<?= esc((string) $supplierPhone) ?>"></div>
            <div class="col-md-3"><label class="form-label">Supplier Email</label><input type="email" name="supplier_email" id="supplier_email" class="form-control" value="<?= esc((string) $supplierEmail) ?>"></div>
            <div class="col-md-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= esc((string) $dueDate) ?>"></div>
            <div class="col-md-4"><label class="form-label">Place of Supply</label><input type="text" name="place_of_supply" class="form-control" value="<?= esc((string) $placeOfSupply) ?>"></div>
            <div class="col-md-8"><label class="form-label">Purchase Description</label><input type="text" name="purchase_description" class="form-control" value="<?= esc((string) $purchaseDescription) ?>"></div>
            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Purchase Lines</h6>
        <div class="d-flex gap-2">
            <a href="<?= site_url('admin/gold-inventory/purities/create') ?>" class="btn btn-sm btn-outline-secondary"><i class="fe fe-percent"></i> New Purity</a>
            <a href="<?= site_url('admin/gold-inventory/products/create') ?>" class="btn btn-sm btn-outline-secondary"><i class="fe fe-package"></i> New Product</a>
            <button type="button" class="btn btn-sm btn-primary" id="add-line"><i class="fe fe-plus"></i> Add Line</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="line-table">
                <thead>
                    <tr>
                        <th style="min-width:220px;">Existing Item</th>
                        <th style="min-width:180px;">Gold Purity</th>
                        <th style="min-width:120px;">Color</th>
                        <th style="min-width:120px;">Form</th>
                        <th style="min-width:180px;">Description</th>
                        <th style="min-width:110px;">HSN/SAC</th>
                        <th style="min-width:90px;">Unit</th>
                        <th style="min-width:110px;">Weight (gm)</th>
                        <th style="min-width:120px;">Pure Weight (gm)</th>
                        <th style="min-width:110px;">Rate/gm</th>
                        <th style="min-width:120px;">Line Value</th>
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
                                        $label = (string) ($item['master_purity_code'] ?: $item['purity_code'] ?: 'NA');
                                        $label .= ' / ' . (string) ($item['color_name'] ?: 'NA');
                                        $label .= ' / ' . (string) ($item['form_type'] ?: 'Raw');
                                        ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            data-gold_purity_id="<?= esc((string) ($item['gold_purity_id'] ?? '')) ?>"
                                            data-purity_percent="<?= esc((string) ($item['purity_percent'] ?? '0')) ?>"
                                            data-color_name="<?= esc((string) ($item['color_name'] ?? '')) ?>"
                                            data-form_type="<?= esc((string) ($item['form_type'] ?? '')) ?>"
                                            <?= (string) $row['item_id'] === (string) $item['id'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="gold_purity_id[]" class="form-select line-purity">
                                    <option value="">Select purity</option>
                                    <?php foreach (($purities ?? []) as $purity): ?>
                                        <option value="<?= (int) $purity['id'] ?>" data-purity_percent="<?= esc((string) ($purity['purity_percent'] ?? '0')) ?>" <?= (string) $row['gold_purity_id'] === (string) $purity['id'] ? 'selected' : '' ?>>
                                            <?= esc((string) $purity['purity_code']) ?> (<?= number_format((float) $purity['purity_percent'], 2) ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="color_name[]" class="form-control line-color" value="<?= esc((string) $row['color_name']) ?>" placeholder="YG/WG/RG"></td>
                            <td><input type="text" name="form_type[]" class="form-control line-form" value="<?= esc((string) $row['form_type']) ?>" placeholder="Bar/Grain/Scrap"></td>
                            <td><input type="text" name="line_description[]" class="form-control" value="<?= esc((string) $row['description']) ?>" placeholder="Pure Gold"></td>
                            <td><input type="text" name="hsn_sac[]" class="form-control" value="<?= esc((string) $row['hsn_sac']) ?>"></td>
                            <td><input type="text" name="unit[]" class="form-control" value="<?= esc((string) $row['unit']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="weight_gm[]" class="form-control line-weight" value="<?= esc((string) $row['weight_gm']) ?>"></td>
                            <td><input type="text" class="form-control line-fine-display" readonly></td>
                            <td><input type="number" step="0.01" min="0" name="rate_per_gm[]" class="form-control line-rate" value="<?= esc((string) $row['rate_per_gm']) ?>"></td>
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
    <div class="card-header"><h6 class="mb-0">Tax & Payment Information</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">GST Master <span class="text-danger">*</span></label><select name="gst_master_id" id="gst_master_id" class="form-select" required><option value="">Select GST master</option><?php foreach (($gstMasters ?? []) as $master): ?><option value="<?= (int) $master['id'] ?>" data-components="<?= esc(json_encode($master['components'] ?? []), 'attr') ?>" <?= (string) $gstMasterId === (string) $master['id'] ? 'selected' : '' ?>><?= esc((string) $master['name']) ?> (<?= number_format((float) $master['total_percentage'], 3) ?>%)</option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Taxable Amount</label><input type="number" step="0.01" min="0" name="taxable_amount" id="taxable_amount" class="form-control" readonly value="<?= esc((string) $taxableAmount) ?>"></div>
            <div class="col-md-5"><label class="form-label">Tax breakup</label><div id="gst_breakup_display" class="form-control bg-light h-auto" style="min-height:38px">Select a GST master</div></div>
            <div class="col-md-2"><label class="form-label">CGST %</label><input type="number" step="0.001" min="0" name="cgst_rate" id="cgst_rate" class="form-control" readonly value="<?= esc((string) $cgstRate) ?>"></div>
            <div class="col-md-2"><label class="form-label">CGST Amount</label><input type="number" step="0.01" min="0" name="cgst_amount" id="cgst_amount" class="form-control" readonly value="<?= esc((string) $cgstAmount) ?>"></div>
            <div class="col-md-2"><label class="form-label">SGST %</label><input type="number" step="0.001" min="0" name="sgst_rate" id="sgst_rate" class="form-control" value="<?= esc((string) $sgstRate) ?>"></div>
            <div class="col-md-3"><label class="form-label">SGST Amount</label><input type="number" step="0.01" min="0" name="sgst_amount" id="sgst_amount" class="form-control" readonly value="<?= esc((string) $sgstAmount) ?>"></div>
            <div class="col-md-2"><label class="form-label">IGST %</label><input type="number" step="0.001" min="0" name="igst_rate" id="igst_rate" class="form-control" value="<?= esc((string) $igstRate) ?>"></div>
            <div class="col-md-2"><label class="form-label">IGST Amount</label><input type="number" step="0.01" min="0" name="igst_amount" id="igst_amount" class="form-control" readonly value="<?= esc((string) $igstAmount) ?>"></div>
            <div class="col-md-2"><label class="form-label">Round Off</label><input type="number" step="0.01" name="round_off_amount" id="round_off_amount" class="form-control" value="<?= esc((string) $roundOff) ?>"></div>
            <div class="col-md-3"><label class="form-label">Invoice Total</label><input type="number" step="0.01" min="0" name="invoice_total" id="invoice_total" class="form-control fw-semibold" readonly value="<?= esc((string) $invoiceTotal) ?>"></div>
            <div class="col-md-3"><label class="form-label">Payment Status</label><select name="payment_status" class="form-select"><?php foreach (['Pending', 'Partial', 'Paid'] as $status): ?><option value="<?= $status ?>" <?= $paymentStatus === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Paid Amount</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="<?= esc((string) $paidAmount) ?>"></div>
            <div class="col-md-3"><label class="form-label">Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= esc((string) $paymentDate) ?>"></div>
        </div>
    </div>
</div>

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Purchase</button>
</div>

<template id="line-template">
    <tr>
        <td>
            <select name="item_id[]" class="form-select existing-item">
                <option value="">Select existing (optional)</option>
                <?php foreach (($items ?? []) as $item): ?>
                    <?php
                    $label = (string) ($item['master_purity_code'] ?: $item['purity_code'] ?: 'NA');
                    $label .= ' / ' . (string) ($item['color_name'] ?: 'NA');
                    $label .= ' / ' . (string) ($item['form_type'] ?: 'Raw');
                    ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-gold_purity_id="<?= esc((string) ($item['gold_purity_id'] ?? '')) ?>"
                        data-purity_percent="<?= esc((string) ($item['purity_percent'] ?? '0')) ?>"
                        data-color_name="<?= esc((string) ($item['color_name'] ?? '')) ?>"
                        data-form_type="<?= esc((string) ($item['form_type'] ?? '')) ?>"
                    >
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="gold_purity_id[]" class="form-select line-purity">
                <option value="">Select purity</option>
                <?php foreach (($purities ?? []) as $purity): ?>
                    <option value="<?= (int) $purity['id'] ?>" data-purity_percent="<?= esc((string) ($purity['purity_percent'] ?? '0')) ?>">
                        <?= esc((string) $purity['purity_code']) ?> (<?= number_format((float) $purity['purity_percent'], 2) ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" name="color_name[]" class="form-control line-color" placeholder="YG/WG/RG"></td>
        <td><input type="text" name="form_type[]" class="form-control line-form" placeholder="Bar/Grain/Scrap"></td>
        <td><input type="text" name="line_description[]" class="form-control" placeholder="Pure Gold"></td>
        <td><input type="text" name="hsn_sac[]" class="form-control"></td>
        <td><input type="text" name="unit[]" class="form-control" value="GMS"></td>
        <td><input type="number" step="0.001" min="0" name="weight_gm[]" class="form-control line-weight"></td>
        <td><input type="text" class="form-control line-fine-display" readonly></td>
        <td><input type="number" step="0.01" min="0" name="rate_per_gm[]" class="form-control line-rate"></td>
        <td><input type="text" class="form-control line-value-display" readonly></td>
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
            const weight = parseFloat((row.querySelector('.line-weight') || {}).value || '0') || 0;
            const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
            const puritySelect = row.querySelector('.line-purity');
            const selectedPurity = puritySelect ? puritySelect.options[puritySelect.selectedIndex] : null;
            const purityPercent = selectedPurity ? (parseFloat(selectedPurity.getAttribute('data-purity_percent') || '0') || 0) : 0;
            const output = row.querySelector('.line-value-display');
            const fineOutput = row.querySelector('.line-fine-display');
            if (output) {
                output.value = (weight * rate).toFixed(2);
            }
            if (fineOutput) {
                fineOutput.value = purityPercent > 0 ? (weight * purityPercent / 100).toFixed(3) : '';
            }
            recalcTotals();
        }

        function numberValue(id) {
            const element = document.getElementById(id);
            return Math.max(0, parseFloat((element || {}).value || '0') || 0);
        }

        function recalcTotals() {
            let taxable = 0;
            body.querySelectorAll('tr').forEach(function(row) {
                const weight = parseFloat((row.querySelector('.line-weight') || {}).value || '0') || 0;
                const rate = parseFloat((row.querySelector('.line-rate') || {}).value || '0') || 0;
                taxable += weight * rate;
            });

            const gstSelect = document.getElementById('gst_master_id');
            const selected = gstSelect ? gstSelect.options[gstSelect.selectedIndex] : null;
            let components = [];
            try { components = JSON.parse((selected || {}).getAttribute?.('data-components') || '[]'); } catch (error) { components = []; }
            const rates = {CGST: 0, SGST: 0, IGST: 0};
            const amounts = {CGST: 0, SGST: 0, IGST: 0};
            components.forEach(function(component) { const name = String(component.name || '').toUpperCase(); const rate = Number(component.percentage || 0); if (Object.prototype.hasOwnProperty.call(rates, name)) { rates[name] += rate; amounts[name] += taxable * rate / 100; } });
            const cgst = amounts.CGST;
            const sgst = amounts.SGST;
            const igst = amounts.IGST;
            const roundOffElement = document.getElementById('round_off_amount');
            const roundOff = parseFloat((roundOffElement || {}).value || '0') || 0;
            const invoiceTotal = taxable + cgst + sgst + igst + roundOff;

            document.getElementById('taxable_amount').value = taxable.toFixed(2);
            document.getElementById('cgst_rate').value = rates.CGST.toFixed(3);
            document.getElementById('cgst_amount').value = cgst.toFixed(2);
            document.getElementById('sgst_rate').value = rates.SGST.toFixed(3);
            document.getElementById('sgst_amount').value = sgst.toFixed(2);
            document.getElementById('igst_rate').value = rates.IGST.toFixed(3);
            document.getElementById('igst_amount').value = igst.toFixed(2);
            document.getElementById('invoice_total').value = Math.max(0, invoiceTotal).toFixed(2);
            const breakup = document.getElementById('gst_breakup_display');
            if (breakup) breakup.textContent = components.length ? components.map(function(component) { const name = String(component.name || '').toUpperCase(); return name + ' ' + Number(component.percentage || 0).toFixed(3) + '% = ₹' + (taxable * Number(component.percentage || 0) / 100).toFixed(2); }).join(' | ') : 'No tax components';
        }

        function bindRow(row) {
            const itemSelect = row.querySelector('.existing-item');
            if (itemSelect) {
                itemSelect.addEventListener('change', function() {
                    const selected = itemSelect.options[itemSelect.selectedIndex];
                    if (!selected || !selected.value) {
                        recalcRow(row);
                        return;
                    }
                    const purity = row.querySelector('.line-purity');
                    const color = row.querySelector('.line-color');
                    const form = row.querySelector('.line-form');
                    if (purity) purity.value = selected.getAttribute('data-gold_purity_id') || '';
                    if (color) color.value = selected.getAttribute('data-color_name') || '';
                    if (form) form.value = selected.getAttribute('data-form_type') || '';
                    recalcRow(row);
                });
            }

            ['.line-weight', '.line-rate', '.line-purity'].forEach(function(selector) {
                const el = row.querySelector(selector);
                if (el) {
                    el.addEventListener('input', function() {
                        recalcRow(row);
                    });
                    el.addEventListener('change', function() {
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
                        row.querySelectorAll('select').forEach(function(select) {
                            select.value = '';
                        });
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
            if (row) bindRow(row);
            body.appendChild(fragment);
        });

        body.querySelectorAll('tr').forEach(function(row) { bindRow(row); });

        ['gst_master_id', 'round_off_amount'].forEach(function(id) {
            const element = document.getElementById(id);
            if (element) { element.addEventListener('input', recalcTotals); element.addEventListener('change', recalcTotals); }
        });

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
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('#vendor_id').select2({ width: '100%' });
        }
        recalcTotals();
    })();
</script>
