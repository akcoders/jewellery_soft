<?php
$oldItemIds = old('item_id');
$rows = [];

if (is_array($oldItemIds)) {
    $oldPurityIds = (array) old('gold_purity_id');
    $oldColors = (array) old('color_name');
    $oldForms = (array) old('form_type');
    $oldWeights = (array) old('weight_gm');
    $oldRates = (array) old('rate_per_gm');

    $max = max(count($oldItemIds), count($oldPurityIds), count($oldColors), count($oldForms), count($oldWeights), count($oldRates));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'item_id' => (string) ($oldItemIds[$i] ?? ''),
            'gold_purity_id' => (string) ($oldPurityIds[$i] ?? ''),
            'color_name' => (string) ($oldColors[$i] ?? ''),
            'form_type' => (string) ($oldForms[$i] ?? ''),
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
        'weight_gm' => '',
        'rate_per_gm' => '',
    ];
}

$returnDate = old('return_date', (string) ($return['return_date'] ?? date('Y-m-d')));
$selectedOrderId = old('order_id', (string) ($return['order_id'] ?? (string) ($preselectedOrderId ?? '')));
$selectedIssueId = old('issue_id', (string) ($return['issue_id'] ?? (string) ($preselectedIssueId ?? '')));
$returnFrom = old('return_from', (string) ($return['return_from'] ?? ''));
$purpose = old('purpose', (string) ($return['purpose'] ?? ''));
$notes = old('notes', (string) ($return['notes'] ?? ''));
$locationId = old('location_id', (string) ($return['location_id'] ?? ''));
$existingAttachmentName = (string) ($return['attachment_name'] ?? '');
$existingAttachmentPath = (string) ($return['attachment_path'] ?? '');
$attachmentRequired = $existingAttachmentPath === '';
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Return Date <span class="text-danger">*</span></label>
                <input type="date" name="return_date" class="form-control" required value="<?= esc((string) $returnDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Order Reference <span class="text-danger">*</span></label>
                <select name="order_id" id="return-order-select" class="form-select" required>
                    <option value="">Select order</option>
                    <?php foreach (($orders ?? []) as $order): ?>
                        <option value="<?= (int) $order['id'] ?>" <?= (string) $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $order['order_no']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Issue Reference <span class="text-danger">*</span></label>
                <select name="issue_id" id="return-issue-select" class="form-select" required>
                    <option value="">Select issue voucher</option>
                    <?php foreach (($issues ?? []) as $issue): ?>
                        <?php
                        $issueVoucher = (string) ($issue['voucher_no'] ?? '');
                        if ($issueVoucher === '') {
                            $issueVoucher = 'ISS#' . (int) ($issue['id'] ?? 0);
                        }
                        $issueParty = (string) (($issue['karigar_name'] ?? '') !== '' ? $issue['karigar_name'] : ($issue['issue_to'] ?? ''));
                        $issueLabel = $issueVoucher . ' | ' . (string) ($issue['issue_date'] ?? '-') . ' | ' . ($issueParty !== '' ? $issueParty : '-');
                        ?>
                        <option
                            value="<?= (int) $issue['id'] ?>"
                            data-order-id="<?= (int) ($issue['order_id'] ?? 0) ?>"
                            data-return-from="<?= esc((string) ($issue['issue_to'] ?: ($issue['karigar_name'] ?? ''))) ?>"
                            <?= (string) $selectedIssueId === (string) $issue['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($issueLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Return From</label>
                <input type="text" id="return-from-input" name="return_from" class="form-control" value="<?= esc((string) $returnFrom) ?>" placeholder="Karigar / Department">
            </div>
            <div class="col-md-3">
                <label class="form-label">Purpose</label>
                <input type="text" name="purpose" class="form-control" value="<?= esc((string) $purpose) ?>" placeholder="return / receive">
            </div>
            <div class="col-md-4">
                <label class="form-label">Return Location <span class="text-danger">*</span></label>
                <select name="location_id" class="form-select" required>
                    <option value="">Select location</option>
                    <?php foreach (($locations ?? []) as $loc): ?>
                        <option value="<?= (int) $loc['id'] ?>" <?= (string) $locationId === (string) $loc['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) $notes) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Attachment <span class="text-danger">*</span></label>
                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" <?= $attachmentRequired ? 'required' : '' ?>>
                <?php if (! $attachmentRequired): ?>
                    <div class="small mt-1">Current: <a href="<?= base_url((string) $existingAttachmentPath) ?>" target="_blank"><?= esc($existingAttachmentName !== '' ? $existingAttachmentName : 'Open') ?></a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Return Lines</h6>
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
                                            data-default-rate="<?= esc(number_format((float) ($item['avg_cost_per_gm'] ?? 0), 2, '.', '')) ?>"
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

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Return</button>
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
                        data-default-rate="<?= esc(number_format((float) ($item['avg_cost_per_gm'] ?? 0), 2, '.', '')) ?>"
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
        const orderSelect = document.getElementById('return-order-select');
        const issueSelect = document.getElementById('return-issue-select');
        const returnFromInput = document.getElementById('return-from-input');
        if (!body || !addBtn || !tpl) {
            return;
        }

        function filterIssuesByOrder() {
            if (!orderSelect || !issueSelect) {
                return;
            }
            const orderId = String(orderSelect.value || '');
            let visibleCount = 0;

            Array.prototype.forEach.call(issueSelect.options, function(opt, idx) {
                if (idx === 0) {
                    opt.hidden = false;
                    return;
                }
                const optOrderId = String(opt.getAttribute('data-order-id') || '');
                const match = orderId !== '' && optOrderId === orderId;
                opt.hidden = !match;
                opt.disabled = !match;
                if (match) {
                    visibleCount++;
                }
            });

            if (issueSelect.selectedOptions.length > 0) {
                const selected = issueSelect.selectedOptions[0];
                if (selected && selected.hidden) {
                    issueSelect.value = '';
                }
            }

            if (orderId === '') {
                issueSelect.value = '';
                issueSelect.disabled = true;
            } else {
                issueSelect.disabled = visibleCount === 0;
            }
        }

        function applyIssueDerivedValues() {
            if (!issueSelect || !returnFromInput) {
                return;
            }
            const selected = issueSelect.options[issueSelect.selectedIndex];
            if (!selected || !selected.value) {
                return;
            }
            if ((returnFromInput.value || '').trim() === '') {
                returnFromInput.value = selected.getAttribute('data-return-from') || '';
            }
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
                output.value = rate > 0 ? (weight * rate).toFixed(2) : '';
            }
            if (fineOutput) {
                fineOutput.value = purityPercent > 0 ? (weight * purityPercent / 100).toFixed(3) : '';
            }
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
                    const rate = row.querySelector('.line-rate');
                    if (purity) purity.value = selected.getAttribute('data-gold_purity_id') || '';
                    if (color) color.value = selected.getAttribute('data-color_name') || '';
                    if (form) form.value = selected.getAttribute('data-form_type') || '';
                    if (rate && ((rate.value || '').trim() === '')) {
                        rate.value = selected.getAttribute('data-default-rate') || '';
                    }
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
                        row.querySelectorAll('input').forEach(function(input) { input.value = ''; });
                        row.querySelectorAll('select').forEach(function(select) { select.value = ''; });
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

        body.querySelectorAll('tr').forEach(function(row) { bindRow(row); });
        if (orderSelect) {
            orderSelect.addEventListener('change', function() {
                filterIssuesByOrder();
                applyIssueDerivedValues();
            });
        }
        if (issueSelect) {
            issueSelect.addEventListener('change', applyIssueDerivedValues);
        }
        filterIssuesByOrder();
        applyIssueDerivedValues();
    })();
</script>
