<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Issue #<?= (int) $issue['id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/diamond-inventory/issues/voucher/' . $issue['id']) ?>" target="_blank" class="btn btn-outline-success">
            <i class="fe fe-printer"></i> Print Voucher
        </a>
        <a href="<?= site_url('admin/diamond-inventory/issues/' . $issue['id'] . '/edit') ?>" class="btn btn-outline-info">
            <i class="fe fe-edit"></i> Edit
        </a>
        <a href="<?= site_url('admin/diamond-inventory/issues') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Voucher:</strong> <?= esc((string) ($issue['voucher_no'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Order Ref:</strong> <?= esc((string) ($issue['order_no'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) $issue['issue_date']) ?></div>
            <div class="col-md-3"><strong>Karigar:</strong> <?= esc((string) ($issue['karigar_name'] ?: $issue['issue_to'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Warehouse:</strong> <?= esc((string) ($issue['warehouse_name'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Purpose:</strong> <?= esc((string) ($issue['purpose'] ?: '-')) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Carat:</strong> <?= number_format((float) $totals['total_carat'], 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Total PCS:</strong> <?= number_format((float) $totals['total_pcs'], 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Value:</strong> <?= number_format((float) $totals['total_value'], 2) ?></div>
            <div class="col-md-3 mt-2">
                <strong>Attachment:</strong>
                <?php if (! empty($issue['attachment_path'])): ?>
                    <a href="<?= base_url((string) $issue['attachment_path']) ?>" target="_blank">Open</a>
                <?php else: ?>-<?php endif; ?>
            </div>
            <div class="col-md-6 mt-2"><strong>Notes:</strong> <?= esc((string) ($issue['notes'] ?: '-')) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Chalni</th>
                        <th>Color</th>
                        <th>Clarity</th>
                        <th>PCS</th>
                        <th>Carat</th>
                        <th>Rate/cts</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['diamond_type'] . ' ' . ($line['shape'] ? '(' . $line['shape'] . ')' : ''))) ?></td>
                            <td><?= esc(($line['chalni_from'] !== null && $line['chalni_to'] !== null) ? ($line['chalni_from'] . ' - ' . $line['chalni_to']) : 'NA') ?></td>
                            <td><?= esc((string) ($line['color'] ?? '-')) ?></td>
                            <td><?= esc((string) ($line['clarity'] ?? '-')) ?></td>
                            <td><?= number_format((float) $line['pcs'], 3) ?></td>
                            <td><?= number_format((float) $line['carat'], 3) ?></td>
                            <td><?= $line['rate_per_carat'] === null ? '-' : number_format((float) $line['rate_per_carat'], 2) ?></td>
                            <td><?= $line['line_value'] === null ? '-' : number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
