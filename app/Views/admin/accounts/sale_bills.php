<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-1">Sale Bills</h4>
                    <p class="text-muted mb-0">Retail showroom bills generated from showroom stock and FG billing.</p>
                </div>
                <?php if (admin_can('showroom.sales.manage')): ?>
                    <a href="<?= site_url('admin/showroom-sales/create') ?>" class="btn btn-primary"><i class="fe fe-plus"></i> Create Sale Bill</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (! ($showroomSalesEnabled ?? false)): ?>
            <div class="text-center py-5">
                <i class="fe fe-file-text" style="font-size: 34px; color: #6b7280;"></i>
                <h5 class="mt-3 mb-2">Showroom Sales Module Pending Migration</h5>
                <p class="text-muted mb-0">Run the latest migration to enable sale bills.</p>
            </div>
        <?php else: ?>
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
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
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
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
