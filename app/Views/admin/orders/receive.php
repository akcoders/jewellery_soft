<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .receive-page { margin: 0 auto; max-width: 1500px; }
    .receive-hero { align-items: center; background: linear-gradient(130deg, #54111b, #9b1c2c); border-radius: 14px; color: #fff; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; margin-bottom: 20px; padding: 20px 24px; }
    .receive-hero h1 { color: #fff; font-size: clamp(21px, 2vw, 29px); font-weight: 800; margin: 4px 0; }
    .receive-hero .eyebrow { color: #f4d888; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .receive-section { border: 1px solid #e7eaf0; border-radius: 12px; margin-bottom: 16px; overflow: visible; }
    .receive-section > .card-header { align-items: center; background: #fff; border-bottom: 1px solid #edf0f4; display: flex; justify-content: space-between; padding: 13px 17px; }
    .receive-section > .card-body { padding: 17px; }
    .receive-table { min-width: 980px; table-layout: fixed; width: 100%; }
    .receive-table th, .receive-table td { vertical-align: middle; }
    .receive-diamond-table th:nth-child(1) { width: 40%; }
    .receive-diamond-table th:nth-child(2) { width: 8%; }
    .receive-diamond-table th:nth-child(3) { width: 11%; }
    .receive-diamond-table th:nth-child(4) { width: 9%; }
    .receive-diamond-table th:nth-child(5) { width: 11%; }
    .receive-diamond-table th:nth-child(6) { width: 8%; }
    .receive-diamond-table .select2-container, .receive-table .form-control { min-width: 0; width: 100% !important; }
    .receive-diamond-name { margin-top: 7px; }
    .receive-diamond-table .js-remove-row { display: block; margin: 0 auto; }
    .receive-footer { align-items: center; background: #fff; border-top: 1px solid #e7eaf0; bottom: 0; display: flex; gap: 10px; justify-content: flex-end; margin: 16px -16px -16px; padding: 14px 16px; position: sticky; z-index: 5; }
    .receive-page .select2-dropdown { z-index: 2070; }
    .receive-page .select2-container { max-width: 100%; }
    .receive-page .select2-container--default .select2-selection--multiple { border: 1px solid #dce1e9; border-radius: 8px; min-height: 42px; padding: 3px 6px; }
    .receive-page .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #e0aa36; box-shadow: 0 0 0 .2rem rgba(224, 170, 54, .14); }
    .receive-page .select2-container--default .select2-selection--multiple .select2-selection__choice { margin-top: 4px; max-width: 100%; overflow-wrap: anywhere; }
    .receive-page .select2-container--default .select2-selection--multiple .select2-search--inline { max-width: 100%; }
    .receive-page .select2-container--default .select2-selection--multiple .select2-search__field { margin-top: 6px; min-width: 150px; width: 100% !important; }
    .receive-page .select2-results__option { padding: 8px 10px; }
    .receive-page .select2-container--default .select2-selection--single { align-items: center; display: flex; min-height: 42px; }
    .receive-page .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; }
    .receive-page .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$orderNo = trim((string) ($order['order_no'] ?? '')) ?: ('#' . (int) $order['id']);
$suggestedPurityId = (int) ($items[0]['gold_purity_id'] ?? 0);
?>
<div class="receive-page">
    <div class="receive-hero">
        <div>
            <div class="eyebrow">Finished jewellery receiving</div>
            <h1>Receive Order <?= esc($orderNo) ?></h1>
            <div class="text-white-50"><?= esc((string) (($order['order_name'] ?? '') ?: 'Enter finished jewellery and material details')) ?></div>
        </div>
        <a class="btn btn-light" href="<?= site_url('admin/orders/' . $order['id']) ?>"><i class="fe fe-arrow-left me-1"></i>Back to Order</a>
    </div>

    <form id="receive-form" method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/receive') ?>">
        <?= csrf_field() ?>
        <div class="alert alert-info">Select the issued diamond bag lines for this order. You can combine several lines into one named finished piece. Stone shortage is automatically deducted from Stone Inventory.</div>

        <section class="card receive-section">
            <div class="card-header"><strong>1. Weight &amp; Purity</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Receive Location *</label><select name="location_id" class="form-select" required><option value="">Select location</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= esc((string) $location['name']) ?> (<?= esc((string) $location['location_type']) ?>)</option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Gross Weight (gm) *</label><input type="number" step="0.001" min="0.001" name="gross_weight_gm" class="form-control js-gross-weight" required></div>
                    <div class="col-md-3"><label class="form-label">Ornament Purity *</label><select name="gold_purity_id" class="form-select js-purity-select" required><option value="">Select purity</option><?php foreach ($goldPurities as $purity): ?><option value="<?= (int) $purity['id'] ?>" data-percent="<?= esc((string) number_format((float) $purity['purity_percent'], 3, '.', '')) ?>" <?= $suggestedPurityId === (int) $purity['id'] ? 'selected' : '' ?>><?= esc((string) $purity['purity_code']) ?> (<?= esc(number_format((float) $purity['purity_percent'], 3)) ?>%)<?= ! empty($purity['color_name']) ? ' · ' . esc((string) $purity['color_name']) : '' ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Net Weight (gm)</label><input type="text" class="form-control js-net-weight" value="0.000" readonly></div>
                    <div class="col-md-3"><label class="form-label">Pure Weight (gm)</label><input type="text" class="form-control js-pure-weight" value="0.000" readonly></div>
                    <div class="col-md-3"><label class="form-label">Gold Rate / gm *</label><input type="number" step="0.01" min="0.01" name="gold_rate_per_gm" class="form-control js-gold-rate" value="<?= esc((string) number_format((float) ($order['karigar_rate_per_gm'] ?? 0), 2, '.', '')) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Gold Amount</label><input type="text" class="form-control js-gold-total" value="0.00" readonly></div>
                </div>
            </div>
        </section>

        <section class="card receive-section">
            <div class="card-header"><strong>2. Studded Diamond</strong><button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="dia"><i class="fe fe-plus me-1"></i>Add Row</button></div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 receive-table receive-diamond-table">
                    <thead><tr><th>Available Bag Lines</th><th>PCS</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th></th></tr></thead>
                    <tbody class="js-dia-body"><tr>
                        <td>
                            <select multiple class="form-select js-diamond-balance-select">
                                <?php foreach ($karigarDiamondOptions as $option): ?>
                                    <option value="<?= esc((string) $option['value'], 'attr') ?>" data-final-name="<?= esc((string) $option['label'], 'attr') ?>" data-available-cts="<?= esc((string) $option['available_cts'], 'attr') ?>" data-available-pcs="<?= esc((string) $option['available_pcs'], 'attr') ?>"><?= esc((string) $option['label']) ?> · <?= number_format((float) $option['available_cts'], 3) ?> cts / <?= number_format((float) $option['available_pcs'], 0) ?> pcs</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="studded_diamond_type[]" class="js-dia-type">
                            <input type="text" name="studded_diamond_name[]" class="form-control receive-diamond-name js-dia-name" placeholder="Editable final name">
                        </td>
                        <td><input type="number" step="1" min="1" name="studded_diamond_pcs[]" class="form-control js-dia-pcs"></td>
                        <td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td>
                        <td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0"></td>
                        <td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" aria-label="Remove diamond row"><i class="fe fe-trash"></i></button></td>
                    </tr></tbody>
                </table>
            </div>
        </section>

        <section class="card receive-section">
            <div class="card-header"><strong>3. Stone</strong><button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="stone"><i class="fe fe-plus me-1"></i>Add Row</button></div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 receive-table">
                    <thead><tr><th>Inventory Item</th><th>Description</th><th>PCS</th><th>Weight (cts)</th><th>Rate</th><th>Total</th><th></th></tr></thead>
                    <tbody class="js-stone-body"><tr>
                        <td><select name="stone_item_id[]" class="form-select js-stone-inventory-select"><option value="">Select stone</option><?php foreach ($stoneInventoryItems as $stoneItem): ?><option value="<?= (int) $stoneItem['id'] ?>" data-description="<?= esc((string) (($stoneItem['stone_type'] ?? '') ?: $stoneItem['product_name']), 'attr') ?>" data-rate="<?= esc((string) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 'attr') ?>"><?= esc((string) $stoneItem['product_name'] . (($stoneItem['stone_type'] ?? '') !== '' ? ' · ' . $stoneItem['stone_type'] : '') . ' · ' . number_format((float) $stoneItem['qty_balance'], 3) . ' available · Rate ' . number_format((float) (($stoneItem['avg_rate'] ?? 0) ?: ($stoneItem['default_rate'] ?? 0)), 2)) ?></option><?php endforeach; ?></select></td>
                        <td><input type="text" name="stone_type[]" class="form-control"></td>
                        <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td>
                        <td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td>
                        <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0"></td>
                        <td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="0.00" readonly></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td>
                    </tr></tbody>
                </table>
            </div>
        </section>

        <section class="card receive-section">
            <div class="card-header"><strong>4. Labour Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Labour Rate / gm</label><input type="number" step="0.01" min="0" name="labour_rate_per_gm" class="form-control js-labour-rate" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Labour Amount</label><input type="text" class="form-control js-labour-total" value="0.00" readonly></div>
                    <div class="col-md-3"><label class="form-label">Wastage %</label><input type="number" step="0.001" min="0" max="100" name="wastage_percent" class="form-control js-wastage-percent" value="0" required><small class="text-muted"><span class="js-wastage-weight-text">0.000</span> gm at ornament purity</small></div>
                    <div class="col-md-3"><label class="form-label">Pure Gold Wastage Deduction</label><input type="text" class="form-control js-pure-wastage-weight" value="0.000" readonly><small class="text-muted">Deducted from karigar pure-gold ledger</small></div>
                </div>
            </div>
        </section>

        <section class="card receive-section">
            <div class="card-header"><strong>5. Other Material</strong><button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="other"><i class="fe fe-plus me-1"></i>Add Row</button></div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 receive-table">
                    <thead><tr><th>Description</th><th>PCS</th><th>Weight (gm)</th><th>Price</th><th>Total</th><th></th></tr></thead>
                    <tbody class="js-other-body"><tr><td><input type="text" name="other_desc[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="other_pcs[]" class="form-control js-other-pcs" value="0"></td><td><input type="number" step="0.001" min="0" name="other_weight_line_gm[]" class="form-control js-other-weight" value="0"></td><td><input type="number" step="0.01" min="0" name="other_price[]" class="form-control js-other-price" value="0"></td><td><input type="text" name="other_total[]" class="form-control js-other-total" value="0.00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="card receive-section">
            <div class="card-body"><label class="form-label" for="receive-notes">Remarks</label><input id="receive-notes" type="text" name="notes" class="form-control" maxlength="500"></div>
        </section>
        <div class="receive-footer">
            <a class="btn btn-light" href="<?= site_url('admin/orders/' . $order['id']) ?>">Cancel</a>
            <button type="submit" class="btn btn-success"><i class="fe fe-check me-1"></i>Save &amp; Complete Order</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const form = document.getElementById('receive-form');
    if (!form) return;
    const diamondOptions = <?= json_encode(array_values($karigarDiamondOptions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const stoneOptions = <?= json_encode(array_values($stoneInventoryItems), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const n = value => { const number = parseFloat(value || '0'); return Number.isFinite(number) ? number : 0; };
    const escapeHtml = value => String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;');

    function selectedElsewhere(current) {
        const used = new Set();
        form.querySelectorAll('.js-diamond-balance-select').forEach(select => {
            if (select !== current) Array.from(select.selectedOptions).forEach(option => used.add(option.value));
        });
        return used;
    }

    function diamondSelectOptions(current, selectedValues) {
        const selected = new Set(selectedValues || []);
        const used = selectedElsewhere(current);
        return diamondOptions.map(item => {
            const value = String(item.value || '');
            const label = String(item.label || value) + ' · ' + n(item.available_cts).toFixed(3) + ' cts / ' + n(item.available_pcs).toFixed(0) + ' pcs';
            return '<option value="' + escapeHtml(value) + '" data-final-name="' + escapeHtml(item.label || value) + '" data-available-cts="' + n(item.available_cts).toFixed(3) + '" data-available-pcs="' + n(item.available_pcs).toFixed(3) + '"' + (selected.has(value) ? ' selected' : '') + (used.has(value) ? ' disabled' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function syncSelectors() {
        const selects = Array.from(form.querySelectorAll('.js-diamond-balance-select'));
        const owners = new Map();
        selects.forEach(select => Array.from(select.selectedOptions).forEach(option => owners.set(option.value, select)));
        selects.forEach(select => {
            Array.from(select.options).forEach(option => {
                option.disabled = owners.has(option.value) && owners.get(option.value) !== select;
            });
            const selected = Array.from(select.selectedOptions).map(option => option.value);
            const hidden = select.closest('tr').querySelector('.js-dia-type');
            if (hidden) hidden.value = selected.length ? JSON.stringify(selected) : '';
            if (window.jQuery) window.jQuery(select).trigger('change.select2');
        });
    }

    function initSelects() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
        window.jQuery(form).find('.js-stone-inventory-select, .js-diamond-balance-select, .js-purity-select').each(function () {
            if (window.jQuery(this).hasClass('select2-hidden-accessible')) return;
            window.jQuery(this).select2({
                width: '100%',
                allowClear: !this.multiple,
                closeOnSelect: !this.multiple,
                placeholder: this.classList.contains('js-diamond-balance-select') ? 'Type to search, then select bag lines' : (this.classList.contains('js-purity-select') ? 'Search ornament purity' : 'Type to search stone inventory'),
                minimumResultsForSearch: 0,
                dropdownParent: window.jQuery(document.body)
            });
        });
    }

    function stoneSelectOptions() {
        return '<option value="">Select stone</option>' + stoneOptions.map(item => {
            const name = String(item.product_name || 'Stone');
            const type = String(item.stone_type || '');
            const rate = n(item.avg_rate || item.default_rate);
            const label = name + (type ? ' · ' + type : '') + ' · ' + n(item.qty_balance).toFixed(3) + ' available · Rate ' + rate.toFixed(2);
            return '<option value="' + escapeHtml(item.id) + '" data-description="' + escapeHtml(type || name) + '" data-rate="' + rate.toFixed(2) + '">' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function addRow(kind) {
        if (kind === 'dia') {
            return '<tr><td><select multiple class="form-select js-diamond-balance-select">' + diamondSelectOptions(null, []) + '</select><input type="hidden" name="studded_diamond_type[]" class="js-dia-type"><input type="text" name="studded_diamond_name[]" class="form-control receive-diamond-name js-dia-name" placeholder="Editable final name"></td><td><input type="number" step="1" min="1" name="studded_diamond_pcs[]" class="form-control js-dia-pcs"></td><td><input type="number" step="0.001" min="0" name="studded_diamond_weight[]" class="form-control js-dia-weight" value="0"></td><td><input type="number" step="0.01" min="0" name="studded_diamond_rate[]" class="form-control js-dia-rate" value="0"></td><td><input type="text" name="studded_diamond_total[]" class="form-control js-dia-total" value="0.00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row" aria-label="Remove diamond row"><i class="fe fe-trash"></i></button></td></tr>';
        }
        if (kind === 'stone') {
            return '<tr><td><select name="stone_item_id[]" class="form-select js-stone-inventory-select">' + stoneSelectOptions() + '</select></td><td><input type="text" name="stone_type[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control js-stone-pcs" value="0"></td><td><input type="number" step="0.001" min="0" name="stone_weight[]" class="form-control js-stone-weight" value="0"></td><td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control js-stone-rate" value="0"></td><td><input type="text" name="stone_total[]" class="form-control js-stone-total" value="0.00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr>';
        }
        return '<tr><td><input type="text" name="other_desc[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="other_pcs[]" class="form-control js-other-pcs" value="0"></td><td><input type="number" step="0.001" min="0" name="other_weight_line_gm[]" class="form-control js-other-weight" value="0"></td><td><input type="number" step="0.01" min="0" name="other_price[]" class="form-control js-other-price" value="0"></td><td><input type="text" name="other_total[]" class="form-control js-other-total" value="0.00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr>';
    }

    function updateDiamond(select) {
        const row = select.closest('tr');
        const selectedValues = window.jQuery ? window.jQuery(select).val() || [] : Array.from(select.selectedOptions).map(option => option.value);
        const selectedSet = new Set(Array.isArray(selectedValues) ? selectedValues.map(String) : [String(selectedValues)]);
        const selected = Array.from(select.options).filter(option => selectedSet.has(option.value));
        const pcs = selected.reduce((sum, option) => sum + n(option.dataset.availablePcs), 0);
        const cts = selected.reduce((sum, option) => sum + n(option.dataset.availableCts), 0);
        const hidden = row.querySelector('.js-dia-type');
        const pcsInput = row.querySelector('.js-dia-pcs');
        const weight = row.querySelector('.js-dia-weight');
        const name = row.querySelector('.js-dia-name');
        if (hidden) hidden.value = selected.length ? JSON.stringify(selected.map(option => option.value)) : '';
        if (pcsInput) pcsInput.value = pcs > 0 ? String(Math.floor(pcs)) : '';
        if (weight) weight.value = cts.toFixed(3);
        if (name && name.dataset.edited !== '1') name.value = selected.map(option => option.dataset.finalName || option.textContent.trim()).join(' + ');
        syncSelectors();
        recalc();
    }

    function recalc() {
        ['dia', 'stone', 'other'].forEach(kind => form.querySelectorAll('.js-' + kind + '-body tr').forEach(row => {
            const weight = n((row.querySelector('.js-' + kind + '-weight') || {}).value);
            const rate = n((row.querySelector('.js-' + kind + '-rate') || row.querySelector('.js-other-price') || {}).value);
            const total = row.querySelector('.js-' + kind + '-total');
            if (total) total.value = (kind === 'other' ? rate : weight * rate).toFixed(2);
        }));
        let diamond = 0, stone = 0, other = 0;
        form.querySelectorAll('.js-dia-weight').forEach(input => diamond += n(input.value));
        form.querySelectorAll('.js-stone-weight').forEach(input => stone += n(input.value));
        form.querySelectorAll('.js-other-weight').forEach(input => other += n(input.value));
        const gross = n(form.querySelector('.js-gross-weight').value);
        const purityOption = form.querySelector('.js-purity-select').selectedOptions[0];
        const purity = n(purityOption ? purityOption.dataset.percent : 0);
        const net = gross - (diamond + stone) * 0.2 - other;
        const safeNet = Math.max(net, 0);
        const set = (selector, value) => { const input = form.querySelector(selector); if (input) input.value = value; };
        set('.js-net-weight', net.toFixed(3));
        set('.js-pure-weight', (safeNet * purity / 100).toFixed(3));
        set('.js-gold-total', (safeNet * n(form.querySelector('.js-gold-rate').value)).toFixed(2));
        set('.js-labour-total', (safeNet * n(form.querySelector('.js-labour-rate').value)).toFixed(2));
        const wastage = n(form.querySelector('.js-wastage-percent').value);
        const wastageText = form.querySelector('.js-wastage-weight-text');
        if (wastageText) wastageText.textContent = (safeNet * wastage / 100).toFixed(3);
        set('.js-pure-wastage-weight', (safeNet * purity / 100 * wastage / 100).toFixed(3));
    }

    form.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.classList.contains('js-add-row')) {
            const kind = button.dataset.kind;
            form.querySelector('.js-' + kind + '-body').insertAdjacentHTML('beforeend', addRow(kind));
            if (kind === 'dia') syncSelectors();
            initSelects();
        } else if (button.classList.contains('js-remove-row')) {
            const row = button.closest('tr');
            const body = row && row.parentElement;
            if (body && body.children.length > 1) {
                const isDiamond = !!row.querySelector('.js-diamond-balance-select');
                row.remove();
                if (isDiamond) syncSelectors();
            }
        }
        recalc();
    });

    form.addEventListener('change', event => {
        const diamond = event.target instanceof Element ? event.target.closest('.js-diamond-balance-select') : null;
        if (diamond) updateDiamond(diamond);
        const stone = event.target instanceof Element ? event.target.closest('.js-stone-inventory-select') : null;
        if (stone && stone.value) {
            const option = stone.selectedOptions[0];
            const row = stone.closest('tr');
            row.querySelector('[name="stone_type[]"]').value = option.dataset.description || option.textContent.trim();
            row.querySelector('.js-stone-rate').value = n(option.dataset.rate).toFixed(2);
        }
        recalc();
    });
    if (window.jQuery) {
        window.jQuery(form).on('select2:select select2:unselect', '.js-diamond-balance-select', function () {
            updateDiamond(this);
        });
    }
    form.addEventListener('input', event => {
        if (event.target.matches('.js-dia-name')) event.target.dataset.edited = '1';
        recalc();
    });
    initSelects();
    recalc();

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const button = form.querySelector('[type="submit"]');
        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (result.csrf && result.csrf.name && result.csrf.hash) {
                const token = form.querySelector('[name="' + CSS.escape(result.csrf.name) + '"]');
                if (token) token.value = result.csrf.hash;
            }
            if (!response.ok || result.status !== 'ok') throw new Error(result.message || 'Unable to save the receipt.');
            if (window.Swal) await window.Swal.fire({ icon: 'success', title: 'Completed', text: result.message || 'Finished jewellery received.' });
            window.location.href = <?= json_encode(site_url('admin/orders/' . $order['id'])) ?>;
        } catch (error) {
            if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Unable to complete receipt', text: error instanceof Error ? error.message : 'Unable to save the receipt.' });
            else alert(error instanceof Error ? error.message : 'Unable to save the receipt.');
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
})();
</script>
<?= $this->endSection() ?>
