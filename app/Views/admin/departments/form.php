<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $row = is_array($row ?? null) ? $row : []; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1"><?= esc($title ?? 'Department') ?></h4>
        <p class="text-muted mb-0">Keep department structure clean so staff and permissions map correctly later.</p>
    </div>
    <a href="<?= site_url('admin/departments') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/departments')) ?>" method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Department Code</label>
                            <input type="text" name="department_code" class="form-control" maxlength="30" required
                                value="<?= esc(old('department_code', (string) ($row['department_code'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" name="name" class="form-control" maxlength="120" required
                                value="<?= esc(old('name', (string) ($row['name'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control"
                                value="<?= esc(old('sort_order', (string) ($row['sort_order'] ?? '0'))) ?>">
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
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            <?= (string) old('is_active', (string) ($row['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active Department</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fe fe-save me-1"></i> Save Department
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
