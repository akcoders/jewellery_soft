<?php
$row = $item ?? [];
?>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Diamond Type <span class="text-danger">*</span></label>
        <input type="text" name="diamond_type" class="form-control" required value="<?= esc((string) old('diamond_type', (string) ($row['diamond_type'] ?? ''))) ?>" placeholder="Round / Pan / Baguette / Polki / Rose Cut">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Shape</label>
        <input type="text" name="shape" class="form-control" value="<?= esc((string) old('shape', (string) ($row['shape'] ?? ''))) ?>" placeholder="Round / Square / Rectangle">
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Chalni From</label>
        <input type="text" name="chalni_from" class="form-control" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) old('chalni_from', (string) ($row['chalni_from'] ?? ''))) ?>">
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Chalni To</label>
        <input type="text" name="chalni_to" class="form-control" inputmode="numeric" pattern="[0-9]*" value="<?= esc((string) old('chalni_to', (string) ($row['chalni_to'] ?? ''))) ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Color</label>
        <input type="text" name="color" class="form-control" value="<?= esc((string) old('color', (string) ($row['color'] ?? ''))) ?>" placeholder="D / E / F / Mix / NA">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Clarity</label>
        <input type="text" name="clarity" class="form-control" value="<?= esc((string) old('clarity', (string) ($row['clarity'] ?? ''))) ?>" placeholder="VVS1 / VS2 / SI1 / Mix / NA">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Cut</label>
        <input type="text" name="cut" class="form-control" value="<?= esc((string) old('cut', (string) ($row['cut'] ?? ''))) ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Remarks</label>
        <input type="text" name="remarks" class="form-control" value="<?= esc((string) old('remarks', (string) ($row['remarks'] ?? ''))) ?>">
    </div>
</div>
