<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Common Issuement</h4>
    <a href="<?= site_url('admin/issuements') ?>" class="btn btn-outline-primary">Back</a>
</div>

<form method="post" action="<?= site_url('admin/issuements') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Issue Date <span class="text-danger">*</span></label>
                    <input type="date" name="issue_date" class="form-control" required value="<?= esc((string) old('issue_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Order <span class="text-danger">*</span></label>
                    <select name="order_id" id="order_id" class="form-select js-select2" required>
                        <option value="">Select order</option>
                        <?php foreach (($orders ?? []) as $order): ?>
                            <option
                                value="<?= (int) $order['id'] ?>"
                                data-karigar-id="<?= (int) ($order['assigned_karigar_id'] ?? 0) ?>"
                                data-gold-budget="<?= esc(number_format((float) ($order['gold_budget_gm'] ?? 0), 3, '.', '')) ?>"
                                data-diamond-budget="<?= esc(number_format((float) ($order['diamond_budget_cts'] ?? 0), 3, '.', '')) ?>"
                                <?= (string) old('order_id') === (string) $order['id'] ? 'selected' : '' ?>
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
                            <option value="<?= (int) $karigar['id'] ?>" <?= (string) old('karigar_id') === (string) $karigar['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $karigar['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select name="location_id" class="form-select" required>
                        <option value="">Select warehouse</option>
                        <?php foreach (($locations ?? []) as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (string) old('location_id') === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Purpose <span class="text-danger">*</span></label>
                    <input type="text" name="purpose" id="purpose" class="form-control" required value="<?= esc((string) old('purpose', 'Jobwork')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Order Budget</label>
                    <div id="order_budget" class="form-control bg-light" style="height:auto;">Select order to view budget.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc((string) old('notes')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Attachment <span class="text-danger">*</span></label>
                    <input type="file" name="attachment" class="form-control" required accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <small class="text-muted">One attachment will be linked with all generated vouchers.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="fe fe-circle me-1"></i>Gold Lines</h6>
            <button type="button" class="btn btn-sm btn-primary" id="add-gold-line"><i class="fe fe-plus"></i> Add Gold</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:280px;">Gold Item</th>
                            <th style="min-width:150px;">Weight (gm)</th>
                            <th style="min-width:150px;">Rate / gm</th>
                            <th style="min-width:150px;">Line Value</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="gold-lines-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="fas fa-gem me-1"></i>Diamond Lines</h6>
            <button type="button" class="btn btn-sm btn-primary" id="add-diamond-line"><i class="fe fe-plus"></i> Add Diamond</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:280px;">Diamond Item</th>
                            <th style="min-width:120px;">PCS</th>
                            <th style="min-width:120px;">CTS</th>
                            <th style="min-width:140px;">Rate / cts</th>
                            <th style="min-width:150px;">Line Value</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="diamond-lines-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="fe fe-disc me-1"></i>Stone Lines</h6>
            <button type="button" class="btn btn-sm btn-primary" id="add-stone-line"><i class="fe fe-plus"></i> Add Stone</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:280px;">Stone Item</th>
                            <th style="min-width:120px;">PCS</th>
                            <th style="min-width:120px;">Qty</th>
                            <th style="min-width:140px;">Rate</th>
                            <th style="min-width:150px;">Line Value</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="stone-lines-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary">Save Common Issuement</button>
    </div>
</form>

<template id="gold-line-template">
    <tr>
        <td>
            <select name="gold_item_id[]" class="form-select gold-item">
                <option value="">Select gold item</option>
                <?php foreach (($goldItems ?? []) as $item): ?>
                    <?php
                    $purity = (string) (($item['master_purity_code'] ?? $item['purity_code'] ?? '-') ?: '-');
                    $color = (string) (($item['master_color_name'] ?? $item['color_name'] ?? '-') ?: '-');
                    $form = (string) (($item['form_type'] ?? '-') ?: '-');
                    ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-default-rate="<?= esc(number_format((float) ($item['avg_cost_per_gm'] ?? 0), 2, '.', '')) ?>"
                    >
                        <?= esc(trim($purity . ' / ' . $color . ' / ' . $form)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.001" min="0" name="gold_weight_gm[]" class="form-control gold-weight"></td>
        <td><input type="number" step="0.01" min="0" name="gold_rate_per_gm[]" class="form-control gold-rate"></td>
        <td><input type="text" class="form-control gold-value" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button></td>
    </tr>
</template>

<template id="diamond-line-template">
    <tr>
        <td>
            <select name="diamond_item_id[]" class="form-select diamond-item">
                <option value="">Select diamond item</option>
                <?php foreach (($diamondItems ?? []) as $item): ?>
                    <?php
                    $label = (string) ($item['diamond_type'] ?? '-');
                    $shape = (string) ($item['shape'] ?? '');
                    $chalni = ((string) ($item['chalni_from'] ?? '') !== '' || (string) ($item['chalni_to'] ?? '') !== '')
                        ? ('CH ' . (string) ($item['chalni_from'] ?? '') . '-' . (string) ($item['chalni_to'] ?? ''))
                        : 'NA';
                    $grade = trim(($shape !== '' ? ($shape . ' / ') : '') . $chalni . ' / ' . (string) ($item['color'] ?? '-') . ' / ' . (string) ($item['clarity'] ?? '-'));
                    ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-default-rate="<?= esc(number_format((float) ($item['avg_cost_per_carat'] ?? 0), 2, '.', '')) ?>"
                    >
                        <?= esc($label . ' (' . $grade . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.001" min="0" name="diamond_pcs[]" class="form-control diamond-pcs"></td>
        <td><input type="number" step="0.001" min="0" name="diamond_carat[]" class="form-control diamond-carat"></td>
        <td><input type="number" step="0.01" min="0" name="diamond_rate_per_carat[]" class="form-control diamond-rate"></td>
        <td><input type="text" class="form-control diamond-value" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button></td>
    </tr>
</template>

<template id="stone-line-template">
    <tr>
        <td>
            <select name="stone_item_id[]" class="form-select stone-item">
                <option value="">Select stone item</option>
                <?php foreach (($stoneItems ?? []) as $item): ?>
                    <?php $label = trim((string) $item['product_name'] . (((string) ($item['stone_type'] ?? '') !== '') ? (' / ' . (string) $item['stone_type']) : '')); ?>
                    <option value="<?= (int) $item['id'] ?>" data-default-rate="<?= esc(number_format((float) ($item['default_rate'] ?? 0), 2, '.', '')) ?>"><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.001" min="0" name="stone_pcs[]" class="form-control stone-pcs"></td>
        <td><input type="number" step="0.001" min="0" name="stone_qty[]" class="form-control stone-qty"></td>
        <td><input type="number" step="0.01" min="0" name="stone_rate[]" class="form-control stone-rate"></td>
        <td><input type="text" class="form-control stone-value" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button></td>
    </tr>
</template>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    function num(v) {
        const n = parseFloat(String(v || '0'));
        return Number.isFinite(n) ? n : 0;
    }

    function recalcRow(row, qtySelector, rateSelector, outSelector) {
        const qty = num((row.querySelector(qtySelector) || {}).value);
        const rate = num((row.querySelector(rateSelector) || {}).value);
        const out = row.querySelector(outSelector);
        if (out) {
            out.value = rate > 0 ? (qty * rate).toFixed(2) : '';
        }
    }

    function bindCommonRow(row, qtySelector, rateSelector, outSelector) {
        [qtySelector, rateSelector].forEach(function (selector) {
            const el = row.querySelector(selector);
            if (el) {
                el.addEventListener('input', function () {
                    recalcRow(row, qtySelector, rateSelector, outSelector);
                });
            }
        });

        const remove = row.querySelector('.remove-line');
        if (remove) {
            remove.addEventListener('click', function () {
                row.remove();
            });
        }

        recalcRow(row, qtySelector, rateSelector, outSelector);
    }

    function addRow(bodyId, tplId, binder) {
        const body = document.getElementById(bodyId);
        const tpl = document.getElementById(tplId);
        if (!body || !tpl) return;
        const fragment = tpl.content.cloneNode(true);
        const row = fragment.querySelector('tr');
        if (row && binder) {
            binder(row);
        }
        body.appendChild(fragment);
    }

    const orderSelect = document.getElementById('order_id');
    const karigarSelect = document.getElementById('karigar_id');
    const budgetEl = document.getElementById('order_budget');

    function refreshOrderInfo() {
        if (!orderSelect || !budgetEl) return;
        const selected = orderSelect.options[orderSelect.selectedIndex];
        if (!selected || !selected.value) {
            budgetEl.textContent = 'Select order to view budget.';
            return;
        }

        const karigarId = selected.getAttribute('data-karigar-id') || '';
        const goldBudget = num(selected.getAttribute('data-gold-budget') || '0').toFixed(3);
        const diamondBudget = num(selected.getAttribute('data-diamond-budget') || '0').toFixed(3);

        if (karigarSelect && karigarId) {
            karigarSelect.value = karigarId;
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(karigarSelect).trigger('change.select2');
            }
        }

        budgetEl.textContent = 'Gold Budget: ' + goldBudget + ' gm | Diamond Budget: ' + diamondBudget + ' cts';
    }

    document.getElementById('add-gold-line')?.addEventListener('click', function () {
        addRow('gold-lines-body', 'gold-line-template', function (row) {
            bindCommonRow(row, '.gold-weight', '.gold-rate', '.gold-value');
            const item = row.querySelector('.gold-item');
            const rate = row.querySelector('.gold-rate');
            if (item && rate) {
                item.addEventListener('change', function () {
                    const opt = item.options[item.selectedIndex];
                    if (opt && (rate.value || '').trim() === '') {
                        rate.value = opt.getAttribute('data-default-rate') || '';
                        recalcRow(row, '.gold-weight', '.gold-rate', '.gold-value');
                    }
                });
            }
        });
    });

    document.getElementById('add-diamond-line')?.addEventListener('click', function () {
        addRow('diamond-lines-body', 'diamond-line-template', function (row) {
            bindCommonRow(row, '.diamond-carat', '.diamond-rate', '.diamond-value');
            const item = row.querySelector('.diamond-item');
            const rate = row.querySelector('.diamond-rate');
            if (item && rate) {
                item.addEventListener('change', function () {
                    const opt = item.options[item.selectedIndex];
                    if (opt && (rate.value || '').trim() === '') {
                        rate.value = opt.getAttribute('data-default-rate') || '';
                        recalcRow(row, '.diamond-carat', '.diamond-rate', '.diamond-value');
                    }
                });
            }
        });
    });

    document.getElementById('add-stone-line')?.addEventListener('click', function () {
        addRow('stone-lines-body', 'stone-line-template', function (row) {
            bindCommonRow(row, '.stone-qty', '.stone-rate', '.stone-value');
            const item = row.querySelector('.stone-item');
            const rate = row.querySelector('.stone-rate');
            if (item && rate) {
                item.addEventListener('change', function () {
                    const opt = item.options[item.selectedIndex];
                    if (opt && (rate.value || '').trim() === '') {
                        rate.value = opt.getAttribute('data-default-rate') || '';
                        recalcRow(row, '.stone-qty', '.stone-rate', '.stone-value');
                    }
                });
            }
        });
    });

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        window.jQuery('.js-select2').select2({ width: '100%' });
    }

    if (orderSelect) {
        orderSelect.addEventListener('change', refreshOrderInfo);
    }

    refreshOrderInfo();
})();
</script>
<?= $this->endSection() ?>
