<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$scopes = is_array($scopes ?? null) ? $scopes : [];
$flashError = session()->getFlashdata('error');
$scopeLabels = [
    'read' => ['View data', 'Orders, inventory, tasks, reports and other records you can see in the ERP.'],
    'write' => ['Create and update', 'Create orders, follow-ups, issuements, tasks and other records on your behalf.'],
    'sql' => ['Read-only SQL', 'Run read-only database queries (only if enabled on the MCP server).'],
];
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card mt-3">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="fe fe-link" style="font-size:2rem"></i>
                    <h4 class="mt-2 mb-1">Connect <?= esc((string) $clientName) ?></h4>
                    <p class="text-muted mb-0">wants to access Aabhushan ERP as you</p>
                </div>

                <?php if (! empty($flashError)): ?>
                    <div class="alert alert-danger"><?= esc((string) $flashError) ?></div>
                <?php endif; ?>

                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="small text-muted">Signed in as</div>
                    <div class="fw-semibold"><?= esc((string) $userName) ?></div>
                    <div class="small text-muted"><?= esc((string) $userEmail) ?></div>
                    <?php if (! empty($redirectLabel)): ?>
                        <div class="small text-muted mt-2">You will return to <strong><?= esc((string) $redirectLabel) ?></strong></div>
                    <?php endif; ?>
                </div>

                <form action="<?= site_url('admin/mcp-authorize') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="d" value="<?= esc((string) $d) ?>">
                    <input type="hidden" name="s" value="<?= esc((string) $s) ?>">

                    <label class="form-label">Allow it to</label>
                    <?php foreach ($scopes as $scope): ?>
                        <?php [$label, $help] = $scopeLabels[$scope] ?? [ucfirst((string) $scope), '']; ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="scope-<?= esc((string) $scope) ?>" name="scopes[]" value="<?= esc((string) $scope) ?>" checked>
                            <label class="form-check-label" for="scope-<?= esc((string) $scope) ?>">
                                <span class="fw-semibold"><?= esc($label) ?></span>
                                <?php if ($help !== ''): ?><span class="d-block small text-muted"><?= esc($help) ?></span><?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <p class="small text-muted mt-3">It gets the same permissions as your ERP role, never more. An administrator can disconnect it any time from MCP API Keys.</p>

                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary flex-fill" type="submit" name="decision" value="deny">Deny</button>
                        <button class="btn btn-primary flex-fill" type="submit" name="decision" value="allow"><i class="fe fe-check me-1"></i> Allow</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
