<?php
$oldIssueLines = old('issue_line_id');
$rows = [];
if (is_array($oldIssueLines)) {
    $oldPcs = (array) old('pcs');
    $oldCarat = (array) old('carat');
    $oldRates = (array) old('rate_per_carat');
    $max = max(count($oldIssueLines), count($oldPcs), count($oldCarat), count($oldRates));
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            'issue_line_id' => (string) ($oldIssueLines[$i] ?? ''),
            'pcs' => (string) ($oldPcs[$i] ?? ''),
            'carat' => (string) ($oldCarat[$i] ?? ''),
            'rate_per_carat' => (string) ($oldRates[$i] ?? ''),
        ];
    }
} elseif (($lines ?? []) !== []) {
    foreach ($lines as $line) {
        $rows[] = [
            'issue_line_id' => (string) ($line['issue_line_id'] ?? ''),
            'pcs' => (string) ($line['pcs'] ?? ''),
            'carat' => (string) ($line['carat'] ?? ''),
            'rate_per_carat' => (string) ($line['rate_per_carat'] ?? ''),
        ];
    }
}
if ($rows === []) {
    $rows[] = ['issue_line_id' => '', 'pcs' => '', 'carat' => '', 'rate_per_carat' => ''];
}

$returnDate = old('return_date', (string) ($return['return_date'] ?? date('Y-m-d')));
$selectedIssueId = old('issue_id', (string) ($return['issue_id'] ?? (string) ($preselectedIssueId ?? '')));
$returnFrom = old('return_from', (string) ($return['return_from'] ?? ''));
$purpose = old('purpose', (string) ($return['purpose'] ?? ''));
$notes = old('notes', (string) ($return['notes'] ?? ''));
$existingAttachmentName = (string) ($return['attachment_name'] ?? '');
$existingAttachmentPath = (string) ($return['attachment_path'] ?? '');
$attachmentRequired = $existingAttachmentPath === '';

$lineOptions = [];
foreach (($issueLines ?? []) as $option) {
    $lineOptions[(int) ($option['issue_line_id'] ?? 0)] = $option;
}

$renderOptions = static function (string $selected = '') use ($lineOptions): string {
    $html = '<option value="">Select exact issued bag / size</option>';
    foreach ($lineOptions as $option) {
        $id = (int) ($option['issue_line_id'] ?? 0);
        $label = (string) ($option['label'] ?? ('Issue line #' . $id));
        $label .= sprintf(
            ' | Available: %s pcs / %s cts',
            number_format((float) ($option['available_pcs'] ?? 0), 0),
            number_format((float) ($option['available_cts'] ?? 0), 3)
        );
        $html .= '<option value="' . $id . '"'
            . ' data-issue-id="' . (int) ($option['issue_id'] ?? 0) . '"'
            . ' data-pcs="' . esc(number_format((float) ($option['available_pcs'] ?? 0), 3, '.', '')) . '"'
            . ' data-cts="' . esc(number_format((float) ($option['available_cts'] ?? 0), 3, '.', '')) . '"'
            . ' data-rate="' . esc(number_format((float) ($option['rate_per_carat'] ?? 0), 2, '.', '')) . '"'
            . ((string) $id === $selected ? ' selected' : '')
            . '>' . esc($label) . '</option>';
    }
    return $html;
};
?>

<div class="card erp-record-card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Return Date <span class="text-danger">*</span></label>
                <input type="date" name="return_date" class="form-control" required value="<?= esc($returnDate) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Issue Voucher <span class="text-danger">*</span></label>
                <select name="issue_id" id="return-issue-select" class="form-select select2" required>
                    <option value="">Select issue voucher</option>
                    <?php foreach (($issues ?? []) as $issue): ?>
                        <?php
                        $party = trim((string) (($issue['karigar_name'] ?? '') ?: ($issue['issue_to'] ?? '')));
                        $label = (string) (($issue['voucher_no'] ?? '') ?: ('ISS#' . (int) $issue['id']));
                        $label .= ' | ' . (string) ($issue['issue_date'] ?? '-');
                        if ($party !== '') { $label .= ' | ' . $party; }
                        ?>
                        <option value="<?= (int) $issue['id'] ?>" data-return-from="<?= esc($party) ?>" <?= (string) $selectedIssueId === (string) $issue['id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Return From</label>
                <input type="text" id="return-from-input" name="return_from" class="form-control" value="<?= esc($returnFrom) ?>" placeholder="Karigar / department">
            </div>
            <div class="col-md-4">
                <label class="form-label">Purpose</label>
                <input type="text" name="purpose" class="form-control" value="<?= esc($purpose) ?>" placeholder="Unused diamond return">
            </div>
            <div class="col-md-4">
                <label class="form-label">Attachment <span class="text-danger">*</span></label>
                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" <?= $attachmentRequired ? 'required' : '' ?>>
                <?php if (! $attachmentRequired): ?><small>Current: <a href="<?= base_url($existingAttachmentPath) ?>" target="_blank"><?= esc($existingAttachmentName ?: 'Open') ?></a></small><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc($notes) ?>">
            </div>
        </div>
    </div>
</div>

<div class="card erp-data-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-1">Exact Bag Returns</h6>
            <small class="text-muted">Return against the original issue row so shape, size and order trail stay intact.</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="add-return-line"><i class="fe fe-plus"></i> Add Line</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead><tr><th style="min-width:440px">Issued Bag / Shape / Size / Order</th><th>PCS *</th><th>CTS *</th><th>Rate/CTS</th><th>Value</th><th></th></tr></thead>
                <tbody id="return-lines-body">
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><select name="issue_line_id[]" class="form-select issue-line-select" required><?= $renderOptions((string) $row['issue_line_id']) ?></select><small class="availability text-muted"></small></td>
                            <td><input type="number" step="1" min="1" name="pcs[]" class="form-control line-pcs" required value="<?= esc($row['pcs']) ?>"></td>
                            <td><input type="number" step="0.001" min="0.001" name="carat[]" class="form-control line-carat" required value="<?= esc($row['carat']) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="rate_per_carat[]" class="form-control line-rate" value="<?= esc($row['rate_per_carat']) ?>"></td>
                            <td><input class="form-control line-value" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary mb-4">Save Exact Return</button>

<template id="return-line-template"><tr>
    <td><select name="issue_line_id[]" class="form-select issue-line-select" required><?= $renderOptions('') ?></select><small class="availability text-muted"></small></td>
    <td><input type="number" step="1" min="1" name="pcs[]" class="form-control line-pcs" required></td>
    <td><input type="number" step="0.001" min="0.001" name="carat[]" class="form-control line-carat" required></td>
    <td><input type="number" step="0.01" min="0" name="rate_per_carat[]" class="form-control line-rate"></td>
    <td><input class="form-control line-value" readonly></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fe fe-trash-2"></i></button></td>
</tr></template>

<script>
(() => {
    const body = document.getElementById('return-lines-body');
    const issue = document.getElementById('return-issue-select');
    const from = document.getElementById('return-from-input');
    const template = document.getElementById('return-line-template');
    const add = document.getElementById('add-return-line');
    if (!body || !issue || !template || !add) return;

    const refreshRow = row => {
        const select = row.querySelector('.issue-line-select');
        const selected = select?.selectedOptions[0];
        const pcs = Number(row.querySelector('.line-pcs')?.value || 0);
        const cts = Number(row.querySelector('.line-carat')?.value || 0);
        const rateInput = row.querySelector('.line-rate');
        if (selected && rateInput && !rateInput.value && Number(selected.dataset.rate || 0) > 0) rateInput.value = selected.dataset.rate;
        const rate = Number(rateInput?.value || 0);
        const value = row.querySelector('.line-value');
        if (value) value.value = (cts * rate).toFixed(2);
        const availability = row.querySelector('.availability');
        if (availability) availability.textContent = selected?.value ? `Available ${Number(selected.dataset.pcs || 0).toFixed(0)} pcs / ${Number(selected.dataset.cts || 0).toFixed(3)} cts` : '';
        if (availability) availability.classList.toggle('text-danger', pcs > Number(selected?.dataset.pcs || 0) || cts > Number(selected?.dataset.cts || 0));
    };

    const filterLines = () => {
        const issueId = issue.value;
        body.querySelectorAll('.issue-line-select').forEach(select => {
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                option.hidden = option.dataset.issueId !== issueId;
                option.disabled = option.hidden;
            });
            if (select.value && select.selectedOptions[0]?.dataset.issueId !== issueId) select.value = '';
            refreshRow(select.closest('tr'));
        });
        const selectedIssue = issue.selectedOptions[0];
        if (from && !from.value && selectedIssue?.dataset.returnFrom) from.value = selectedIssue.dataset.returnFrom;
    };

    add.addEventListener('click', () => { body.append(template.content.cloneNode(true)); filterLines(); });
    issue.addEventListener('change', filterLines);
    body.addEventListener('input', event => { const row = event.target.closest('tr'); if (row) refreshRow(row); });
    body.addEventListener('change', event => { const row = event.target.closest('tr'); if (row) refreshRow(row); });
    body.addEventListener('click', event => { const button = event.target.closest('.remove-line'); if (button && body.rows.length > 1) button.closest('tr').remove(); });
    filterLines();
})();
</script>
