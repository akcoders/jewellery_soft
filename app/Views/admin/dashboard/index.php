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

$dashboardRow = static function (array $row) use ($orderSource): array {
    return [
        'id'           => (int) ($row['id'] ?? 0),
        'orderNo'      => (string) ($row['order_no'] ?? ''),
        'orderName'    => (string) ($row['order_name'] ?? ''),
        'orderType'    => (string) ($row['order_type'] ?? ''),
        'priority'     => (string) ($row['priority'] ?? ''),
        'status'       => (string) ($row['status'] ?? ''),
        'source'       => $orderSource($row),
        'dueDate'      => ! empty($row['due_date']) ? date('d M Y', strtotime((string) $row['due_date'])) : '',
        'dueRaw'       => ! empty($row['due_date']) ? date('Y-m-d', strtotime((string) $row['due_date'])) : '',
        'createdAt'    => ! empty($row['created_at']) ? date('d M, h:i A', strtotime((string) $row['created_at'])) : '',
        'thumbnailUrl' => (string) ($row['thumbnail_url'] ?? ''),
        'url'          => site_url('admin/orders/' . (int) ($row['id'] ?? 0)),
    ];
};
$dashboardPayload = [
    'adminName' => (string) (session('admin_name') ?: 'Admin'),
    'today'     => date('l, d M Y'),
    'todayIso'  => date('Y-m-d'),
    'hour'      => (int) date('G'),
    'can'       => [
        'createOrder'    => admin_can('orders.create'),
        'createCustomer' => admin_can('customers.create'),
    ],
    'urls'      => [
        'newOrder'      => site_url('admin/orders/create'),
        'newCustomer'   => site_url('admin/customers/create'),
        'freshOrders'   => site_url('admin/orders/fresh'),
        'orders'        => site_url('admin/orders'),
        'customers'     => site_url('admin/customers'),
        'goldStock'     => site_url('admin/gold-inventory/stock'),
    ],
    'counts'    => array_map('intval', $counts ?? []),
    'goldCards' => $goldCards ?? [],
    'ordersNeedingAssignment' => array_map($dashboardRow, $ordersNeedingAssignment ?? []),
    'recentOrders'            => array_map($dashboardRow, $recentOrders ?? []),
    'statusBreakdown'         => $statusBreakdown ?? [],
    'ordersTrend'             => $ordersTrend ?? [],
];
$reactSegments = service('uri')->getSegments();
$reactAssetDir = ((string) ($reactSegments[0] ?? '') === 'public') ? 'public/template/assets/react' : 'template/assets/react';
$reactFsDir = FCPATH . 'template/assets/react/';
$reactVersion = static fn (string $file): string => (string) (@filemtime($reactFsDir . $file) ?: '1');
$reactReady = is_file($reactFsDir . 'dashboard.js');
?>
<script type="application/json" id="erp-dashboard-data"><?= json_encode($dashboardPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div id="erp-react-dashboard">

<section class="erp-dashboard-hero mb-4" data-app-tour="dashboard-overview">
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

<div class="row g-3 mb-4" data-app-tour="dashboard-kpis">
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

<div class="row g-3 mb-4" data-app-tour="inventory-summary">
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

<div class="row g-3" data-app-tour="dashboard-activity">
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
                                    <th>Photo</th>
                                    <th>Order</th>
                                    <th>Order Name</th>
                                    <th>Type</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordersNeedingAssignment as $row): ?>
                                    <tr>
                                        <td><span class="erp-order-mark"><?php if (! empty($row['thumbnail_url'])): ?><img src="<?= esc((string) $row['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" onerror="this.style.display='none'"><?php else: ?><i class="fe fe-image"></i><?php endif; ?></span></td>
                                        <td>
                                            <a class="erp-data-link" href="<?= site_url('admin/orders/' . (int) $row['id']) ?>">
                                                <?= esc((string) $row['order_no']) ?>
                                            </a>
                                        </td>
                                        <td><strong><?= esc((string) (($row['order_name'] ?? '') ?: '-')) ?></strong></td>
                                        <td><?= esc((string) ($row['order_type'] ?? '-')) ?></td>
                                        <td><?= ! empty($row['due_date']) ? esc(date('d M Y', strtotime((string) $row['due_date']))) : '<span class="text-muted">Not set</span>' ?></td>
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
                                <span class="erp-order-mark"><?php if (! empty($row['thumbnail_url'])): ?><img src="<?= esc((string) $row['thumbnail_url'], 'attr') ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" onerror="this.style.display='none'"><?php else: ?><i class="fe fe-shopping-bag"></i><?php endif; ?></span>
                                <span class="flex-grow-1">
                                    <strong><?= esc((string) $row['order_no']) ?></strong>
                                    <small><?= esc((string) (($row['order_name'] ?? '') ?: 'Unnamed order')) ?> · <?= esc($orderSource($row)) ?> · <?= esc(date('d M, h:i A', strtotime((string) $row['created_at']))) ?></small>
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
</div>
<?= $this->endSection() ?>

<?php if ($reactReady): ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url($reactAssetDir . '/dashboard.css') ?>?v=<?= esc($reactVersion('dashboard.css')) ?>">
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="module" src="<?= base_url($reactAssetDir . '/dashboard.js') ?>?v=<?= esc($reactVersion('dashboard.js')) ?>"></script>
<?= $this->endSection() ?>
<?php endif; ?>
