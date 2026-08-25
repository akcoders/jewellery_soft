<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$pendingMigrations = is_array($pendingMigrations ?? null) ? $pendingMigrations : [];
$appliedMigrations = is_array($appliedMigrations ?? null) ? $appliedMigrations : [];
$availableMigrations = is_array($availableMigrations ?? null) ? $availableMigrations : [];
$isUpToDate = ($stateError ?? '') === '' && $pendingMigrations === [];
?>

<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">System maintenance</span>
        <h4 class="mb-1">Database Update</h4>
        <p class="mb-0">Apply pending application migrations safely after deploying new code.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge <?= $isUpToDate ? 'bg-success' : 'bg-warning text-dark' ?> px-3 py-2">
            <i class="fe <?= $isUpToDate ? 'fe-check-circle' : 'fe-alert-circle' ?> me-1"></i>
            <?= $isUpToDate ? 'Database Up to Date' : count($pendingMigrations) . ' Pending' ?>
        </span>
    </div>
</div>

<?php if (! empty($stateError)): ?>
    <div class="alert alert-danger">
        <strong>Could not inspect database:</strong> <?= esc((string) $stateError) ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Available Migrations</div>
            <div class="display-6 fw-bold mt-1"><?= esc((string) count($availableMigrations)) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Applied Migrations</div>
            <div class="display-6 fw-bold text-success mt-1"><?= esc((string) count($appliedMigrations)) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Pending Migrations</div>
            <div class="display-6 fw-bold <?= $pendingMigrations === [] ? 'text-success' : 'text-warning' ?> mt-1"><?= esc((string) count($pendingMigrations)) ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0">Pending Database Changes</h5></div>
            <div class="card-body">
                <?php if ($pendingMigrations === []): ?>
                    <div class="text-center py-5">
                        <i class="fe fe-check-circle text-success" style="font-size:42px"></i>
                        <h5 class="mt-3 mb-1">No pending migrations</h5>
                        <p class="text-muted mb-0">The database schema matches this application version.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group mb-4">
                        <?php foreach ($pendingMigrations as $migration): ?>
                            <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold"><?= esc((string) $migration['name']) ?></div>
                                    <div class="small text-muted"><?= esc((string) $migration['version']) ?></div>
                                </div>
                                <span class="badge bg-warning text-dark">Pending</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="alert alert-warning small">
                        Take a database backup before running migrations on production.
                    </div>
                    <form action="<?= site_url('admin/system/database-update') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Run all pending database migrations now?');">
                            <i class="fe fe-database me-1"></i> Run Database Update
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0">Recently Applied</h5></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($appliedMigrations, 0, 10) as $migration): ?>
                        <div class="list-group-item px-3 py-3">
                            <div class="fw-semibold"><?= esc((string) $migration['name']) ?></div>
                            <div class="small text-muted"><?= esc((string) $migration['version']) ?> · Batch <?= esc((string) $migration['batch']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($appliedMigrations === []): ?>
                        <div class="text-muted p-4">No applied migration history found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
