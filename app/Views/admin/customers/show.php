<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$customer = is_array($customer ?? null) ? $customer : [];
$portalUsers = is_array($portalUsers ?? null) ? $portalUsers : [];
$addresses = is_array($addresses ?? null) ? $addresses : [];
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
$canManage = admin_can('customers.create');
?>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Customer directory</span>
        <h4 class="mb-1"><?= esc((string) ($customer['name'] ?? 'Customer Details')) ?></h4>
        <p class="mb-0">Profile, portal users, login status and recent order activity.</p>
    </div>
    <a href="<?= site_url('admin/customers') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i> Back</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4 col-md-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div><small class="text-muted">Customer Code</small><h5 class="mb-0"><?= esc((string) ($customer['customer_code'] ?? '-')) ?></h5></div>
                <span class="badge <?= (int) ($customer['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($customer['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="mb-2"><small class="text-muted d-block">Email</small><span class="fw-semibold"><?= esc((string) (($customer['email'] ?? '') ?: '-')) ?></span></div>
            <div class="mb-2"><small class="text-muted d-block">Phone</small><span class="fw-semibold"><?= esc((string) (($customer['phone'] ?? '') ?: '-')) ?></span></div>
            <div><small class="text-muted d-block">GSTIN</small><span class="fw-semibold"><?= esc((string) (($customer['gstin'] ?? '') ?: '-')) ?></span></div>
        </div></div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted">Order Activity</small>
            <div class="display-6 fw-bold mt-1"><?= (int) ($orderSummary['total_orders'] ?? 0) ?></div>
            <div class="text-muted">Total orders</div>
            <hr>
            <small class="text-muted d-block">Last order</small>
            <span class="fw-semibold"><?= ! empty($orderSummary['last_order_at']) ? esc(date('d M Y, h:i A', strtotime((string) $orderSummary['last_order_at']))) : 'No orders yet' ?></span>
        </div></div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-body">
            <small class="text-muted d-block mb-2">Saved Addresses</small>
            <?php if ($addresses === []): ?><span class="text-muted">No address saved.</span><?php endif; ?>
            <?php foreach ($addresses as $address): ?>
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between"><strong><?= esc((string) ($address['address_type'] ?? 'Address')) ?></strong><?php if ((int) ($address['is_default'] ?? 0) === 1): ?><span class="badge bg-light text-dark border">Default</span><?php endif; ?></div>
                    <small class="text-muted"><?= esc(implode(', ', array_filter([$address['line1'] ?? '', $address['line2'] ?? '', $address['city'] ?? '', $address['state'] ?? '', $address['country'] ?? '', $address['pincode'] ?? '']))) ?></small>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
    <div><h5 class="mb-0">Portal Users</h5><small class="text-muted">Passwords are never displayed; they can only be securely replaced.</small></div>
    <div class="d-flex gap-2 align-items-center"><span class="badge bg-primary"><?= count($portalUsers) ?> account<?= count($portalUsers) === 1 ? '' : 's' ?></span><?php if ($canManage): ?><button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createPortalUserModal"><i class="fe fe-user-plus me-1"></i> Add Portal User</button><?php endif; ?></div>
</div>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle border mb-0 erp-responsive-wide">
        <thead><tr><th>User</th><th>Role</th><th>Mobile</th><th>Last Login</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
            <?php if ($portalUsers === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No portal users found.</td></tr><?php endif; ?>
            <?php foreach ($portalUsers as $portalUser): ?>
                <tr>
                    <td><strong class="d-block"><?= esc((string) ($portalUser['name'] ?? '-')) ?></strong><small class="text-muted"><?= esc((string) ($portalUser['email'] ?? '-')) ?></small></td>
                    <td><span class="badge bg-light text-dark border"><?= esc(ucwords(str_replace('_', ' ', (string) ($portalUser['role'] ?? '-')))) ?></span></td>
                    <td><?= esc((string) (($portalUser['mobile'] ?? '') ?: '-')) ?></td>
                    <td><?= ! empty($portalUser['last_login_at']) ? esc(date('d M Y, h:i A', strtotime((string) $portalUser['last_login_at']))) : '<span class="text-muted">Never</span>' ?></td>
                    <td><span class="badge <?= (int) ($portalUser['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) ($portalUser['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end">
                        <?php if ($canManage): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#passwordModal<?= (int) $portalUser['id'] ?>"><i class="fe fe-key me-1"></i> Update Password</button><?php else: ?><span class="text-muted">View only</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="d-flex align-items-center justify-content-between mb-2"><h5 class="mb-0">Recent Orders</h5><span class="text-muted small">Latest 10</span></div>
<div class="table-responsive">
    <table class="table table-hover align-middle border mb-0 erp-responsive-wide">
        <thead><tr><th>Order</th><th>Status</th><th>Due Date</th><th>Created</th><th class="text-end">Action</th></tr></thead>
        <tbody>
            <?php if ($recentOrders === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No orders found.</td></tr><?php endif; ?>
            <?php foreach ($recentOrders as $order): ?>
                <tr><td class="fw-semibold"><?= esc((string) ($order['order_no'] ?? ('#' . ($order['id'] ?? '')))) ?></td><td><span class="badge bg-light text-dark border"><?= esc((string) ($order['status'] ?? '-')) ?></span></td><td><?= ! empty($order['due_date']) ? esc(date('d M Y', strtotime((string) $order['due_date']))) : '-' ?></td><td><?= ! empty($order['created_at']) ? esc(date('d M Y', strtotime((string) $order['created_at']))) : '-' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/orders/' . (int) $order['id']) ?>"><i class="fe fe-eye"></i></a></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="createPortalUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form action="<?= site_url('admin/customers/' . (int) $customer['id'] . '/users') ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="modal-header"><div><h5 class="modal-title">Create Portal User</h5><small class="text-muted"><?= esc((string) ($customer['name'] ?? 'Customer')) ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" maxlength="150" autocomplete="name" required></div>
                <div class="col-md-6"><label class="form-label">Mobile</label><input type="text" name="mobile" class="form-control" maxlength="30" autocomplete="tel"></div>
                <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="191" autocomplete="username" required></div>
                <div class="col-12"><label class="form-label">Role</label><select name="role" class="form-select" required><option value="customer_admin">Customer Admin</option><option value="sales_person">Sales Person</option></select></div>
                <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fe fe-user-plus me-1"></i> Create User</button></div>
        </form>
    </div></div>
</div>
<?php foreach ($portalUsers as $portalUser): ?>
<div class="modal fade" id="passwordModal<?= (int) $portalUser['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form action="<?= site_url('admin/customers/' . (int) $customer['id'] . '/users/' . (int) $portalUser['id'] . '/password') ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="modal-header"><div><h5 class="modal-title">Update Portal Password</h5><small class="text-muted"><?= esc((string) ($portalUser['name'] ?? '')) ?> · <?= esc((string) ($portalUser['email'] ?? '')) ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required><small class="text-muted">Minimum 8 characters.</small></div>
                <div><label class="form-label">Confirm Password</label><input type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fe fe-key me-1"></i> Update Password</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?= $this->endSection() ?>
