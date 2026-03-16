<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Bills</small><strong><?= (int) ($summary['sale_count'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Qty</small><strong><?= number_format((float) ($summary['total_qty'] ?? 0), 3) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Billed Amount</small><strong><?= number_format((float) ($summary['total_amount'] ?? 0), 2) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Received</small><strong><?= number_format((float) ($summary['received_amount'] ?? 0), 2) ?></strong></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Showroom Sales Register</h5>
        <?php if (admin_can('showroom.sales.manage')): ?>
            <a href="<?= site_url('admin/showroom-sales/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> New Sale</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Sale No</th>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Showroom</th>
                        <th>Counter</th>
                        <th>Customer</th>
                        <th>Salesperson</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['sale_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['invoice_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['sale_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['customer_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['salesperson_name'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?></td>
                            <td><span class="badge <?= (string) ($row['payment_status'] ?? '') === 'Paid' ? 'bg-success' : ((string) ($row['payment_status'] ?? '') === 'Partial' ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= esc((string) ($row['payment_status'] ?? '-')) ?></span></td>
                            <td>
                                <a href="<?= site_url('admin/showroom-sales/' . (int) $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                <?php if ((int) ($row['invoice_id'] ?? 0) > 0): ?>
                                    <a href="<?= site_url('api/documents/invoice/' . (int) $row['invoice_id']) ?>?download=1" class="btn btn-sm btn-outline-secondary" target="_blank" data-loader-off="1"><i class="fe fe-download"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
