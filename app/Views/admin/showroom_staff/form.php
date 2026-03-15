<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Showroom Staff Assignment</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" class="form-select select2" required>
                                <option value="">Select employee</option>
                                <?php foreach (($employees ?? []) as $employee): ?>
                                    <option value="<?= (int) $employee['id'] ?>" <?= (int) old('employee_id', $row['employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $employee['full_name']) ?> (<?= esc((string) ($employee['designation_name'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role Label</label>
                            <input type="text" name="role_label" class="form-control" value="<?= esc((string) old('role_label', $row['role_label'] ?? 'Staff')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective From</label>
                            <input type="date" name="effective_from" class="form-control" value="<?= esc((string) old('effective_from', $row['effective_from'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective To</label>
                            <input type="date" name="effective_to" class="form-control" value="<?= esc((string) old('effective_to', $row['effective_to'] ?? '')) ?>">
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
                        <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary" value="1" <?= old('is_primary', $row['is_primary'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_primary">Primary assignment</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= old('is_active', $row['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active assignment</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Save</button>
                        <a href="<?= site_url('admin/showroom-staff') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
