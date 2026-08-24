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

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GSTIN</th>
                        <th>Portal Access</th>
                        <th>Sales Team</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No customers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= esc($customer['customer_code'] ?? '-') ?></span></td>
                            <td class="fw-semibold"><?= esc($customer['name']) ?></td>
                            <td><?= esc($customer['phone'] ?: '-') ?></td>
                            <td><?= esc($customer['email'] ?: '-') ?></td>
                            <td><?= esc($customer['gstin'] ?: '-') ?></td>
                            <td>
                                <?php if ((int) ($customer['portal_user_count'] ?? 0) > 0): ?>
                                    <span class="badge bg-success"><i class="fe fe-check-circle me-1"></i>Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Not created</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) ($customer['sales_person_count'] ?? 0) ?></strong> salesperson<?= (int) ($customer['sales_person_count'] ?? 0) === 1 ? '' : 's' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
