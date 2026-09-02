<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$pending = max(0, (float) $bill['total_amount'] - (float) $bill['paid_amount']);
$taxComponents = json_decode((string) ($bill['tax_breakup_json'] ?? ''), true);
$taxComponents = is_array($taxComponents) ? $taxComponents : [];
?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div><span class="erp-eyebrow">Labour invoice</span><h4 class="mb-1"><?= esc((string) $bill['bill_no']) ?></h4><p class="mb-0"><?= esc((string) $bill['karigar_name']) ?> · <?= esc((string) $bill['bill_date']) ?></p></div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (! empty($bill['attachment_path'])): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/accounts/labour-bills/' . (int) $bill['id'] . '/attachment') ?>"><i class="fe fe-paperclip me-1"></i>Source Bill</a><?php endif; ?>
        <a class="btn btn-outline-primary" target="_blank" href="<?= site_url('api/documents/labour-bill/' . (int) $bill['id']) ?>?download=1"><i class="fe fe-download me-1"></i>Generated Bill</a>
        <a class="btn btn-light" href="<?= site_url('admin/accounts/labour-bills') ?>">Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['Taxable','taxable_amount',''],['GST','gst_amount',''],['Invoice Total','total_amount','primary'],['Outstanding',null,$pending > 0 ? 'danger' : 'success']] as $metric): ?>
        <?php $value = $metric[1] ? (float) $bill[$metric[1]] : $pending; ?>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted"><?= esc($metric[0]) ?></small><h4 class="mb-0 <?= $metric[2] ? 'text-' . $metric[2] : '' ?>">₹<?= number_format($value, 2) ?></h4></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="card-title mb-0">Included Job Works</h5></div>
    <div class="table-responsive">
        <table class="table datatable table-hover align-middle mb-0" data-dt-page-length="25">
            <thead><tr><th>Date</th><th>Order / Work</th><th>Source</th><th class="text-end">Gross</th><th class="text-end">Net</th><th class="text-end">Labour</th></tr></thead>
            <tbody><?php foreach ($jobworks as $job): ?><tr><td><?= esc((string) ($job['jobwork_date'] ?: '-')) ?></td><td><?php if (! empty($job['order_id'])): ?><a href="<?= site_url('admin/orders/' . (int) $job['order_id']) ?>"><?= esc((string) ($job['order_no'] ?: $job['description'])) ?></a><?php else: ?><?= esc((string) $job['description']) ?><?php endif; ?></td><td><span class="badge bg-light text-dark"><?= esc(ucwords(str_replace('_', ' ', (string) $job['jobwork_type']))) ?></span></td><td class="text-end"><?= number_format((float) $job['gross_weight_gm'], 3) ?> gm</td><td class="text-end"><?= number_format((float) $job['net_weight_gm'], 3) ?> gm</td><td class="text-end fw-semibold">₹<?= number_format((float) $job['labour_amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Tax & Payment Position</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><small class="text-muted d-block">GST Master</small><strong><?= esc((string) ($bill['gst_master_name'] ?: '-')) ?></strong></div>
            <?php foreach ($taxComponents as $component): ?><div class="col-md-2"><small class="text-muted d-block"><?= esc((string) ($component['name'] ?? 'Tax')) ?></small><strong><?= number_format((float) ($component['percentage'] ?? 0), 3) ?>% · ₹<?= number_format((float) ($component['amount'] ?? 0), 2) ?></strong></div><?php endforeach; ?>
            <?php if ($taxComponents === []): ?><div class="col-md-2"><small class="text-muted d-block">Tax</small><strong>No GST</strong></div><?php endif; ?>
            <div class="col-md-2"><small class="text-muted d-block">Round off</small><strong>₹<?= number_format((float) $bill['round_off_amount'], 2) ?></strong></div>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Payment Date</th><th>Reference</th><th>Notes</th><th class="text-end">Amount</th></tr></thead><tbody><?php if ($payments === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No payment has been entered for this bill.</td></tr><?php endif; ?><?php foreach ($payments as $payment): ?><tr><td><?= esc((string) $payment['payment_date']) ?></td><td><?= esc((string) ($payment['reference_no'] ?: '-')) ?></td><td><?= esc((string) ($payment['notes'] ?: '-')) ?></td><td class="text-end">₹<?= number_format((float) $payment['amount'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?= $this->endSection() ?>
