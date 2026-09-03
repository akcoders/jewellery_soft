<?= $this->extend('customer/layout') ?>

<?= $this->section('content') ?>
<?php
$isSalesPerson = session('customer_user_role') === 'sales_person';
$completedStatuses = ['Completed', 'Delivered', 'Dispatched'];
$completed = count(array_filter($orders, static fn(array $order): bool => in_array((string) $order['status'], $completedStatuses, true)));
$statusClass = static fn(string $status): string => match ($status) {
    'Completed', 'Delivered', 'Dispatched', 'Ready' => 'success',
    'Cancelled' => 'danger',
    'In Production', 'QC', 'Packed' => 'warning',
    default => '',
};
$formatDate = static function (?string $date, string $fallback = '-'): string {
    $value = trim((string) $date);
    if ($value === '') return $fallback;
    $timestamp = strtotime($value);
    return $timestamp === false ? $fallback : date('d M Y', $timestamp);
};
?>
<style>
    .team-member-list { display: grid; gap: 10px; }
    .team-member { align-items: center; background: #f8f9fb; border: 1px solid #e6e9ef; border-radius: 12px; display: flex; gap: 11px; padding: 11px 12px; }
    .team-member-avatar { align-items: center; background: var(--portal-gold-soft); border-radius: 10px; color: var(--portal-gold); display: inline-flex; flex: 0 0 38px; height: 38px; justify-content: center; }
    .team-member strong, .team-member small { display: block; }
    .team-member strong { font-size: 12px; }
    .team-member small { color: var(--portal-muted); font-size: 10px; margin-top: 2px; overflow-wrap: anywhere; }
    .team-form-panel { background: linear-gradient(135deg, #fffdf8, #fff); border: 1px solid #eadfca; border-radius: 14px; padding: 18px; }
    .portal-password-field { position: relative; }
    .portal-password-field .form-control { padding-right: 43px; }
    .portal-password-toggle { background: transparent; border: 0; color: #7b8494; height: 42px; position: absolute; right: 1px; top: 1px; width: 42px; }
    .portal-order-identity { align-items: center; display: flex; gap: 10px; min-width: 220px; }
    .portal-order-thumb { align-items: center; background: #f3f4f6; border: 1px solid #e3e6eb; border-radius: 10px; color: #a2a9b4; display: inline-flex; flex: 0 0 46px; height: 46px; justify-content: center; overflow: hidden; }
    .portal-order-thumb img { height: 100%; object-fit: cover; width: 100%; }
</style>

<div class="portal-hero mb-4">
    <div>
        <span class="eyebrow"><?= esc((string) session('customer_name')) ?></span>
        <h2 class="mb-1"><?= $isSalesPerson ? 'My Submitted Orders' : 'My Orders' ?></h2>
        <p class="mb-0"><?= $isSalesPerson ? 'Only orders assigned to your salesperson login are shown.' : 'Track customer-safe status updates for every order in your account.' ?> Internal karigar assignment always remains private.</p>
    </div>
    <a href="<?= site_url('customer/orders/create') ?>" class="btn btn-dark btn-lg"><i class="fe fe-plus-circle me-1"></i>Create Order</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><i class="fe fe-shopping-bag"></i><small>Total Orders</small><h3 class="mb-0"><?= count($orders) ?></h3></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><i class="fe fe-check-circle"></i><small>Completed</small><h3 class="mb-0"><?= $completed ?></h3></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><i class="fe fe-clock"></i><small>In Progress</small><h3 class="mb-0"><?= max(0, count($orders) - $completed) ?></h3></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><i class="fe fe-users"></i><small>Sales People</small><h3 class="mb-0"><?= count($salesPeople) ?></h3></div></div>
</div>

<div class="portal-card card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-3">
        <div><span class="eyebrow">Status tracking</span><h5 class="mb-0 mt-1">Order Register</h5></div>
        <span class="small text-muted"><?= count($orders) ?> record<?= count($orders) === 1 ? '' : 's' ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive portal-scroll-shell">
            <table class="table portal-scroll-table mb-0">
                <thead><tr><th>Order</th><th>Order Name</th><th>Category</th><th>Order Type</th><th>Design</th><th>Required By</th><th>Current Status</th></tr></thead>
                <tbody>
                    <?php if ($orders === []): ?><tr><td colspan="7" class="text-center text-muted py-5">No orders available for this login.</td></tr><?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Order"><div class="portal-order-identity"><span class="portal-order-thumb"><?php if (! empty($order['thumbnail_url'])): ?><img src="<?= esc((string) $order['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" onerror="this.replaceWith(document.createTextNode('◇'))"><?php else: ?><i class="fe fe-image"></i><?php endif; ?></span><span><strong><?= esc((string) $order['order_no']) ?></strong><small class="d-block text-muted mt-1">Created <?= esc($formatDate((string) $order['created_at'])) ?></small></span></div></td>
                            <td data-label="Order Name"><strong><?= esc((string) (($order['order_name'] ?? '') ?: '-')) ?></strong></td>
                            <td data-label="Category"><?= esc((string) (($order['order_category_name'] ?? '') ?: '-')) ?></td>
                            <td data-label="Order Type"><?= esc((string) $order['order_type']) ?></td>
                            <td data-label="Design"><?= esc((string) (($order['order_design_type'] ?? '') ?: 'Fresh')) ?></td>
                            <td data-label="Required By"><?= esc($formatDate((string) ($order['due_date'] ?? ''))) ?></td>
                            <td data-label="Current Status"><span class="status-pill <?= esc($statusClass((string) $order['status'])) ?>"><?= esc((string) $order['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManageSalesPeople): ?>
    <div class="portal-card card">
        <div class="card-header"><span class="eyebrow">Team access</span><h5 class="mb-0 mt-1">Salesperson Logins</h5></div>
        <div class="card-body p-3 p-lg-4">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h6 class="mb-2">Active Sales Team</h6>
                    <p class="text-muted small">Each salesperson can login, submit orders and see only their assigned orders.</p>
                    <div class="team-member-list">
                        <?php if ($salesPeople === []): ?><div class="text-muted small border rounded-3 p-3">No salesperson login created yet.</div><?php endif; ?>
                        <?php foreach ($salesPeople as $person): ?>
                            <div class="team-member">
                                <span class="team-member-avatar"><i class="fe fe-user"></i></span>
                                <span><strong><?= esc((string) $person['name']) ?></strong><small><?= esc((string) (($person['mobile'] ?? '') ?: 'No mobile')) ?> · <?= esc((string) $person['email']) ?></small></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="team-form-panel">
                        <h6 class="mb-1">Add Sales Person</h6>
                        <p class="text-muted small mb-3">Create an individual, password-protected login for this customer account.</p>
                        <form method="post" action="<?= site_url('customer/sales-people') ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <div class="col-md-6"><label class="form-label">Full Name <span class="text-danger">*</span></label><input class="form-control" name="name" value="<?= esc((string) old('name')) ?>" maxlength="150" required></div>
                            <div class="col-md-6"><label class="form-label">Mobile Number <span class="text-danger">*</span></label><input class="form-control" type="tel" name="mobile" value="<?= esc((string) old('mobile')) ?>" maxlength="30" required></div>
                            <div class="col-12"><label class="form-label">Login Email <span class="text-danger">*</span></label><input class="form-control" type="email" name="email" value="<?= esc((string) old('email')) ?>" autocomplete="email" required></div>
                            <div class="col-md-6">
                                <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
                                <div class="portal-password-field"><input id="sales-password" class="form-control" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" class="portal-password-toggle" data-password-target="sales-password" aria-label="Show password"><i class="fe fe-eye"></i></button></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="portal-password-field"><input id="sales-password-confirm" class="form-control" type="password" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" class="portal-password-toggle" data-password-target="sales-password-confirm" aria-label="Show password"><i class="fe fe-eye"></i></button></div>
                            </div>
                            <div class="col-12"><button class="btn btn-dark px-4" type="submit"><i class="fe fe-user-plus me-1"></i>Create Salesperson Login</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.querySelectorAll('[data-password-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordTarget || '');
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            const icon = button.querySelector('i');
            if (icon) icon.className = showing ? 'fe fe-eye' : 'fe fe-eye-off';
        });
    });
</script>
<?= $this->endSection() ?>
