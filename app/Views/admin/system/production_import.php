<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$documents = is_array($documents ?? null) ? $documents : [];
?>

<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">System maintenance</span>
        <h4 class="mb-1">Production Data Import</h4>
        <p class="mb-0">Replace demo transactions with the verified 2026–27 production workbooks and purchase invoices.</p>
    </div>
    <a href="<?= site_url('admin/system/database-update') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-database me-1"></i> Database Update
    </a>
</div>

<?php if (! $importReady): ?>
    <div class="alert alert-warning">
        <strong>Migration required.</strong> Run the pending database update first, then return to this page.
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card h-100 border-danger-subtle">
                <div class="card-header"><h5 class="card-title mb-0">Upload Production Archive</h5></div>
                <div class="card-body">
                    <div class="alert alert-danger small">
                        This action permanently removes all current operational records. It preserves the signed-in administrator, application roles, permissions, and database schema.
                    </div>
                    <p class="text-muted small">The ZIP must contain the four supplied workbooks. PDF invoices remain private in writable storage and are never added to Git.</p>
                    <form action="<?= site_url('admin/system/production-import') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="production_zip">Production ZIP</label>
                            <input class="form-control" id="production_zip" name="production_zip" type="file" accept=".zip,application/zip" required>
                            <div class="form-text">Maximum 30 MB. The supplied archive is approximately 14 MB.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="replacement_confirmation">Confirmation</label>
                            <input class="form-control" id="replacement_confirmation" name="replacement_confirmation" type="text" placeholder="Type REPLACE" autocomplete="off" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="admin_password">New Shweta Admin Password</label>
                            <input class="form-control" id="admin_password" name="admin_password" type="password" minlength="12" autocomplete="new-password" required>
                            <div class="form-text">Login email after import: shweta@aabhushan.in</div>
                        </div>
                        <button class="btn btn-danger" type="submit" onclick="return confirm('Replace every current operational record with this ZIP?');">
                            <i class="fe fe-upload-cloud me-1"></i> Replace Data and Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Import Rules</h5></div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">One administrator is retained as <strong>Shweta &lt;shweta@aabhushan.in&gt;</strong>.</li>
                        <li class="mb-2">Gold and diamond stock movements are imported into inventory.</li>
                        <li class="mb-2">Internal production references become customerless manufacturing orders.</li>
                        <li class="mb-2">Ready-job valuations and detailed issuements are preserved exactly for audit.</li>
                        <li class="mb-2">Completed orders create active tagged jewellery inventory with studded details and workbook pictures.</li>
                        <li class="mb-2">Karigar bills/payments and vendor source-payment markers appear in party accounts and ledgers.</li>
                        <li>Purchase PDFs are grouped by supplier and available only to authenticated administrators.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (! empty($latestBatch)): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-1">Latest Import</h5>
                <div class="small text-muted"><?= esc((string) $latestBatch['source_name']) ?> · <?= esc((string) $latestBatch['completed_at']) ?></div>
            </div>
            <span class="badge bg-success px-3 py-2">Completed</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ([
                    'orders' => 'Production Orders',
                    'karigars' => 'Karigars',
                    'vendors' => 'Vendors',
                    'purchase_documents' => 'Purchase PDFs',
                    'gold_movements' => 'Gold Movements',
                    'diamond_movements' => 'Diamond Movements',
                    'diamond_issue_lines' => 'Diamond Issue Lines',
                    'ready_items' => 'Ready Items',
                    'ready_images' => 'Ready Pictures',
                    'finished_jewellery_items' => 'Jewellery Inventory',
                    'labour_bills' => 'Karigar Bills',
                    'karigar_payments' => 'Karigar Payments',
                ] as $key => $label): ?>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted"><?= esc($label) ?></div>
                            <div class="h3 mb-0 mt-1"><?= esc((string) ($summary[$key] ?? 0)) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="small text-muted mt-3">SHA-256: <code><?= esc((string) $latestBatch['source_sha256']) ?></code></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($documents !== []): ?>
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Imported Purchase Documents</h5></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Vendor</th><th>File</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><?= esc((string) ($document['document_date'] ?: 'Unknown')) ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($document['category'] === 'gold' ? 'Gold' : 'Diamond / Stone') ?></span></td>
                        <td><?= esc((string) $document['vendor_name']) ?></td>
                        <td><?= esc((string) $document['original_name']) ?></td>
                        <td><?= esc((string) $document['payment_status']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/system/production-import/document/' . (int) $document['id']) ?>"><i class="fe fe-download"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
