<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Counter Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Showroom</label>
                            <select name="showroom_id" class="form-select select2" required>
                                <option value="">Select showroom</option>
                                <?php foreach (($showrooms ?? []) as $showroom): ?>
                                    <option value="<?= (int) $showroom['id'] ?>" <?= (int) old('showroom_id', $row['showroom_id'] ?? 0) === (int) $showroom['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $showroom['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Counter Code</label>
                            <input type="text" name="counter_code" class="form-control" value="<?= esc((string) old('counter_code', $row['counter_code'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Counter Name</label>
                            <input type="text" name="counter_name" class="form-control" value="<?= esc((string) old('counter_name', $row['counter_name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Counter Type</label>
                            <input type="text" name="counter_type" class="form-control" value="<?= esc((string) old('counter_type', $row['counter_type'] ?? 'Sales Counter')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incharge</label>
                            <select name="incharge_employee_id" class="form-select select2">
                                <option value="">Select</option>
                                <?php foreach (($employees ?? []) as $employee): ?>
                                    <option value="<?= (int) $employee['id'] ?>" <?= (int) old('incharge_employee_id', $row['incharge_employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $employee['full_name']) ?> (<?= esc((string) ($employee['designation_name'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= esc((string) old('notes', $row['notes'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Status</h5></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= old('is_active', $row['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active counter</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Save</button>
                        <a href="<?= site_url('admin/showroom-counters') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
