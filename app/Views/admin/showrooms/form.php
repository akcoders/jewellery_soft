<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Showroom Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Showroom Code</label>
                            <input type="text" name="showroom_code" class="form-control" value="<?= esc((string) old('showroom_code', $row['showroom_code'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Showroom Name</label>
                            <input type="text" name="name" class="form-control" value="<?= esc((string) old('name', $row['name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <input type="text" name="showroom_type" class="form-control" value="<?= esc((string) old('showroom_type', $row['showroom_type'] ?? 'Retail Showroom')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Manager</label>
                            <select name="manager_employee_id" class="form-select select2">
                                <option value="">Select</option>
                                <?php foreach (($employees ?? []) as $employee): ?>
                                    <option value="<?= (int) $employee['id'] ?>" <?= (int) old('manager_employee_id', $row['manager_employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $employee['full_name']) ?> (<?= esc((string) ($employee['designation_name'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warehouse Location</label>
                            <select name="warehouse_location_id" class="form-select select2">
                                <option value="">Select</option>
                                <?php foreach (($locations ?? []) as $location): ?>
                                    <option value="<?= (int) $location['id'] ?>" <?= (int) old('warehouse_location_id', $row['warehouse_location_id'] ?? 0) === (int) $location['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $location['name']) ?><?= ! empty($location['code']) ? ' (' . esc((string) $location['code']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Opening Date</label>
                            <input type="date" name="opening_date" class="form-control" value="<?= esc((string) old('opening_date', $row['opening_date'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= esc((string) old('phone', $row['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= esc((string) old('email', $row['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">GSTIN</label>
                            <input type="text" name="gstin" class="form-control" value="<?= esc((string) old('gstin', $row['gstin'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state_name" class="form-control" value="<?= esc((string) old('state_name', $row['state_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city_name" class="form-control" value="<?= esc((string) old('city_name', $row['city_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea name="address_line" class="form-control" rows="3"><?= esc((string) old('address_line', $row['address_line'] ?? '')) ?></textarea>
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
                        <label class="form-check-label" for="is_active">Active showroom</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Save</button>
                        <a href="<?= site_url('admin/showrooms') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
