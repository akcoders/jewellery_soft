<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Customer directory</span>
        <h4 class="mb-1">Customers</h4>
        <p class="mb-0">Manage billing profiles and customer contact details.</p>
    </div>
    <?php if (admin_can('customers.create')): ?>
        <a href="<?= site_url('admin/customers/create') ?>" class="btn btn-primary"><i class="fe fe-user-plus me-1"></i> Add Customer</a>
    <?php endif; ?>
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
                            <td><span class="badge bg-light text-dark border"><?= esc($customer['customer_code'] ?? '-') ?></span></td>
                            <td class="fw-semibold"><?= esc($customer['name']) ?></td>
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

