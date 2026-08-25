<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Production material movement</span>
        <h4 class="mb-1">Issuements</h4>
        <p class="mb-0">Combined gold, diamond and stone vouchers with karigar-wise traceability.</p>
    </div>
    <a href="<?= site_url('admin/issuements/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Create Issuement</a>
</div>

<div class="erp-finance-summary mb-3">
    <div class="erp-finance-metric blue"><i class="fe fe-file-text"></i><span><small>All Vouchers</small><strong><?= number_format(count($rows ?? [])) ?></strong></span></div>
    <div class="erp-finance-metric"><i class="fe fe-circle"></i><span><small>Gold Issues</small><strong><?= number_format(count($goldRows ?? [])) ?></strong></span></div>
    <div class="erp-finance-metric success"><i class="fe fe-box"></i><span><small>Diamond Issues</small><strong><?= number_format(count($diamondRows ?? [])) ?></strong></span></div>
    <div class="erp-finance-metric danger"><i class="fe fe-grid"></i><span><small>Stone Issues</small><strong><?= number_format(count($stoneRows ?? [])) ?></strong></span></div>
</div>

<div class="card erp-data-card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Combined Summary</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Material</th>
                        <th>Karigar</th>
                        <th>Warehouse</th>
                        <th>Purpose</th>
                        <th>Total Weight/Qty</th>
                        <th>PCS</th>
                        <th>Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No issuements found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['issue_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['voucher_no'] ?? '-')) ?></td>
                            <td>
                                <span class="badge <?= (string) ($row['material_type'] ?? '') === 'Mixed' ? 'bg-warning text-dark' : 'bg-light text-dark' ?>">
                                    <?= esc((string) ($row['material_type'] ?? '-')) ?>
                                </span>
                            </td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['warehouse_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['purpose'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['total_qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_value'] ?? 0), 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= esc((string) ($row['view_url'] ?? '#')) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= esc((string) ($row['voucher_url'] ?? '#')) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fe fe-printer"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card erp-data-card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Gold Issuements</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Karigar</th>
                        <th>Warehouse</th>
                        <th>Purpose</th>
                        <th>Weight (gm)</th>
                        <th>Value</th>
                        <th>Attachment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($goldRows ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No gold issuements.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($goldRows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['issue_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['warehouse_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['purpose'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['total_qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_value'] ?? 0), 2) ?></td>
                            <td>
                                <?php if ((string) ($row['attachment_url'] ?? '') !== ''): ?>
                                    <a href="<?= esc((string) $row['attachment_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Open</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= esc((string) ($row['view_url'] ?? '#')) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= esc((string) ($row['voucher_url'] ?? '#')) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fe fe-printer"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card erp-data-card mb-3">
    <div class="card-header">
        <h6 class="mb-0">Diamond Issuements</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Karigar</th>
                        <th>Warehouse</th>
                        <th>Purpose</th>
                        <th>CTS</th>
                        <th>PCS</th>
                        <th>Value</th>
                        <th>Attachment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($diamondRows ?? []) === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No diamond issuements.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($diamondRows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['issue_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['warehouse_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['purpose'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['total_qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_value'] ?? 0), 2) ?></td>
                            <td>
                                <?php if ((string) ($row['attachment_url'] ?? '') !== ''): ?>
                                    <a href="<?= esc((string) $row['attachment_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Open</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= esc((string) ($row['view_url'] ?? '#')) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= esc((string) ($row['voucher_url'] ?? '#')) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fe fe-printer"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card erp-data-card">
    <div class="card-header">
        <h6 class="mb-0">Stone Issuements</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Karigar</th>
                        <th>Warehouse</th>
                        <th>Purpose</th>
                        <th>Qty</th>
                        <th>PCS</th>
                        <th>Value</th>
                        <th>Attachment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($stoneRows ?? []) === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No stone issuements.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($stoneRows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['issue_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['warehouse_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['purpose'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['total_qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_pcs'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['total_value'] ?? 0), 2) ?></td>
                            <td>
                                <?php if ((string) ($row['attachment_url'] ?? '') !== ''): ?>
                                    <a href="<?= esc((string) $row['attachment_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Open</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= esc((string) ($row['view_url'] ?? '#')) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= esc((string) ($row['voucher_url'] ?? '#')) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fe fe-printer"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
