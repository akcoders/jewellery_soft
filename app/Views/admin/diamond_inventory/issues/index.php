<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Diamond Issues</h4>
    <a href="<?= site_url('admin/diamond-inventory/issues/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Issue
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from" class="form-control" value="<?= esc((string) ($from ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to" class="form-control" value="<?= esc((string) ($to ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="fe fe-filter"></i> Filter</button>
                <a href="<?= site_url('admin/diamond-inventory/issues') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Voucher</th>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Karigar</th>
                        <th>Warehouse</th>
                        <th>Purpose</th>
                        <th>Lines</th>
                        <th>Total Carat</th>
                        <th>Total Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($issues ?? []) === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No issue records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($issues ?? []) as $issue): ?>
                        <tr>
                            <td><?= (int) $issue['id'] ?></td>
                            <td><?= esc((string) ($issue['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($issue['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) $issue['issue_date']) ?></td>
                            <td><?= esc((string) ($issue['karigar_name'] ?? $issue['issue_to'] ?? '-')) ?></td>
                            <td><?= esc((string) ($issue['warehouse_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($issue['purpose'] ?? '-')) ?></td>
                            <td><?= (int) $issue['line_count'] ?></td>
                            <td><?= number_format((float) $issue['total_carat'], 3) ?></td>
                            <td><?= number_format((float) $issue['total_value'], 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/diamond-inventory/issues/view/' . $issue['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= site_url('admin/diamond-inventory/issues/voucher/' . $issue['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fe fe-printer"></i></a>
                                    <a href="<?= site_url('admin/diamond-inventory/issues/' . $issue['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/diamond-inventory/issues/' . $issue['id'] . '/delete') ?>" onsubmit="return confirm('Delete this issue?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
