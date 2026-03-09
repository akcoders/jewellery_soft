<?php
$p = $row ?? [];
?>
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Purity Code <span class="text-danger">*</span></label>
        <input type="text" name="purity_code" class="form-control" required maxlength="20" value="<?= esc((string) old('purity_code', (string) ($p['purity_code'] ?? ''))) ?>" placeholder="22K / 18K / 24K">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Purity Percent <span class="text-danger">*</span></label>
        <input type="number" step="0.001" min="0.001" max="100" name="purity_percent" class="form-control" required value="<?= esc((string) old('purity_percent', (string) ($p['purity_percent'] ?? ''))) ?>" placeholder="91.666">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Default Color</label>
        <input type="text" name="color_name" class="form-control" maxlength="30" value="<?= esc((string) old('color_name', (string) ($p['color_name'] ?? ''))) ?>" placeholder="YG / WG / RG">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label d-block">Status</label>
        <?php $isActive = (string) old('is_active', (string) ($p['is_active'] ?? '1')); ?>
        <select name="is_active" class="form-select">
            <option value="1" <?= $isActive === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $isActive === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
</div>

