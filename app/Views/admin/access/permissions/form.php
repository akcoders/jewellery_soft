<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php $row = is_array($row ?? null) ? $row : []; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1"><?= esc($title ?? 'Permission') ?></h4>
        <p class="text-muted mb-0">Keep permission codes stable. Roles and route guards depend on them.</p>
    </div>
    <a href="<?= site_url('admin/access/permissions') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back
    </a>
</div>

<form action="<?= esc($formAction ?? site_url('admin/access/permissions')) ?>" method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Permission Code</label>
                            <input type="text" name="code" class="form-control" maxlength="100" required value="<?= esc(old('code', (string) ($row['code'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Permission Name</label>
                            <input type="text" name="name" class="form-control" maxlength="120" required value="<?= esc(old('name', (string) ($row['name'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Module Group</label>
                            <input list="moduleGroups" name="module_group" class="form-control" maxlength="80" required value="<?= esc(old('module_group', (string) ($row['module_group'] ?? ''))) ?>">
                            <datalist id="moduleGroups">
                                <?php foreach ($moduleOptions as $module): ?>
                                    <option value="<?= esc($module) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Action Key</label>
                            <input type="text" name="action_key" class="form-control" maxlength="40" value="<?= esc(old('action_key', (string) ($row['action_key'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= esc(old('sort_order', (string) ($row['sort_order'] ?? '0'))) ?>">
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
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (string) old('is_active', (string) ($row['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active Permission</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fe fe-save me-1"></i> Save Permission
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
