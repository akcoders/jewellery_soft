<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$preview = is_array($preview ?? null) ? $preview : null;
$categories = is_array($preview['categories'] ?? null) ? $preview['categories'] : [];
$latestRelease = is_array($latestRelease ?? null) ? $latestRelease : null;
$cleanupAudits = is_array($cleanupAudits ?? null) ? $cleanupAudits : [];
$labels = [
    'orders' => 'Orders',
    'purchases' => 'Purchases',
    'issues' => 'Issuements',
    'returns' => 'Returns',
    'adjustments' => 'Adjustments',
    'notifications' => 'Notifications',
    'tasks' => 'Tasks',
    'accounting_and_sales' => 'Accounting & Sales',
    'other_operational_rows' => 'Other Operational Rows',
];
?>

<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Admin controls</span>
        <h4 class="mb-1">System Maintenance</h4>
        <p class="mb-0">Publish PWA updates and safely remove a complete month of test transactions.</p>
    </div>
</div>

<?php if (! ($maintenanceReady ?? false)): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
        <span><strong>Database update required.</strong> Apply the pending maintenance tables first.</span>
        <a class="btn btn-warning btn-sm" href="<?= site_url('admin/system/database-update') ?>">Open Database Update</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fe fe-refresh-cw me-2"></i>PWA App Update</h5></div>
            <div class="card-body">
                <p class="text-muted">Send every active app user a push notification and activate the in-app “App updated — please relaunch” prompt. The device clears its PWA cache when the user relaunches.</p>
                <?php if ($latestRelease): ?>
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="small text-muted">Latest published version</div>
                        <div class="fw-semibold"><?= esc((string) $latestRelease['version']) ?></div>
                        <div class="small text-muted mt-1">
                            <?= esc(date('d M Y, h:i A', strtotime((string) $latestRelease['created_at']))) ?>
                            <?php if (! empty($latestRelease['released_by_name'])): ?> · <?= esc((string) $latestRelease['released_by_name']) ?><?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border">No PWA refresh has been published yet.</div>
                <?php endif; ?>
                <form action="<?= site_url('admin/system/maintenance/pwa-update') ?>" method="post">
                    <?= csrf_field() ?>
                    <label class="form-label" for="pwa-message">Device message</label>
                    <textarea class="form-control mb-3" id="pwa-message" name="message" maxlength="255" rows="3">A new Aabhushan ERP version is available. Clear the old cache and relaunch now.</textarea>
                    <button class="btn btn-primary" type="submit" <?= ($maintenanceReady ?? false) ? '' : 'disabled' ?> onclick="return confirm('Publish the PWA update prompt to all active users?');">
                        <i class="fe fe-send me-1"></i> Send Cache Clear & Relaunch
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100 border-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-danger"><i class="fe fe-alert-triangle me-2"></i>Monthly Test Data Cleanup</h5>
                <span class="badge bg-danger">Permanent</span>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    Deletes transactional data for the selected month, including orders, purchases, issuements, returns, accounting/sales rows, tasks and notifications. Gold, diamond, stone and accounting stock effects are rolled back. Users, permissions, company settings and master catalogues are preserved.
                </div>
                <form class="row g-2 align-items-end mb-3" action="<?= site_url('admin/system/maintenance') ?>" method="get">
                    <div class="col-sm-7">
                        <label class="form-label" for="cleanup-month">Month to preview</label>
                        <input class="form-control" type="month" id="cleanup-month" name="month" max="<?= esc(date('Y-m')) ?>" value="<?= esc((string) ($selectedMonth ?? date('Y-m'))) ?>" required>
                    </div>
                    <div class="col-sm-5"><button class="btn btn-outline-primary w-100" type="submit">Preview Data</button></div>
                </form>

                <?php if (! empty($previewError)): ?>
                    <div class="alert alert-danger"><?= esc((string) $previewError) ?></div>
                <?php elseif ($preview): ?>
                    <div class="row g-2 mb-3">
                        <?php foreach ($labels as $key => $label): ?>
                            <div class="col-6 col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted"><?= esc($label) ?></div>
                                    <div class="h5 mb-0"><?= number_format((int) ($categories[$key] ?? 0)) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="alert <?= ($preview['can_cleanup'] ?? false) ? 'alert-warning' : 'alert-danger' ?>">
                        <strong><?= number_format((int) ($preview['total_rows'] ?? 0)) ?> previewed row(s).</strong>
                        <?= esc((string) ($preview['safety_message'] ?? '')) ?>
                    </div>

                    <?php if (($preview['can_cleanup'] ?? false) && (int) ($preview['total_rows'] ?? 0) > 0 && ($maintenanceReady ?? false)): ?>
                        <form action="<?= site_url('admin/system/maintenance/monthly-cleanup') ?>" method="post" onsubmit="return confirm('This permanently deletes the selected month and rolls stock back. Continue?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="month" value="<?= esc((string) $preview['month']) ?>">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Administrator password</label>
                                    <input class="form-control" type="password" name="password" autocomplete="current-password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type <code>CLEAR <?= esc((string) $preview['month']) ?></code></label>
                                    <input class="form-control" type="text" name="confirmation" autocomplete="off" required>
                                </div>
                            </div>
                            <button class="btn btn-danger mt-3" type="submit"><i class="fe fe-trash-2 me-1"></i> Clear <?= esc((string) $preview['month']) ?> Data & Roll Back Stock</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Cleanup Audit History</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Month</th><th>Status</th><th>Requested By</th><th>Started</th><th>Completed</th><th>Result</th></tr></thead>
                <tbody>
                <?php foreach ($cleanupAudits as $audit): ?>
                    <?php $result = json_decode((string) ($audit['result_json'] ?? ''), true); ?>
                    <tr>
                        <td class="fw-semibold"><?= esc((string) $audit['month_key']) ?></td>
                        <td><span class="badge <?= ($audit['status'] ?? '') === 'completed' ? 'bg-success' : (($audit['status'] ?? '') === 'failed' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= esc(ucfirst((string) $audit['status'])) ?></span></td>
                        <td><?= esc((string) (($audit['requested_by_name'] ?? '') ?: ('User #' . (int) ($audit['requested_by'] ?? 0)))) ?></td>
                        <td><?= esc((string) ($audit['started_at'] ?? '—')) ?></td>
                        <td><?= esc((string) (($audit['completed_at'] ?? '') ?: '—')) ?></td>
                        <td>
                            <?php if (is_array($result)): ?><?= number_format((int) ($result['records_deleted'] ?? 0)) ?> deleted
                            <?php elseif (! empty($audit['error_message'])): ?><span class="text-danger"><?= esc((string) $audit['error_message']) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($cleanupAudits === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No monthly cleanup has been run.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
