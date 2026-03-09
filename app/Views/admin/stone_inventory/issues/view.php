<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Issue #<?= (int) $issue['id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/stone-inventory/issues/voucher/' . $issue['id']) ?>" target="_blank" class="btn btn-outline-success">
            <i class="fe fe-printer"></i> Print Voucher
        </a>
        <a href="<?= site_url('admin/stone-inventory/issues/' . $issue['id'] . '/edit') ?>" class="btn btn-outline-info">
            <i class="fe fe-edit"></i> Edit
        </a>
        <a href="<?= site_url('admin/stone-inventory/issues') ?>" class="btn btn-outline-primary">Back</a>
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
            <div class="col-md-3 mt-2"><strong>Total PCS:</strong> <?= number_format((float) ($totals['total_pcs'] ?? 0), 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Qty:</strong> <?= number_format((float) ($totals['total_qty'] ?? 0), 3) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Value:</strong> <?= number_format((float) ($totals['total_value'] ?? 0), 2) ?></div>
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
            <table class="table datatable table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>PCS</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['product_name'] ?? '-')) ?></td>
                            <td><?= esc((string) (($line['stone_type'] ?? '') !== '' ? $line['stone_type'] : '-')) ?></td>
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
<?= $this->endSection() ?>

