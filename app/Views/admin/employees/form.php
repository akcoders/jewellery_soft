<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $row = is_array($row ?? null) ? $row : []; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1"><?= esc($title ?? 'Employee') ?></h4>
        <p class="text-muted mb-0">Create clean employee records first. Hierarchy and KPI ownership depend on this data.</p>
    </div>
    <a href="<?= site_url('admin/employees') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/employees')) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="current_photo" value="<?= esc((string) ($row['photo_path'] ?? '')) ?>">
    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Basic Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Employee Code</label>
                            <input type="text" name="employee_code" class="form-control" maxlength="30" required
                                value="<?= esc(old('employee_code', (string) ($row['employee_code'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" maxlength="150" required
                                value="<?= esc(old('full_name', (string) ($row['full_name'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select select2" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (string) old('department_id', (string) ($row['department_id'] ?? '')) === (string) $department['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $department['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Designation</label>
                            <select name="designation_id" class="form-select select2" required>
                                <option value="">Select Designation</option>
                                <?php foreach ($designations as $designation): ?>
                                    <option value="<?= (int) $designation['id'] ?>" <?= (string) old('designation_id', (string) ($row['designation_id'] ?? '')) === (string) $designation['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $designation['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" class="form-control" maxlength="30"
                                value="<?= esc(old('mobile', (string) ($row['mobile'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" maxlength="120"
                                value="<?= esc(old('email', (string) ($row['email'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Work Location</label>
                            <input type="text" name="work_location" class="form-control" maxlength="120"
                                value="<?= esc(old('work_location', (string) ($row['work_location'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Joining Date</label>
                            <input type="date" name="joining_date" class="form-control"
                                value="<?= esc(old('joining_date', (string) ($row['joining_date'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link Admin User</label>
                            <select name="admin_user_id" class="form-select select2">
                                <option value="">No Login Link</option>
                                <?php foreach ($adminUsers as $adminUser): ?>
                                    <option value="<?= (int) $adminUser['id'] ?>" <?= (string) old('admin_user_id', (string) ($row['admin_user_id'] ?? '')) === (string) $adminUser['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $adminUser['name']) ?> (<?= esc((string) $adminUser['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Photo</label>
                            <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (! empty($row['photo_path'])): ?>
                                <div class="mt-2"><a href="<?= base_url((string) $row['photo_path']) ?>" target="_blank">Current Photo</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Compliance and Bank Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PAN No</label>
                            <input type="text" name="pan_no" class="form-control" maxlength="20"
                                value="<?= esc(old('pan_no', (string) ($row['pan_no'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Aadhaar No</label>
                            <input type="text" name="aadhaar_no" class="form-control" maxlength="20"
                                value="<?= esc(old('aadhaar_no', (string) ($row['aadhaar_no'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" maxlength="120"
                                value="<?= esc(old('bank_name', (string) ($row['bank_name'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Account No</label>
                            <input type="text" name="bank_account_no" class="form-control" maxlength="40"
                                value="<?= esc(old('bank_account_no', (string) ($row['bank_account_no'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" name="ifsc_code" class="form-control" maxlength="20"
                                value="<?= esc(old('ifsc_code', (string) ($row['ifsc_code'] ?? ''))) ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="4"><?= esc(old('notes', (string) ($row['notes'] ?? ''))) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Status</h5></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            <?= (string) old('is_active', (string) ($row['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active Employee</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fe fe-save me-1"></i> Save Employee
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
