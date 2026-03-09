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

$returnDate = old('return_date', (string) ($return['return_date'] ?? date('Y-m-d')));
$selectedOrderId = old('order_id', (string) ($return['order_id'] ?? (string) ($preselectedOrderId ?? '')));
$selectedIssueId = old('issue_id', (string) ($return['issue_id'] ?? (string) ($preselectedIssueId ?? '')));
$returnFrom = old('return_from', (string) ($return['return_from'] ?? ''));
$purpose = old('purpose', (string) ($return['purpose'] ?? ''));
$notes = old('notes', (string) ($return['notes'] ?? ''));
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
                <input type="text" id="return-from-input" name="return_from" class="form-control" value="<?= esc((string) $returnFrom) ?>" placeholder="Karigar / Customer / Department">
            </div>
            <div class="col-md-3">
                <label class="form-label">Purpose</label>
                <input type="text" name="purpose" class="form-control" value="<?= esc((string) $purpose) ?>" placeholder="jobwork return / sale return">
            </div>
            <div class="col-md-6">
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
        <button type="button" class="btn btn-sm btn-primary" id="add-return-line"><i class="fe fe-plus"></i> Add Line</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="return-lines-table">
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
                <tbody id="return-lines-body">
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

<div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Return</button>
</div>

<template id="return-line-template">
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
        const body = document.getElementById('return-lines-body');
        const addBtn = document.getElementById('add-return-line');
        const tpl = document.getElementById('return-line-template');
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
            if (row) bindRow(row);
            body.appendChild(fragment);
        });

        body.querySelectorAll('tr').forEach(function(row) {
            bindRow(row);
        });

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
