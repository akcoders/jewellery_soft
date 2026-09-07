<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<style>
.sale-kpi{border:1px solid #e7eaf0;border-radius:14px;background:#fff;height:100%;padding:18px 20px}.sale-kpi small{color:#667085;font-weight:600}.sale-kpi strong{color:#101828;display:block;font-size:1.35rem;margin-top:5px}.sale-docs{display:flex;gap:6px;flex-wrap:wrap}.sale-docs .btn{white-space:nowrap}.sale-weight{line-height:1.65;white-space:nowrap}.sale-weight span{color:#667085;font-size:.75rem}.sale-no{color:#b42318;font-weight:700}.sale-register-card{border:1px solid #e7eaf0;border-radius:16px;overflow:hidden}.sale-register-card .card-header{background:#fff;border-bottom:1px solid #eaecf0;padding:18px 20px}
</style>

<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Studded Jewellery</span>
        <h4 class="mb-1">Wholesale Sale Bills</h4>
        <p class="mb-0">Customer invoices, jewellery weights, collections and packing lists in one register.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('admin/studded-jewellery/dashboard') ?>" class="btn btn-outline-primary"><i class="fe fe-bar-chart-2 me-1"></i>Sales Dashboard</a>
        <?php if (admin_can('showroom.sales.manage')): ?>
            <a href="<?= site_url('admin/studded-jewellery/sale-bills/create') ?>" class="btn btn-primary"><i class="fe fe-plus me-1"></i>Create Sale Bill</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="sale-kpi"><small>Total bills</small><strong><?= number_format((int) ($summary['sale_count'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="sale-kpi"><small>Invoice value</small><strong>₹<?= number_format((float) ($summary['invoice_value'] ?? 0), 2) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="sale-kpi"><small>Amount received</small><strong class="text-success">₹<?= number_format((float) ($summary['paid_amount'] ?? 0), 2) ?></strong></div></div>
    <div class="col-6 col-xl-3"><div class="sale-kpi"><small>Amount pending</small><strong class="text-danger">₹<?= number_format((float) ($summary['pending_amount'] ?? 0), 2) ?></strong></div></div>
</div>

<div class="card sale-register-card erp-data-card">
    <div class="card-header">
        <h5 class="card-title mb-1">Sale Bill Register</h5>
        <small class="text-muted">Invoice and packing-list documents are generated automatically for every sale.</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive erp-scroll-shell">
            <table class="table datatable table-hover align-middle mb-0 erp-mobile-scroll-table erp-responsive-wide" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Bill / Date</th>
                        <th>Customer</th>
                        <th>Gold Weight</th>
                        <th>Diamond</th>
                        <th>Stone</th>
                        <th>Other</th>
                        <th>Tax</th>
                        <th>Invoice Value</th>
                        <th>Payment</th>
                        <th>Documents</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($rows ?? []) as $row): ?>
                    <?php
                        $payment = (string) ($row['payment_status'] ?? 'Pending');
                        $badge = $payment === 'Paid' ? 'bg-success' : ($payment === 'Partial' ? 'bg-warning text-dark' : 'bg-danger');
                    ?>
                    <tr>
                        <td><a class="sale-no" href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $row['id']) ?>"><?= esc((string) ($row['invoice_no'] ?? $row['sale_no'] ?? '-')) ?></a><br><small class="text-muted"><?= esc((string) ($row['sale_date'] ?? '-')) ?></small></td>
                        <td><strong><?= esc((string) ($row['customer_name'] ?? '-')) ?></strong></td>
                        <td class="sale-weight"><strong><?= number_format((float) ($row['total_gold_weight'] ?? 0), 3) ?> gm</strong><br><span>₹<?= number_format((float) ($row['gold_amount'] ?? 0), 2) ?></span></td>
                        <td class="sale-weight"><strong><?= number_format((float) ($row['total_diamond_weight'] ?? 0), 3) ?> cts</strong><br><span>₹<?= number_format((float) ($row['diamond_amount'] ?? 0), 2) ?></span></td>
                        <td class="sale-weight"><strong><?= number_format((float) ($row['total_stone_weight'] ?? 0), 3) ?> cts</strong><br><span>₹<?= number_format((float) ($row['stone_amount'] ?? 0), 2) ?></span></td>
                        <td>₹<?= number_format((float) ($row['other_amount'] ?? 0), 2) ?></td>
                        <td><strong>₹<?= number_format((float) ($row['gst_amount'] ?? 0), 2) ?></strong><br><small class="text-muted"><?= number_format((float) ($row['gst_percent'] ?? 0), 2) ?>%</small></td>
                        <td><strong>₹<?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></strong></td>
                        <td><span class="badge <?= $badge ?>"><?= esc($payment) ?></span><br><small class="text-muted">Paid ₹<?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?><br>Due ₹<?= number_format((float) ($row['pending_amount'] ?? 0), 2) ?></small></td>
                        <td>
                            <div class="sale-docs">
                                <a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $row['id']) ?>" class="btn btn-sm btn-outline-primary" title="View sale"><i class="fe fe-eye"></i></a>
                                <a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $row['id'] . '/invoice') ?>?download=1" class="btn btn-sm btn-outline-danger" target="_blank" data-loader-off="1"><i class="fe fe-file-text me-1"></i>Bill</a>
                                <a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $row['id'] . '/packing-list') ?>?download=1" class="btn btn-sm btn-outline-secondary" target="_blank" data-loader-off="1"><i class="fe fe-package me-1"></i>Packing</a>
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
