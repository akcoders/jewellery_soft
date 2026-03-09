<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Gold Returns</h4>
    <a href="<?= site_url('admin/gold-inventory/returns/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Return
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
                <a href="<?= site_url('admin/gold-inventory/returns') ?>" class="btn btn-light">Reset</a>
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
                        <th>Receipt No</th>
                        <th>Order</th>
                        <th>Issue Ref</th>
                        <th>Date</th>
                        <th>Return From</th>
                        <th>Purpose</th>
                        <th>Location</th>
                        <th>Lines</th>
                        <th>Total Weight</th>
                        <th>Total Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($returns ?? []) === []): ?>
                        <tr><td colspan="12" class="text-center text-muted">No return records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($returns ?? []) as $return): ?>
                        <tr>
                            <td><?= (int) $return['id'] ?></td>
                            <td><?= esc((string) (($return['voucher_no'] ?? '') !== '' ? $return['voucher_no'] : ('RET#' . (int) $return['id']))) ?></td>
                            <td><?= esc((string) ($return['order_no'] ?? '-')) ?></td>
                            <td><?= esc((string) (($return['issue_voucher_no'] ?? '') !== '' ? $return['issue_voucher_no'] : '-')) ?></td>
                            <td><?= esc((string) $return['return_date']) ?></td>
                            <td><?= esc((string) ($return['return_from'] ?? '-')) ?></td>
                            <td><?= esc((string) ($return['purpose'] ?? '-')) ?></td>
                            <td><?= esc((string) ($return['location_name'] ?? '-')) ?></td>
                            <td><?= (int) $return['line_count'] ?></td>
                            <td><?= number_format((float) $return['total_weight'], 3) ?> gm</td>
                            <td><?= number_format((float) $return['total_value'], 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/gold-inventory/returns/view/' . $return['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <a href="<?= site_url('admin/gold-inventory/returns/receipt/' . $return['id']) ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="fe fe-printer"></i></a>
                                    <a href="<?= site_url('admin/gold-inventory/returns/' . $return['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info"><i class="fe fe-edit"></i></a>
                                    <form method="post" action="<?= site_url('admin/gold-inventory/returns/' . $return['id'] . '/delete') ?>" onsubmit="return confirm('Delete this return?');">
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
