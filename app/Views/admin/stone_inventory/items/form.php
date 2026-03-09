<?php
$row = $item ?? [];
?>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Product Name <span class="text-danger">*</span></label>
        <input type="text" name="product_name" class="form-control" required value="<?= esc((string) old('product_name', (string) ($row['product_name'] ?? ''))) ?>" placeholder="Ruby / Emerald / CZ / Coral">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Stone Type</label>
        <input type="text" name="stone_type" class="form-control" value="<?= esc((string) old('stone_type', (string) ($row['stone_type'] ?? ''))) ?>" placeholder="Natural / Synthetic / Semi-precious">
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Default Rate</label>
        <input type="number" step="0.01" min="0" name="default_rate" class="form-control" value="<?= esc((string) old('default_rate', (string) ($row['default_rate'] ?? '0'))) ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Remarks</label>
        <input type="text" name="remarks" class="form-control" value="<?= esc((string) old('remarks', (string) ($row['remarks'] ?? ''))) ?>">
    </div>
</div>
