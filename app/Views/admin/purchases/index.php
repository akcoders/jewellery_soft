<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Purchase Entries</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/purchases/gold/create') ?>" class="btn btn-primary"><i class="fe fe-plus-circle"></i> Gold Purchase</a>
        <a href="<?= site_url('admin/diamond-inventory/purchases/create') ?>" class="btn btn-outline-primary"><i class="fe fe-plus-circle"></i> Diamond Purchase</a>
        <a href="<?= site_url('admin/purchases/stone/create') ?>" class="btn btn-outline-primary"><i class="fe fe-plus-circle"></i> Stone Purchase</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Purchase No</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Location</th>
                        <th>Invoice No</th>
                        <th>Invoice Amount</th>
                        <th>Due Date</th>
                        <th>Payment</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($purchases === []): ?>
                        <tr><td colspan="10" class="text-center text-muted">No purchases found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($purchases as $row): ?>
                        <tr>
                            <td><?= esc($row['purchase_no']) ?></td>
                            <td><?= esc($row['purchase_type'] ?: 'Mixed') ?></td>
                            <td><?= esc((string) $row['purchase_date']) ?></td>
                            <td><?= esc($row['vendor_name'] ?: '-') ?></td>
                            <td><?= esc($row['location_name'] ?: '-') ?></td>
                            <td><?= esc($row['invoice_no'] ?: '-') ?></td>
                            <td><?= esc(number_format((float) ($row['invoice_amount'] ?? 0), 2)) ?></td>
                            <td><?= esc($row['payment_due_date'] ?: '-') ?></td>
                            <td><?= esc($row['payment_status'] ?: '-') ?></td>
                            <td><?= esc($row['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
