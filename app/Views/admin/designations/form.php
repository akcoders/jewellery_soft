<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $row = is_array($row ?? null) ? $row : []; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1"><?= esc($title ?? 'Designation') ?></h4>
        <p class="text-muted mb-0">Use designation levels and default reports-to setup to keep hierarchy readable.</p>
    </div>
    <a href="<?= site_url('admin/designations') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/designations')) ?>" method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Designation Code</label>
                            <input type="text" name="designation_code" class="form-control" maxlength="30" required
                                value="<?= esc(old('designation_code', (string) ($row['designation_code'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Designation Name</label>
                            <input type="text" name="name" class="form-control" maxlength="120" required
                                value="<?= esc(old('name', (string) ($row['name'] ?? ''))) ?>">
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
                            <label class="form-label">Level No</label>
                            <input type="number" name="level_no" class="form-control" min="1" required
                                value="<?= esc(old('level_no', (string) ($row['level_no'] ?? '1'))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Default Reports To Designation</label>
                            <select name="reports_to_designation_id" class="form-select select2">
                                <option value="">No Default Mapping</option>
                                <?php foreach ($designations as $designation): ?>
                                    <option value="<?= (int) $designation['id'] ?>" <?= (string) old('reports_to_designation_id', (string) ($row['reports_to_designation_id'] ?? '')) === (string) $designation['id'] ? 'selected' : '' ?>>
                                        <?= esc((string) $designation['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?= esc(old('description', (string) ($row['description'] ?? ''))) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="can_manage_team" name="can_manage_team" value="1"
                            <?= (string) old('can_manage_team', (string) ($row['can_manage_team'] ?? '0')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="can_manage_team">Can Manage Team</label>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            <?= (string) old('is_active', (string) ($row['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active Designation</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fe fe-save me-1"></i> Save Designation
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
