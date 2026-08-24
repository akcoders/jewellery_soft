<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$statusBadge = static function (string $status): string {
    return match ($status) {
        'Completed', 'Dispatched', 'Ready' => 'bg-success',
        'Cancelled' => 'bg-danger',
        'QC', 'Packed' => 'bg-info',
        'In Production' => 'bg-warning',
        default => 'bg-secondary',
    };
};
$orderSource = static function (array $row): string {
    return trim((string) (($row['customer_name'] ?? '') ?: ($row['order_from'] ?? '') ?: '-'));
};
?>

<section class="erp-dashboard-hero mb-4">
    <div>
        <span class="erp-eyebrow">Jewellery operations</span>
        <h2 class="mb-2">Good day, <?= esc((string) (session('admin_name') ?: 'Admin')) ?></h2>
        <p class="mb-0">Track customers, production orders and inventory from one focused workspace.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (admin_can('orders.create')): ?>
            <a href="<?= site_url('admin/orders/create') ?>" class="btn btn-light">
                <i class="fe fe-plus-circle me-1"></i> New Order
            </a>
        <?php endif; ?>
        <?php if (admin_can('customers.create')): ?>
            <a href="<?= site_url('admin/customers/create') ?>" class="btn btn-outline-light">
                <i class="fe fe-user-plus me-1"></i> Add Customer
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <a href="<?= site_url('admin/orders/fresh') ?>" class="erp-kpi-card erp-kpi-danger">
            <span class="erp-kpi-icon"><i class="fe fe-user-plus"></i></span>
            <span>
                <small>Needs Assignment</small>
                <strong><?= esc((string) ($counts['unassignedOrders'] ?? 0)) ?></strong>
                <em>Open production queue</em>
            </span>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="<?= site_url('admin/customers') ?>" class="erp-kpi-card erp-kpi-gold">
            <span class="erp-kpi-icon"><i class="fe fe-users"></i></span>
            <span>
                <small>Active Customers</small>
                <strong><?= esc((string) ($counts['customers'] ?? 0)) ?></strong>
                <em>Customer directory</em>
            </span>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="<?= site_url('admin/orders') ?>" class="erp-kpi-card erp-kpi-blue">
            <span class="erp-kpi-icon"><i class="fe fe-activity"></i></span>
            <span>
                <small>Active Orders</small>
                <strong><?= esc((string) ($counts['activeOrders'] ?? 0)) ?></strong>
                <em>Currently in workflow</em>
            </span>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="<?= site_url('admin/orders') ?>" class="erp-kpi-card erp-kpi-green">
            <span class="erp-kpi-icon"><i class="fe fe-send"></i></span>
            <span>
                <small>Dispatched Today</small>
                <strong><?= esc((string) ($counts['dispatchedToday'] ?? 0)) ?></strong>
                <em><?= esc(date('d M Y')) ?></em>
            </span>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="erp-metal-card h-100">
            <div class="erp-metal-icon"><i class="fas fa-coins"></i></div>
            <div>
                <span>Fine Gold Stock</span>
                <strong><?= esc(number_format((float) ($goldCards['fine_gold_total'] ?? 0), 3)) ?> <small>gm</small></strong>
                <p>Current pure-gold equivalent across inventory.</p>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="erp-metal-card h-100">
            <div class="erp-metal-icon"><i class="fe fe-trending-up"></i></div>
            <div>
                <span>Pending Gold Requirement</span>
                <strong><?= esc(number_format((float) ($goldCards['current_req_gold'] ?? 0), 3)) ?> <small>gm</small></strong>
                <?php if ((int) ($goldCards['minus_karigar_count'] ?? 0) > 0): ?>
                    <p class="text-danger">
                        <?= esc((string) $goldCards['minus_karigar_count']) ?> karigar minus balance ·
                        <?= esc(number_format((float) ($goldCards['minus_karigar_gold'] ?? 0), 3)) ?> gm
                    </p>
                <?php else: ?>
                    <p class="text-success">All karigar balances are clear.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="erp-metal-card h-100">
            <div class="erp-metal-icon"><i class="fe fe-bar-chart-2"></i></div>
            <div>
                <span>Average Pure Gold Price</span>
                <strong>₹<?= esc(number_format((float) ($goldCards['avg_price_pure'] ?? 0), 2)) ?> <small>/ gm</small></strong>
                <p>Weighted purchase price per pure gram.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7 d-flex">
        <div class="card erp-section-card w-100">
            <div class="card-header">
                <div>
                    <span class="erp-eyebrow">Action queue</span>
                    <h5 class="card-title mb-0">Orders Needing Assignment</h5>
                </div>
                <a href="<?= site_url('admin/orders/fresh') ?>" class="btn btn-sm btn-outline-primary">View Queue</a>
            </div>
            <div class="card-body p-0">
                <?php if (($ordersNeedingAssignment ?? []) === []): ?>
                    <div class="erp-empty-state">
                        <i class="fe fe-check-circle"></i>
                        <strong>Assignment queue is clear</strong>
                        <span>Every active order has a karigar assigned.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" data-dt-skip="1">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer / Source</th>
                                    <th>Type</th>
                                    <th>Due Date</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordersNeedingAssignment as $row): ?>
                                    <tr>
                                        <td>
                                            <a class="erp-data-link" href="<?= site_url('admin/orders/' . (int) $row['id']) ?>">
                                                <?= esc((string) $row['order_no']) ?>
                                            </a>
                                        </td>
                                        <td><?= esc($orderSource($row)) ?></td>
                                        <td><?= esc((string) ($row['order_type'] ?? '-')) ?></td>
                                        <td><?= ! empty($row['due_date']) ? esc(date('d M Y', strtotime((string) $row['due_date']))) : '<span class="text-muted">Not set</span>' ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc((string) ($row['priority'] ?? 'Medium')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5 d-flex">
        <div class="card erp-section-card w-100">
            <div class="card-header">
                <div>
                    <span class="erp-eyebrow">Live activity</span>
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <a href="<?= site_url('admin/orders') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (($recentOrders ?? []) === []): ?>
                    <div class="erp-empty-state">
                        <i class="fe fe-inbox"></i>
                        <strong>No orders available</strong>
                        <span>New orders will appear here.</span>
                    </div>
                <?php else: ?>
                    <div class="erp-activity-list">
                        <?php foreach ($recentOrders as $row): ?>
                            <a href="<?= site_url('admin/orders/' . (int) $row['id']) ?>" class="erp-activity-item">
                                <span class="erp-order-mark"><i class="fe fe-shopping-bag"></i></span>
                                <span class="flex-grow-1">
                                    <strong><?= esc((string) $row['order_no']) ?></strong>
                                    <small><?= esc($orderSource($row)) ?> · <?= esc(date('d M, h:i A', strtotime((string) $row['created_at']))) ?></small>
                                </span>
                                <span class="badge <?= esc($statusBadge((string) ($row['status'] ?? ''))) ?>">
                                    <?= esc((string) ($row['status'] ?? '-')) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
