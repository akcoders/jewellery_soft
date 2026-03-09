<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Return #<?= (int) $return['id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/gold-inventory/returns/receipt/' . $return['id']) ?>" class="btn btn-outline-success" target="_blank">
            <i class="fe fe-printer"></i> Receipt
        </a>
        <a href="<?= site_url('admin/gold-inventory/returns/' . $return['id'] . '/edit') ?>" class="btn btn-outline-info">
            <i class="fe fe-edit"></i> Edit
        </a>
        <a href="<?= site_url('admin/gold-inventory/returns') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Receipt No:</strong> <?= esc((string) (($return['voucher_no'] ?? '') !== '' ? $return['voucher_no'] : ('RET#' . (int) $return['id']))) ?></div>
            <div class="col-md-3"><strong>Order Ref:</strong> <?= esc((string) ($return['order_no'] ?? '-')) ?></div>
            <div class="col-md-3"><strong>Issue Ref:</strong> <?= esc((string) (($return['issue_voucher_no'] ?? '') !== '' ? $return['issue_voucher_no'] : '-')) ?></div>
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) $return['return_date']) ?></div>
            <div class="col-md-3"><strong>Return From:</strong> <?= esc((string) ($return['return_from'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Karigar:</strong> <?= esc((string) ($return['karigar_name'] ?? '-')) ?></div>
            <div class="col-md-3 mt-2"><strong>Location:</strong> <?= esc((string) ($return['location_name'] ?? '-')) ?></div>
            <div class="col-md-3 mt-2"><strong>Total Weight:</strong> <?= number_format((float) $totals['total_weight'], 3) ?> gm</div>
            <div class="col-md-3 mt-2"><strong>Total Fine:</strong> <?= number_format((float) $totals['total_fine'], 3) ?> gm</div>
            <div class="col-md-3 mt-2"><strong>Total Value:</strong> <?= number_format((float) $totals['total_value'], 2) ?></div>
            <div class="col-md-6 mt-2"><strong>Notes:</strong> <?= esc((string) ($return['notes'] ?: '-')) ?></div>
            <div class="col-md-6 mt-2"><strong>Attachment:</strong>
                <?php if (! empty($return['attachment_path'])): ?>
                    <a href="<?= base_url((string) $return['attachment_path']) ?>" target="_blank"><?= esc((string) (($return['attachment_name'] ?? '') !== '' ? $return['attachment_name'] : 'Open')) ?></a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Purity</th>
                        <th>Color</th>
                        <th>Form</th>
                        <th>Weight (gm)</th>
                        <th>Fine (gm)</th>
                        <th>Rate/gm</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['master_purity_code'] ?: $line['purity_code'] ?: 'NA')) ?></td>
                            <td><?= esc((string) ($line['color_name'] ?? 'NA')) ?></td>
                            <td><?= esc((string) ($line['form_type'] ?? 'Raw')) ?></td>
                            <td><?= number_format((float) $line['weight_gm'], 3) ?></td>
                            <td><?= number_format((float) $line['fine_weight_gm'], 3) ?></td>
                            <td><?= $line['rate_per_gm'] === null ? '-' : number_format((float) $line['rate_per_gm'], 2) ?></td>
                            <td><?= $line['line_value'] === null ? '-' : number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
