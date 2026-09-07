<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$payment = (string) ($sale['payment_status'] ?? 'Pending');
$paymentBadge = $payment === 'Paid' ? 'bg-success' : ($payment === 'Partial' ? 'bg-warning text-dark' : 'bg-danger');
?>
<style>
.sale-detail-card{border:1px solid #e5e9f0;border-radius:16px;overflow:hidden}.sale-detail-card>.card-header{background:#fff;border-bottom:1px solid #eaecf0;padding:16px 20px}.sale-detail-card>.card-body{padding:20px}.sale-detail-grid{display:grid;gap:18px;grid-template-columns:repeat(4,minmax(0,1fr))}.sale-detail-grid small{color:#667085;display:block;font-weight:600;margin-bottom:4px}.sale-detail-grid strong{color:#101828}.sale-detail-kpi{background:#f8fafc;border:1px solid #eaecf0;border-radius:12px;padding:14px}.sale-detail-kpi span{color:#667085;display:block;font-size:.75rem}.sale-detail-kpi strong{display:block;margin-top:4px}.sale-line-thumb{background:#f2f4f7;border:1px solid #e4e7ec;border-radius:10px;height:60px;object-fit:cover;width:60px}.sale-line-thumb.empty{align-items:center;display:flex;justify-content:center}.tax-breakup{display:flex;flex-wrap:wrap;gap:8px}.tax-chip{background:#fff7e8;border:1px solid #fedf89;border-radius:999px;color:#93370d;font-size:.75rem;font-weight:700;padding:6px 10px}@media(max-width:991.98px){.sale-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.sale-detail-grid{grid-template-columns:1fr}}
</style>

<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Studded Jewellery Sale</span>
        <h4 class="mb-1"><?= esc((string) ($sale['invoice_no'] ?? $sale['sale_no'] ?? '-')) ?></h4>
        <p class="mb-0"><?= esc((string) ($sale['customer_name'] ?? '-')) ?> · <?= esc((string) ($sale['sale_date'] ?? '-')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $sale['id'] . '/invoice') ?>?download=1" target="_blank" data-loader-off="1" class="btn btn-outline-danger"><i class="fe fe-file-text me-1"></i>Tax Invoice</a>
        <a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) $sale['id'] . '/packing-list') ?>?download=1" target="_blank" data-loader-off="1" class="btn btn-outline-secondary"><i class="fe fe-package me-1"></i>Packing List</a>
        <a href="<?= site_url('admin/studded-jewellery/sale-bills') ?>" class="btn btn-light"><i class="fe fe-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card sale-detail-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Invoice Summary</h5><span class="badge <?= $paymentBadge ?>"><?= esc($payment) ?></span></div>
    <div class="card-body">
        <div class="sale-detail-grid mb-3">
            <div><small>Invoice Number</small><strong><?= esc((string) ($sale['invoice_no'] ?? '-')) ?></strong></div>
            <div><small>Invoice Date</small><strong><?= esc((string) ($sale['invoice_date'] ?? $sale['sale_date'] ?? '-')) ?></strong></div>
            <div><small>Packing List</small><strong><?= esc((string) ($sale['packing_no'] ?? '-')) ?></strong></div>
            <div><small>HSN / SAC</small><strong><?= esc((string) ($sale['hsn_sac'] ?? '711319')) ?></strong></div>
            <div><small>Customer</small><strong><?= esc((string) ($sale['customer_name'] ?? '-')) ?></strong></div>
            <div><small>Phone</small><strong><?= esc((string) ($sale['customer_phone'] ?? '-')) ?></strong></div>
            <div><small>GSTIN</small><strong><?= esc((string) ($sale['customer_gstin'] ?? '-')) ?></strong></div>
            <div><small>GST Master</small><strong><?= esc((string) ($sale['gst_master_name'] ?? '-')) ?></strong></div>
        </div>
        <div class="row g-2">
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Net Gold</span><strong><?= number_format((float) ($sale['total_gold_weight'] ?? 0), 3) ?> gm</strong></div></div>
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Diamond</span><strong><?= number_format((float) ($sale['total_diamond_weight'] ?? 0), 3) ?> cts</strong></div></div>
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Stone</span><strong><?= number_format((float) ($sale['total_stone_weight'] ?? 0), 3) ?> cts</strong></div></div>
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Taxable</span><strong>₹<?= number_format((float) ($sale['taxable_amount'] ?? 0), 2) ?></strong></div></div>
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Tax</span><strong>₹<?= number_format((float) ($sale['gst_amount'] ?? 0), 2) ?></strong></div></div>
            <div class="col-6 col-lg"><div class="sale-detail-kpi"><span>Invoice Value</span><strong>₹<?= number_format((float) ($sale['total_amount'] ?? 0), 2) ?></strong></div></div>
        </div>
        <?php if (($sale['tax_components'] ?? []) !== []): ?>
            <div class="tax-breakup mt-3">
                <?php foreach ($sale['tax_components'] as $component): ?>
                    <span class="tax-chip"><?= esc((string) ($component['name'] ?? 'Tax')) ?> <?= number_format((float) ($component['percentage'] ?? 0), 2) ?>% · ₹<?= number_format((float) ($component['amount'] ?? 0), 2) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (! empty($sale['notes'])): ?><div class="alert alert-light border mt-3 mb-0"><strong>Notes:</strong> <?= esc((string) $sale['notes']) ?></div><?php endif; ?>
    </div>
</div>

<div class="card sale-detail-card mb-3 erp-data-card">
    <div class="card-header"><h5 class="mb-0">Jewellery in this Bill</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive erp-scroll-shell">
            <table class="table table-hover align-middle mb-0 erp-mobile-scroll-table erp-responsive-wide">
                <thead><tr><th>Photo</th><th>Tag / Jewellery</th><th>Order</th><th>Gross</th><th>Net Gold</th><th>Diamond</th><th>Stone</th><th>Gold Value</th><th>Diamond Value</th><th>Stone Value</th><th>Other</th><th>Line Total</th></tr></thead>
                <tbody>
                <?php foreach (($items ?? []) as $item): ?>
                    <tr>
                        <td><?php if ((int) ($item['production_ready_item_id'] ?? 0) > 0 && ! empty($item['image_path'])): ?><a href="<?= site_url('admin/jewellery-inventory/image/' . (int) $item['production_ready_item_id']) ?>" target="_blank"><img class="sale-line-thumb" src="<?= site_url('admin/jewellery-inventory/image/' . (int) $item['production_ready_item_id']) ?>" loading="lazy" alt="Jewellery"></a><?php else: ?><span class="sale-line-thumb empty"><i class="fe fe-image"></i></span><?php endif; ?></td>
                        <td><strong><?= esc((string) ($item['tag_no'] ?? '-')) ?></strong><br><small class="text-muted"><?= esc((string) ($item['description'] ?? '-')) ?> · <?= esc((string) ($item['purity_label'] ?? '-')) ?></small></td>
                        <td><?= esc((string) ($item['order_no'] ?? '-')) ?><br><small class="text-muted"><?= esc((string) ($item['order_name'] ?? '-')) ?></small></td>
                        <td><?= number_format((float) ($item['gross_wt'] ?? 0), 3) ?> gm</td>
                        <td><?= number_format((float) ($item['net_gold_wt'] ?? 0), 3) ?> gm</td>
                        <td><?= number_format((float) ($item['diamond_cts'] ?? 0), 3) ?> cts</td>
                        <td><?= number_format((float) ($item['stone_wt'] ?? 0), 3) ?> cts</td>
                        <td>₹<?= number_format((float) ($item['gold_amount'] ?? 0), 2) ?></td>
                        <td>₹<?= number_format((float) ($item['diamond_amount'] ?? 0), 2) ?></td>
                        <td>₹<?= number_format((float) ($item['stone_amount'] ?? 0), 2) ?></td>
                        <td>₹<?= number_format((float) ($item['other_amount'] ?? 0), 2) ?></td>
                        <td><strong>₹<?= number_format((float) ($item['amount'] ?? 0), 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card sale-detail-card">
    <div class="card-header"><h5 class="mb-0">Payment Position</h5></div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="sale-detail-kpi"><span>Invoice Value</span><strong>₹<?= number_format((float) ($sale['total_amount'] ?? 0), 2) ?></strong></div></div>
            <div class="col-md-4"><div class="sale-detail-kpi"><span>Paid</span><strong class="text-success">₹<?= number_format((float) ($sale['paid_amount'] ?? 0), 2) ?></strong></div></div>
            <div class="col-md-4"><div class="sale-detail-kpi"><span>Pending</span><strong class="text-danger">₹<?= number_format((float) ($sale['pending_amount'] ?? 0), 2) ?></strong></div></div>
        </div>
        <?php if (($receipts ?? []) === []): ?>
            <p class="text-muted mb-0">No payment has been recorded against this invoice.</p>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th>Reference</th><th>Amount</th></tr></thead><tbody><?php foreach ($receipts as $receipt): ?><tr><td><?= esc((string) ($receipt['receipt_no'] ?? '-')) ?></td><td><?= esc((string) ($receipt['receipt_date'] ?? '-')) ?></td><td><?= esc((string) ($receipt['payment_mode'] ?? '-')) ?></td><td><?= esc((string) ($receipt['reference_no'] ?? '-')) ?></td><td>₹<?= number_format((float) ($receipt['amount'] ?? 0), 2) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
