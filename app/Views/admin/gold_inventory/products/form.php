<?php
$p = $row ?? [];
$selectedPurity = (string) old('gold_purity_id', (string) ($p['gold_purity_id'] ?? ''));
?>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Gold Purity <span class="text-danger">*</span></label>
        <select name="gold_purity_id" class="form-select" required>
            <option value="">Select purity</option>
            <?php foreach (($purities ?? []) as $purity): ?>
                <option
                    value="<?= (int) $purity['id'] ?>"
                    data-color="<?= esc((string) ($purity['color_name'] ?? '')) ?>"
                    data-percent="<?= esc((string) ($purity['purity_percent'] ?? '0')) ?>"
                    <?= $selectedPurity === (string) $purity['id'] ? 'selected' : '' ?>
                >
                    <?= esc((string) $purity['purity_code']) ?> (<?= number_format((float) $purity['purity_percent'], 3) ?>%)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Color</label>
        <input type="text" name="color_name" id="color_name" class="form-control" maxlength="30" value="<?= esc((string) old('color_name', (string) ($p['color_name'] ?? ''))) ?>" placeholder="YG / WG / RG">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Purity Percent (Auto)</label>
        <input type="text" id="purity_percent_display" class="form-control" value="<?= esc((string) old('purity_percent_display', (string) ($p['purity_percent'] ?? ''))) ?>" readonly>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Form/Product Type <span class="text-danger">*</span></label>
        <input type="text" name="form_type" class="form-control" required maxlength="30" value="<?= esc((string) old('form_type', (string) ($p['form_type'] ?? ''))) ?>" placeholder="Bar / Grain / Ornament / Scrap">
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label">Remarks</label>
        <input type="text" name="remarks" class="form-control" maxlength="255" value="<?= esc((string) old('remarks', (string) ($p['remarks'] ?? ''))) ?>">
    </div>
</div>

<script>
    (function () {
        const purity = document.querySelector('select[name="gold_purity_id"]');
        const color = document.getElementById('color_name');
        const percent = document.getElementById('purity_percent_display');
        if (!purity || !color || !percent) {
            return;
        }

        function syncFromPurity(forceColor) {
            const selected = purity.options[purity.selectedIndex];
            if (!selected || !selected.value) {
                percent.value = '';
                return;
            }

            percent.value = selected.getAttribute('data-percent') || '';
            if (forceColor || color.value.trim() === '') {
                color.value = selected.getAttribute('data-color') || '';
            }
        }

        purity.addEventListener('change', function () {
            syncFromPurity(true);
        });

        syncFromPurity(false);
    })();
</script>

