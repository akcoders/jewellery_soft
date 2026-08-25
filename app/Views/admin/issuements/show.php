<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Material movement</span>
        <h4 class="mb-1">Issuement <?= esc((string) ($voucherNo ?? '')) ?></h4>
        <p class="mb-0">Complete voucher, karigar, warehouse and issued-material breakdown.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/issuements') ?>" class="btn btn-outline-primary">Back</a>
        <a href="<?= site_url('admin/issuements/voucher/' . rawurlencode((string) ($voucherNo ?? ''))) ?>" target="_blank" class="btn btn-primary"><i class="fe fe-printer"></i> Print Voucher</a>
    </div>
</div>

<div class="erp-finance-summary mb-3">
    <div class="erp-finance-metric blue"><i class="fe fe-file-text"></i><span><small>Voucher</small><strong><?= esc((string) ($voucherNo ?? '-')) ?></strong></span></div>
    <div class="erp-finance-metric"><i class="fe fe-layers"></i><span><small>Material Type</small><strong><?= esc((string) ($materialType ?? '-')) ?></strong></span></div>
    <div class="erp-finance-metric success"><i class="fe fe-user"></i><span><small>Issued To</small><strong><?= esc((string) ($supplierName ?? '-')) ?></strong></span></div>
    <div class="erp-finance-metric danger"><i class="fe fe-credit-card"></i><span><small>Total Value</small><strong>₹<?= number_format((float) ($totalValue ?? 0), 2) ?></strong></span></div>
</div>

<div class="card erp-record-card mb-3">
    <div class="card-body">
        <div class="row erp-record-grid">
            <div class="col-md-3"><strong>Voucher No:</strong> <?= esc((string) ($voucherNo ?? '-')) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) ($issue['issue_date'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Type:</strong> <?= esc((string) ($materialType ?? '-')) ?></div>
            <div class="col-md-3"><strong>Issue To:</strong> <?= esc((string) ($supplierName ?? '-')) ?></div>
            <div class="col-md-3"><strong>Warehouse:</strong> <?= esc((string) ($issue['warehouse_name'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Purpose:</strong> <?= esc((string) ($issue['purpose'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Total Value:</strong> <?= number_format((float) ($totalValue ?? 0), 2) ?></div>
            <div class="col-12"><strong>Notes:</strong> <?= esc((string) (($issue['notes'] ?? '') !== '' ? $issue['notes'] : '-')) ?></div>
        </div>
    </div>
</div>

<?php if (($goldLines ?? []) !== []): ?>
<div class="card erp-data-card mb-3">
    <div class="card-header"><h6 class="mb-0">Gold Lines</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered datatable mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Purity</th>
                        <th>Weight (gm)</th>
                        <th>Rate/gm</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach (($goldLines ?? []) as $line): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc(trim((string) (($line['form_type'] ?? 'Gold') . ' ' . ($line['color_name'] ?? '')))) ?></td>
                            <td><?= esc((string) ($line['master_purity_code'] ?? $line['purity_code'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($line['weight_gm'] ?? 0), 3) ?></td>
                            <td><?= ($line['rate_per_gm'] ?? null) === null ? '-' : number_format((float) $line['rate_per_gm'], 2) ?></td>
                            <td><?= ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (($diamondLines ?? []) !== []): ?>
<div class="card erp-data-card mb-3">
    <div class="card-header"><h6 class="mb-0">Diamond Lines</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered datatable mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Grade</th>
                        <th>PCS</th>
                        <th>CTS</th>
                        <th>Rate/cts</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach (($diamondLines ?? []) as $line): ?>
                        <?php $chalni = ((string) ($line['chalni_from'] ?? '') !== '' || (string) ($line['chalni_to'] ?? '') !== '') ? ((string) ($line['chalni_from'] ?? '') . '-' . (string) ($line['chalni_to'] ?? '')) : 'NA'; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc(trim((string) (($line['diamond_type'] ?? '-') . ' ' . ($line['shape'] ?? '')))) ?></td>
                            <td><?= esc(trim($chalni . ' / ' . (string) ($line['color'] ?? '-') . ' / ' . (string) ($line['clarity'] ?? '-'))) ?></td>
                            <td><?= number_format((float) ($line['pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($line['carat'] ?? 0), 3) ?></td>
                            <td><?= ($line['rate_per_carat'] ?? null) === null ? '-' : number_format((float) $line['rate_per_carat'], 2) ?></td>
                            <td><?= ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (($stoneLines ?? []) !== []): ?>
<div class="card erp-data-card">
    <div class="card-header"><h6 class="mb-0">Stone Lines</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered datatable mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>PCS</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach (($stoneLines ?? []) as $line): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc(trim((string) (($line['product_name'] ?? '-') . ' ' . ($line['stone_type'] ?? '')))) ?></td>
                            <td><?= number_format((float) ($line['pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($line['qty'] ?? 0), 3) ?></td>
                            <td><?= ($line['rate'] ?? null) === null ? '-' : number_format((float) $line['rate'], 2) ?></td>
                            <td><?= ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
