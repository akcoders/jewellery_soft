<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Customers</h4>
    <a href="<?= site_url('admin/customers/create') ?>" class="btn btn-primary">Add Customer</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GSTIN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="5" class="text-center text-muted">No customers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= esc($customer['customer_code'] ?? '-') ?></td>
                            <td><?= esc($customer['name']) ?></td>
                            <td><?= esc($customer['phone'] ?: '-') ?></td>
                            <td><?= esc($customer['email'] ?: '-') ?></td>
                            <td><?= esc($customer['gstin'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


