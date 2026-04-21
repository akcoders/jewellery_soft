<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="order_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($orderTypes ?? []) as $type): ?><option value="<?= esc($type) ?>" <?= ($filters['order_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($orderStatuses ?? []) as $status): ?><option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc($status) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Karigar</label>
                <select name="karigar_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($karigars ?? []) as $karigar): ?><option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="0">All</option>
                    <?php foreach (($customers ?? []) as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) ($filters['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= esc((string) $customer['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All</option>
                    <?php foreach (($priorities ?? []) as $priority): ?><option value="<?= esc($priority) ?>" <?= ($filters['priority'] ?? '') === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= site_url('admin/reports/orders-analysis') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Total Orders</small><strong><?= (int) ($cards['total_orders'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Active</small><strong><?= (int) ($cards['active_orders'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Completed</small><strong><?= (int) ($cards['completed_orders'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Delayed</small><strong class="text-danger"><?= (int) ($cards['delayed_orders'] ?? 0) ?></strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Gold Budget</small><strong><?= number_format((float) ($cards['gold_budget_gm'] ?? 0), 3) ?> gm</strong></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Diamond Budget</small><strong><?= number_format((float) ($cards['diamond_budget_cts'] ?? 0), 3) ?> cts</strong></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Analysis</h5></div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach (($analysis ?? []) as $line): ?><li><?= esc((string) $line) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Status Distribution</h5></div>
            <div class="card-body">
                <?php $statusMax = ($statusCounts ?? []) !== [] ? max($statusCounts) : 0; ?>
                <?php foreach (($statusCounts ?? []) as $label => $count): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= esc((string) $label) ?></span><span><?= (int) $count ?></span></div>
                        <div class="progress" style="height:9px;"><div class="progress-bar bg-primary" style="width: <?= $statusMax > 0 ? round(($count / $statusMax) * 100, 2) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Monthly Order Trend</h5></div>
            <div class="card-body">
                <?php $monthMax = ($monthlyCounts ?? []) !== [] ? max($monthlyCounts) : 0; ?>
                <?php foreach (($monthlyCounts ?? []) as $label => $count): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= esc((string) $label) ?></span><span><?= (int) $count ?></span></div>
                        <div class="progress" style="height:9px;"><div class="progress-bar bg-success" style="width: <?= $monthMax > 0 ? round(($count / $monthMax) * 100, 2) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Priority Mix</h5></div>
            <div class="card-body">
                <?php $priorityMax = ($priorityCounts ?? []) !== [] ? max($priorityCounts) : 0; ?>
                <?php foreach (($priorityCounts ?? []) as $label => $count): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= esc((string) $label) ?></span><span><?= (int) $count ?></span></div>
                        <div class="progress" style="height:9px;"><div class="progress-bar bg-warning" style="width: <?= $priorityMax > 0 ? round(($count / $priorityMax) * 100, 2) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Top Karigars By Order Count</h5></div>
            <div class="card-body">
                <?php $karigarMax = ($karigarCounts ?? []) !== [] ? max($karigarCounts) : 0; ?>
                <?php foreach (($karigarCounts ?? []) as $label => $count): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= esc((string) $label) ?></span><span><?= (int) $count ?></span></div>
                        <div class="progress" style="height:9px;"><div class="progress-bar bg-info" style="width: <?= $karigarMax > 0 ? round(($count / $karigarMax) * 100, 2) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Orders</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Created</th>
                        <th>Customer</th>
                        <th>Karigar</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Delay</th>
                        <th>Qty</th>
                        <th>Gold Budget</th>
                        <th>Diamond Budget</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($rows ?? []) === []): ?><tr><td colspan="12" class="text-center text-muted">No orders found.</td></tr><?php endif; ?>
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr>
                            <td><a href="<?= site_url('admin/orders/' . (int) ($row['id'] ?? 0)) ?>"><?= esc((string) ($row['order_no'] ?? '-')) ?></a></td>
                            <td><?= esc(substr((string) ($row['created_at'] ?? ''), 0, 10)) ?></td>
                            <td><?= esc((string) ($row['customer_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['karigar_name'] ?? 'Unassigned')) ?></td>
                            <td><?= esc((string) ($row['order_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['status'] ?? '-')) ?></td>
                            <td><?= esc((string) ($row['priority'] ?? '-')) ?></td>
                            <td><?= esc((string) (($row['due_date'] ?? '') !== '' ? $row['due_date'] : '-')) ?></td>
                            <td><?= ! empty($row['is_delayed']) ? (int) ($row['delay_days'] ?? 0) . ' days' : '-' ?></td>
                            <td><?= number_format((float) ($row['total_qty'] ?? 0), 3) ?></td>
                            <td><?= number_format((float) ($row['gold_budget_gm'] ?? 0), 3) ?> gm</td>
                            <td><?= number_format((float) ($row['diamond_budget_cts'] ?? 0), 3) ?> cts</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
