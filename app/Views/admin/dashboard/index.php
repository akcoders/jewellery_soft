<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Open Leads</h6>
                <h3 class="mb-0"><?= esc((string) $counts['openLeads']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Customers</h6>
                <h3 class="mb-0"><?= esc((string) $counts['customers']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Active Orders</h6>
                <h3 class="mb-0"><?= esc((string) $counts['activeOrders']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <h6 class="mb-1">Dispatched Today</h6>
                <h3 class="mb-0"><?= esc((string) $counts['dispatchedToday']) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4 col-sm-6 col-12 d-flex">
        <div class="card w-100 border border-warning-subtle">
            <div class="card-body">
                <h6 class="mb-1">Fine Gold (All Stock)</h6>
                <h3 class="mb-0 text-warning"><?= esc(number_format((float) ($goldCards['fine_gold_total'] ?? 0), 3)) ?> <small class="text-muted">gm</small></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-6 col-12 d-flex">
        <div class="card w-100 border border-info-subtle">
            <div class="card-body">
                <h6 class="mb-1">Total Req Gold (Pending Issue)</h6>
                <h3 class="mb-0 text-info"><?= esc(number_format((float) ($goldCards['current_req_gold'] ?? 0), 3)) ?> <small class="text-muted">gm</small></h3>
                <?php if ((int) ($goldCards['minus_karigar_count'] ?? 0) > 0): ?>
                    <div class="small text-danger mt-1">
                        Minus Karigar: <?= esc((string) ($goldCards['minus_karigar_count'] ?? 0)) ?>,
                        <?= esc(number_format((float) ($goldCards['minus_karigar_gold'] ?? 0), 3)) ?> gm
                    </div>
                <?php else: ?>
                    <div class="small text-success mt-1">No karigar minus balance.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-6 col-12 d-flex">
        <div class="card w-100 border border-success-subtle">
            <div class="card-body">
                <h6 class="mb-1">Average Price (Pure Gold Eq.)</h6>
                <h3 class="mb-0 text-success">Rs <?= esc(number_format((float) ($goldCards['avg_price_pure'] ?? 0), 2)) ?> <small class="text-muted">/ pure gm</small></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Overdue Follow-ups</h5>
            </div>
            <div class="card-body">
                <?php if ($overdueFollowups === []): ?>
                    <p class="mb-0 text-muted">No overdue follow-ups.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Phone</th>
                                    <th>Follow-up At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueFollowups as $row): ?>
                                    <tr>
                                        <td><?= esc($row['lead_name'] ?? '-') ?></td>
                                        <td><?= esc($row['lead_phone'] ?? '-') ?></td>
                                        <td><?= esc((string) $row['followup_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Orders</h5>
            </div>
            <div class="card-body">
                <?php if ($recentOrders === []): ?>
                    <p class="mb-0 text-muted">No orders available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order No</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $row): ?>
                                    <tr>
                                        <td><a href="<?= site_url('admin/orders/' . $row['id']) ?>"><?= esc($row['order_no']) ?></a></td>
                                        <td><?= esc($row['customer_name'] ?? '-') ?></td>
                                        <td><?= esc($row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


