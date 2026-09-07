<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldFgIds = array_map('intval', (array) old('fg_item_ids', []));
$defaultGst = '';
foreach (($gstMasters ?? []) as $master) {
    if (stripos((string) ($master['name'] ?? ''), 'Local GST 3%') !== false) {
        $defaultGst = (string) $master['id'];
        break;
    }
}
?>
<style>
.sale-builder-card{border:1px solid #e5e9f0;border-radius:16px;overflow:hidden}.sale-builder-card>.card-header{background:#fff;border-bottom:1px solid #eaecf0;padding:17px 20px}.sale-builder-card>.card-body{padding:20px}.sale-section-number{align-items:center;background:#fff4e6;border-radius:50%;color:#b54708;display:inline-flex;font-size:.8rem;font-weight:800;height:30px;justify-content:center;margin-right:8px;width:30px}.sale-selected-empty{border:1px dashed #cfd5df;border-radius:12px;color:#667085;padding:28px;text-align:center}.sale-thumb{background:#f2f4f7;border:1px solid #e4e7ec;border-radius:10px;height:58px;object-fit:cover;width:58px}.sale-thumb-empty{align-items:center;display:flex;justify-content:center}.select2-result-jewel{align-items:center;display:flex;gap:10px;padding:4px}.select2-result-jewel img{border-radius:8px;height:48px;object-fit:cover;width:48px}.select2-result-jewel .meta{color:#667085;display:block;font-size:11px;margin-top:2px}.sale-total-row{align-items:center;border-bottom:1px solid #eaecf0;display:flex;justify-content:space-between;padding:10px 0}.sale-total-row:last-child{border:0}.sale-total-row.grand{color:#101828;font-size:1.12rem}.sale-metric{background:#f8fafc;border:1px solid #eaecf0;border-radius:12px;padding:13px}.sale-metric span{color:#667085;display:block;font-size:.75rem}.sale-metric strong{display:block;font-size:1rem;margin-top:4px}.selected-jewellery-table td{vertical-align:middle}.sticky-summary{position:sticky;top:88px}.tax-help{color:#667085;font-size:.75rem}.form-label{font-weight:600}@media(max-width:991.98px){.sticky-summary{position:static}}
</style>

<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Wholesale billing</span>
        <h4 class="mb-1">Create Studded Jewellery Sale</h4>
        <p class="mb-0">Select finished jewellery visually, set component rates and generate the tax invoice with packing list.</p>
    </div>
    <a href="<?= site_url('admin/studded-jewellery/sale-bills') ?>" class="btn btn-light"><i class="fe fe-arrow-left me-1"></i>Sale Bills</a>
</div>

<form method="post" action="<?= esc($formAction) ?>" id="saleBuilderForm">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-start">
        <div class="col-xl-8">
            <div class="card sale-builder-card mb-3">
                <div class="card-header"><h5 class="mb-0"><span class="sale-section-number">1</span>Bill Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Sale Date <span class="text-danger">*</span></label><input type="date" name="sale_date" class="form-control" value="<?= esc((string) old('sale_date', date('Y-m-d'))) ?>" required></div>
                        <div class="col-md-8">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select select2" required>
                                <option value="">Select customer</option>
                                <?php foreach (($customers ?? []) as $customer): ?>
                                    <option value="<?= (int) $customer['id'] ?>" data-phone="<?= esc((string) ($customer['phone'] ?? ''), 'attr') ?>" data-gstin="<?= esc((string) ($customer['gstin'] ?? ''), 'attr') ?>" <?= (string) old('customer_id') === (string) $customer['id'] ? 'selected' : '' ?>><?= esc((string) ($customer['name'] ?? '-')) ?><?= ! empty($customer['customer_code']) ? ' · ' . esc((string) $customer['customer_code']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="customerHint" class="tax-help mt-2">Select the buyer whose GST and billing details will print on the invoice.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card sale-builder-card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0"><span class="sale-section-number">2</span>Select Jewellery</h5>
                    <span class="badge bg-light text-dark border"><span id="selectedCount">0</span> selected</span>
                </div>
                <div class="card-body">
                    <?php if (($fgItems ?? []) === []): ?>
                        <div class="alert alert-warning mb-0">No saleable jewellery is available. Completed jewellery will appear here until it is sold, transferred or delivered.</div>
                    <?php else: ?>
                        <label class="form-label">Search by tag, order or jewellery name <span class="text-danger">*</span></label>
                        <select name="fg_item_ids[]" id="fg_item_ids" class="form-select" multiple required style="width:100%">
                            <?php foreach (($fgItems ?? []) as $item): ?>
                                <?php
                                $itemPayload = [
                                    'id' => (int) $item['id'],
                                    'tag' => (string) ($item['tag_no'] ?? '-'),
                                    'order' => (string) ($item['order_no'] ?? '-'),
                                    'name' => (string) (($item['order_name'] ?? '') ?: ($item['design_name'] ?? 'Jewellery')),
                                    'design' => (string) ($item['design_name'] ?? '-'),
                                    'purity' => (string) ($item['purity_label'] ?? '-'),
                                    'gross' => (float) ($item['gross_wt'] ?? 0),
                                    'gold' => (float) ($item['net_gold_wt'] ?? 0),
                                    'diamond' => (float) ($item['diamond_cts'] ?? 0),
                                    'stone' => (float) ($item['stone_wt'] ?? 0),
                                    'image' => (string) ($item['image_url'] ?? ''),
                                ];
                                ?>
                                <option value="<?= (int) $item['id'] ?>" data-jewellery="<?= esc((string) json_encode($itemPayload, JSON_UNESCAPED_SLASHES), 'attr') ?>" data-image="<?= esc((string) ($item['image_url'] ?? ''), 'attr') ?>" <?= in_array((int) $item['id'], $oldFgIds, true) ? 'selected' : '' ?>><?= esc((string) ($item['tag_no'] ?? '-')) ?> · <?= esc((string) (($item['order_name'] ?? '') ?: ($item['design_name'] ?? 'Jewellery'))) ?> · <?= number_format((float) ($item['net_gold_wt'] ?? 0), 3) ?> gm</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="tax-help mt-2">The image appears inside the searchable dropdown and in the selection table below.</div>
                        <div id="selectedEmpty" class="sale-selected-empty mt-3"><i class="fe fe-image d-block mb-2" style="font-size:1.7rem"></i>Select one or more jewellery items to build this bill.</div>
                        <div id="selectedTableWrap" class="table-responsive mt-3 d-none">
                            <table class="table table-hover align-middle selected-jewellery-table mb-0" data-dt-skip="1">
                                <thead><tr><th>Photo</th><th>Jewellery</th><th>Order</th><th>Gross</th><th>Gold</th><th>Diamond</th><th>Stone</th></tr></thead>
                                <tbody id="selectedTableBody"></tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card sale-builder-card">
                <div class="card-header"><h5 class="mb-0"><span class="sale-section-number">3</span>Rates &amp; Tax</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Gold Rate / gm</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="gold_rate" id="gold_rate" class="form-control calc-field" min="0" step="0.01" value="<?= esc((string) old('gold_rate', '0.00')) ?>"></div></div>
                        <div class="col-md-4"><label class="form-label">Diamond Rate / ct</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="diamond_rate" id="diamond_rate" class="form-control calc-field" min="0" step="0.01" value="<?= esc((string) old('diamond_rate', '0.00')) ?>"></div></div>
                        <div class="col-md-4"><label class="form-label">Stone Rate / ct</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="stone_rate" id="stone_rate" class="form-control calc-field" min="0" step="0.01" value="<?= esc((string) old('stone_rate', '0.00')) ?>"></div></div>
                        <div class="col-md-4"><label class="form-label">Other Charges</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="other_amount" id="other_amount" class="form-control calc-field" min="0" step="0.01" value="<?= esc((string) old('other_amount', '0.00')) ?>"></div></div>
                        <div class="col-md-4">
                            <label class="form-label">GST Master <span class="text-danger">*</span></label>
                            <select name="gst_master_id" id="gst_master_id" class="form-select select2 calc-field" required>
                                <option value="">Select GST</option>
                                <?php foreach (($gstMasters ?? []) as $master): ?>
                                    <?php $selectedGst = (string) old('gst_master_id', $defaultGst); ?>
                                    <option value="<?= (int) $master['id'] ?>" data-rate="<?= esc((string) ($master['total_percentage'] ?? 0), 'attr') ?>" data-components="<?= esc((string) ($master['component_string'] ?? ''), 'attr') ?>" <?= $selectedGst === (string) $master['id'] ? 'selected' : '' ?>><?= esc((string) ($master['name'] ?? '-')) ?> (<?= number_format((float) ($master['total_percentage'] ?? 0), 2) ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                            <div id="taxBreakupHint" class="tax-help mt-2"></div>
                        </div>
                        <div class="col-md-2"><label class="form-label">Round Off</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="round_off_amount" id="round_off_amount" class="form-control calc-field" step="0.01" value="<?= esc((string) old('round_off_amount', '0.00')) ?>"></div></div>
                        <div class="col-md-2"><label class="form-label">HSN/SAC</label><input type="text" name="hsn_sac" class="form-control" maxlength="30" value="<?= esc((string) old('hsn_sac', '711319')) ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Amount Received</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" name="received_amount" class="form-control" min="0" step="0.01" value="<?= esc((string) old('received_amount', '0.00')) ?>"></div></div>
                        <div class="col-md-4"><label class="form-label">Payment Mode</label><select name="payment_mode" class="form-select"><option>Bank Transfer</option><option>Cheque</option><option>Cash</option><option>UPI</option><option>Other</option></select></div>
                        <div class="col-md-4"><label class="form-label">Payment Reference</label><input type="text" name="reference_no" class="form-control" maxlength="100" value="<?= esc((string) old('reference_no')) ?>" placeholder="UTR / cheque number"></div>
                        <div class="col-12"><label class="form-label">Notes / Terms</label><textarea name="notes" class="form-control" rows="3" maxlength="2000" placeholder="Dispatch, delivery or commercial note"><?= esc((string) old('notes')) ?></textarea></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card sale-builder-card sticky-summary">
                <div class="card-header"><h5 class="mb-0">Invoice Preview</h5></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-4"><div class="sale-metric"><span>Gold</span><strong id="goldWeight">0.000 gm</strong></div></div>
                        <div class="col-4"><div class="sale-metric"><span>Diamond</span><strong id="diamondWeight">0.000 ct</strong></div></div>
                        <div class="col-4"><div class="sale-metric"><span>Stone</span><strong id="stoneWeight">0.000 ct</strong></div></div>
                    </div>
                    <div class="sale-total-row"><span>Gold value</span><strong id="goldAmount">₹0.00</strong></div>
                    <div class="sale-total-row"><span>Diamond value</span><strong id="diamondAmount">₹0.00</strong></div>
                    <div class="sale-total-row"><span>Stone value</span><strong id="stoneAmount">₹0.00</strong></div>
                    <div class="sale-total-row"><span>Other charges</span><strong id="otherAmountPreview">₹0.00</strong></div>
                    <div class="sale-total-row"><span>Taxable value</span><strong id="taxableAmount">₹0.00</strong></div>
                    <div class="sale-total-row"><span id="taxLabel">GST</span><strong id="taxAmount">₹0.00</strong></div>
                    <div class="sale-total-row"><span>Round off</span><strong id="roundOffPreview">₹0.00</strong></div>
                    <div class="sale-total-row grand"><strong>Invoice value</strong><strong id="invoiceTotal">₹0.00</strong></div>
                    <div class="alert alert-light border mt-3 small"><i class="fe fe-file-text me-1"></i>Tax invoice and a combined packing list will be generated automatically.</div>
                    <button class="btn btn-primary w-100" type="submit" <?= ($fgItems ?? []) === [] ? 'disabled' : '' ?>><i class="fe fe-check-circle me-1"></i>Create Sale &amp; Documents</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const selector = document.getElementById('fg_item_ids');
    const currency = new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', minimumFractionDigits: 2});
    const number = value => Number.parseFloat(value || '0') || 0;
    const parseItem = option => {
        try { return JSON.parse(option.dataset.jewellery || '{}'); } catch (error) { return {}; }
    };

    function selectedItems() {
        if (!selector) return [];
        return Array.from(selector.selectedOptions).map(parseItem);
    }

    function renderSelection() {
        const items = selectedItems();
        const tbody = document.getElementById('selectedTableBody');
        const empty = document.getElementById('selectedEmpty');
        const wrap = document.getElementById('selectedTableWrap');
        document.getElementById('selectedCount').textContent = String(items.length);
        if (!tbody) return;
        tbody.innerHTML = '';
        items.forEach(item => {
            const row = document.createElement('tr');
            const image = item.image
                ? '<img class="sale-thumb" src="' + String(item.image).replace(/"/g, '&quot;') + '" alt="Jewellery">'
                : '<span class="sale-thumb sale-thumb-empty"><i class="fe fe-image"></i></span>';
            row.innerHTML = '<td>' + image + '</td>'
                + '<td><strong>' + escapeHtml(item.tag || '-') + '</strong><br><small class="text-muted">' + escapeHtml(item.name || item.design || '-') + ' · ' + escapeHtml(item.purity || '-') + '</small></td>'
                + '<td>' + escapeHtml(item.order || '-') + '</td>'
                + '<td>' + number(item.gross).toFixed(3) + ' gm</td>'
                + '<td>' + number(item.gold).toFixed(3) + ' gm</td>'
                + '<td>' + number(item.diamond).toFixed(3) + ' ct</td>'
                + '<td>' + number(item.stone).toFixed(3) + ' ct</td>';
            tbody.appendChild(row);
        });
        if (empty) empty.classList.toggle('d-none', items.length > 0);
        if (wrap) wrap.classList.toggle('d-none', items.length === 0);
        recalculate();
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function recalculate() {
        const items = selectedItems();
        const goldWeight = items.reduce((sum, item) => sum + number(item.gold), 0);
        const diamondWeight = items.reduce((sum, item) => sum + number(item.diamond), 0);
        const stoneWeight = items.reduce((sum, item) => sum + number(item.stone), 0);
        const goldValue = goldWeight * number(document.getElementById('gold_rate')?.value);
        const diamondValue = diamondWeight * number(document.getElementById('diamond_rate')?.value);
        const stoneValue = stoneWeight * number(document.getElementById('stone_rate')?.value);
        const otherValue = number(document.getElementById('other_amount')?.value);
        const taxable = goldValue + diamondValue + stoneValue + otherValue;
        const taxSelect = document.getElementById('gst_master_id');
        const taxOption = taxSelect && taxSelect.selectedIndex >= 0 ? taxSelect.options[taxSelect.selectedIndex] : null;
        const taxRate = number(taxOption?.dataset.rate);
        const taxValue = taxable * taxRate / 100;
        const roundOff = number(document.getElementById('round_off_amount')?.value);
        const invoice = taxable + taxValue + roundOff;
        document.getElementById('goldWeight').textContent = goldWeight.toFixed(3) + ' gm';
        document.getElementById('diamondWeight').textContent = diamondWeight.toFixed(3) + ' ct';
        document.getElementById('stoneWeight').textContent = stoneWeight.toFixed(3) + ' ct';
        document.getElementById('goldAmount').textContent = currency.format(goldValue);
        document.getElementById('diamondAmount').textContent = currency.format(diamondValue);
        document.getElementById('stoneAmount').textContent = currency.format(stoneValue);
        document.getElementById('otherAmountPreview').textContent = currency.format(otherValue);
        document.getElementById('taxableAmount').textContent = currency.format(taxable);
        document.getElementById('taxAmount').textContent = currency.format(taxValue);
        document.getElementById('roundOffPreview').textContent = currency.format(roundOff);
        document.getElementById('invoiceTotal').textContent = currency.format(invoice);
        document.getElementById('taxLabel').textContent = taxRate > 0 ? 'GST (' + taxRate.toFixed(2) + '%)' : 'GST';
        const componentText = taxOption?.dataset.components || '';
        document.getElementById('taxBreakupHint').textContent = componentText ? componentText.split('|').join(' + ') : 'No tax component selected.';
    }

    function customerHint() {
        const select = document.getElementById('customer_id');
        const option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
        const parts = [];
        if (option?.dataset.phone) parts.push('Phone: ' + option.dataset.phone);
        if (option?.dataset.gstin) parts.push('GSTIN: ' + option.dataset.gstin);
        document.getElementById('customerHint').textContent = parts.length ? parts.join(' · ') : 'Customer GST/address will print when available.';
    }

    if (window.jQuery && selector && window.jQuery.fn.select2) {
        window.jQuery(selector).select2({
            width: '100%',
            placeholder: 'Search and select finished jewellery',
            closeOnSelect: false,
            templateResult: function (state) {
                if (!state.id) return state.text;
                const item = parseItem(state.element);
                const node = document.createElement('div');
                node.className = 'select2-result-jewel';
                node.innerHTML = item.image ? '<img src="' + String(item.image).replace(/"/g, '&quot;') + '" alt="">' : '<span class="sale-thumb sale-thumb-empty"><i class="fe fe-image"></i></span>';
                const text = document.createElement('div');
                text.innerHTML = '<strong>' + escapeHtml(item.tag || '-') + ' · ' + escapeHtml(item.name || '-') + '</strong><span class="meta">Order ' + escapeHtml(item.order || '-') + ' · Gold ' + number(item.gold).toFixed(3) + ' gm · Diamond ' + number(item.diamond).toFixed(3) + ' ct</span>';
                node.appendChild(text);
                return node;
            },
            templateSelection: function (state) {
                if (!state.id) return state.text;
                const item = parseItem(state.element);
                return (item.tag || '-') + ' · ' + (item.name || '-');
            }
        }).on('change', renderSelection);
    } else if (selector) {
        selector.addEventListener('change', renderSelection);
    }
    document.querySelectorAll('.calc-field').forEach(field => {
        field.addEventListener('input', recalculate);
        field.addEventListener('change', recalculate);
    });
    const customer = document.getElementById('customer_id');
    if (customer) {
        customer.addEventListener('change', customerHint);
        if (window.jQuery) window.jQuery(customer).on('select2:select', customerHint);
    }
    document.getElementById('saleBuilderForm')?.addEventListener('submit', function (event) {
        if (selectedItems().length === 0) {
            event.preventDefault();
            window.alert('Please select at least one jewellery item.');
        }
    });
    renderSelection();
    customerHint();
})();
</script>
<?= $this->endSection() ?>
