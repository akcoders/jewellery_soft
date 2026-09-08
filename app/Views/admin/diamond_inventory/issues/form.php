<?php
$issue = is_array($issue ?? null) ? $issue : [];
$oldBagIds = old('bag_item_id');
$rows = [];
if (is_array($oldBagIds)) {
    $oldOrders = (array) old('diamond_order_id');
    $oldPcs = (array) old('pcs');
    $oldCts = (array) old('carat');
    $oldRates = (array) old('rate_per_carat');
    foreach ($oldBagIds as $i => $bagItemId) {
        $rows[] = ['bag_item_id' => $bagItemId, 'allocation_order_id' => $oldOrders[$i] ?? '', 'pcs' => $oldPcs[$i] ?? '', 'carat' => $oldCts[$i] ?? '', 'rate_per_carat' => $oldRates[$i] ?? ''];
    }
} elseif (($lines ?? []) !== []) {
    $rows = $lines;
}
if ($rows === []) {
    $rows[] = ['bag_item_id' => '', 'allocation_order_id' => '', 'pcs' => '', 'carat' => '', 'rate_per_carat' => ''];
}
?>
<div class="card erp-form-shell mb-3"><div class="card-body"><div class="row g-3">
    <div class="col-md-3"><label class="form-label">Voucher Number *</label><input type="text" name="voucher_no" class="form-control text-uppercase" maxlength="80" required value="<?= esc((string) old('voucher_no', (string) ($issue['voucher_no'] ?? ($suggestedVoucherNo ?? '')))) ?>"></div>
    <div class="col-md-2"><label class="form-label">Issue Date *</label><input type="date" name="issue_date" class="form-control" required value="<?= esc((string) old('issue_date', (string) ($issue['issue_date'] ?? date('Y-m-d')))) ?>"></div>
    <div class="col-md-4"><label class="form-label">Karigar *</label><select name="karigar_id" class="form-select js-select2" required><option value="">Select karigar</option><?php foreach (($karigars ?? []) as $karigar): ?><option value="<?= (int) $karigar['id'] ?>" <?= (string) old('karigar_id', (string) ($issue['karigar_id'] ?? '')) === (string) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Warehouse *</label><select name="location_id" class="form-select" required><option value="">Select warehouse</option><?php foreach (($locations ?? []) as $location): ?><option value="<?= (int) $location['id'] ?>" <?= (string) old('location_id', (string) ($issue['location_id'] ?? '')) === (string) $location['id'] ? 'selected' : '' ?>><?= esc((string) $location['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Purpose *</label><input type="text" name="purpose" class="form-control" required value="<?= esc((string) old('purpose', (string) ($issue['purpose'] ?? 'Jobwork'))) ?>"></div>
    <div class="col-md-5"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" value="<?= esc((string) old('notes', (string) ($issue['notes'] ?? ''))) ?>"></div>
    <div class="col-md-4"><label class="form-label">Attachment *</label><input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" <?= empty($issue['attachment_path']) ? 'required' : '' ?>><?php if (! empty($issue['attachment_path'])): ?><a class="small" target="_blank" href="<?= base_url((string) $issue['attachment_path']) ?>">Open current attachment</a><?php endif; ?></div>
</div></div></div>

<div class="alert alert-info"><strong>Order-independent but fully traceable:</strong> an order allocation is optional. Repeat the same bag/size in separate rows when one physical bag is used for multiple orders.</div>
<div class="card erp-form-shell mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div><h6 class="mb-1">Bag-wise Issue Lines</h6><small class="text-muted">Bag, shape/size, PCS and CTS are mandatory.</small></div><button type="button" class="btn btn-sm btn-primary" id="add-issue-line"><i class="fe fe-plus me-1"></i>Add Allocation</button></div>
<div class="table-responsive"><table class="table table-bordered align-middle mb-0" data-dt-skip="true"><thead><tr><th style="min-width:360px">Bag / Shape / Size *</th><th style="min-width:260px">Order Allocation</th><th style="min-width:100px">PCS *</th><th style="min-width:110px">CTS *</th><th style="min-width:130px">Rate / CTS</th><th style="min-width:130px">Value</th><th></th></tr></thead><tbody id="issue-lines-body">
<?php foreach ($rows as $row): ?><tr>
    <td><select name="bag_item_id[]" class="form-select js-bag-item" required><option value="">Select available bag size</option><?php foreach (($bagItems ?? []) as $item): ?><?php $label = implode(' / ', array_filter([(string) ($item['bag_no'] ?? ''), (string) ($item['diamond_type'] ?? ''), (string) (($item['shape_name'] ?? '') ?: ($item['item_shape'] ?? '')), (string) (($item['size_label'] ?? '') ?: ($item['size_code'] ?? ''))])); ?><option value="<?= (int) $item['id'] ?>" data-pcs="<?= esc(number_format((float) ($item['pcs_available'] ?? 0), 0, '.', '')) ?>" data-cts="<?= esc(number_format((float) ($item['weight_cts_available'] ?? 0), 3, '.', '')) ?>" data-rate="<?= esc(number_format((float) ($item['avg_cost_per_carat'] ?? 0), 2, '.', '')) ?>" <?= (string) ($row['bag_item_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>><?= esc($label) ?> · <?= number_format((float) $item['pcs_available'], 0) ?> pcs / <?= number_format((float) $item['weight_cts_available'], 3) ?> cts</option><?php endforeach; ?></select></td>
    <td><select name="diamond_order_id[]" class="form-select"><option value="">Unallocated / general jobwork</option><?php foreach (($orders ?? []) as $order): ?><option value="<?= (int) $order['id'] ?>" <?= (string) ($row['allocation_order_id'] ?? '') === (string) $order['id'] ? 'selected' : '' ?>><?= esc((string) $order['order_no'] . (((string) ($order['order_name'] ?? '')) !== '' ? (' · ' . (string) $order['order_name']) : '')) ?></option><?php endforeach; ?></select></td>
    <td><input type="number" min="1" step="1" name="pcs[]" class="form-control js-pcs" required value="<?= esc((string) ($row['pcs'] ?? '')) ?>"></td>
    <td><input type="number" min="0.001" step="0.001" name="carat[]" class="form-control js-cts" required value="<?= esc((string) ($row['carat'] ?? '')) ?>"></td>
    <td><input type="number" min="0" step="0.01" name="rate_per_carat[]" class="form-control js-rate" value="<?= esc((string) ($row['rate_per_carat'] ?? '')) ?>"></td>
    <td><input type="text" class="form-control js-value" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove"><i class="fe fe-trash-2"></i></button></td>
</tr><?php endforeach; ?>
</tbody></table></div><div class="card-footer d-flex justify-content-between"><a href="<?= site_url('admin/diamond-inventory/bags/create') ?>" class="btn btn-outline-secondary">Prepare New Bag</a><button class="btn btn-primary">Save Diamond Issue</button></div></div>
<script>
(function () {
    const body = document.getElementById('issue-lines-body'); const add = document.getElementById('add-issue-line'); if (!body || !add) return;
    function bind(row) {
        const calc = function () { const cts = parseFloat(row.querySelector('.js-cts')?.value || 0) || 0; const rate = parseFloat(row.querySelector('.js-rate')?.value || 0) || 0; row.querySelector('.js-value').value = rate > 0 ? (cts * rate).toFixed(2) : ''; };
        row.querySelector('.js-cts')?.addEventListener('input', calc); row.querySelector('.js-rate')?.addEventListener('input', calc);
        row.querySelector('.js-bag-item')?.addEventListener('change', function (event) { const option = event.target.options[event.target.selectedIndex]; const pcs = row.querySelector('.js-pcs'); const cts = row.querySelector('.js-cts'); const rate = row.querySelector('.js-rate'); if (pcs) pcs.value = option?.value ? (option.getAttribute('data-pcs') || '') : ''; if (cts) cts.value = option?.value ? (option.getAttribute('data-cts') || '') : ''; if (rate && !rate.value) rate.value = option?.getAttribute('data-rate') || ''; calc(); });
        row.querySelector('.js-remove')?.addEventListener('click', function () { if (body.querySelectorAll('tr').length > 1) row.remove(); }); calc();
    }
    body.querySelectorAll('tr').forEach(bind); add.addEventListener('click', function () { const row = body.querySelector('tr').cloneNode(true); row.querySelectorAll('select').forEach(function (el) { el.value = ''; }); row.querySelectorAll('input').forEach(function (el) { el.value = ''; }); body.appendChild(row); bind(row); });
})();
</script>
