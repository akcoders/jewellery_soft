<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<style>
.dc-hero{background:linear-gradient(135deg,#351528 0%,#6d2940 62%,#a77522 130%);border:0;color:#fff;overflow:hidden;position:relative}.dc-hero:after{content:'DC';position:absolute;right:24px;top:-35px;font:900 150px Georgia;color:rgba(255,255,255,.06)}.dc-hero .btn{background:#e7bd68;border:0;color:#351528;font-weight:800}.dc-no{font-family:Georgia,serif;color:#5b2338;font-weight:800}.dc-chip{display:inline-flex;padding:5px 10px;border-radius:999px;background:#fff6df;color:#805b17;font-size:12px;font-weight:700}
</style>
<div class="card dc-hero mb-4"><div class="card-body p-4 p-lg-5 position-relative"><div class="row align-items-center"><div class="col"><div class="text-uppercase small fw-bold mb-2" style="letter-spacing:2px;color:#e7bd68">Dispatch Documents</div><h2 class="mb-2 text-white">Delivery Challans</h2><p class="mb-0 text-white-50">Independent challans for ornaments, loose diamonds and loose metal.</p></div><div class="col-auto"><a href="<?= site_url('admin/delivery-challans/create') ?>" class="btn btn-lg"><i class="fe fe-plus me-1"></i>Create Challan</a></div></div></div></div>

<div class="card"><div class="card-header"><h5 class="mb-0">Challan Register</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Challan</th><th>Date</th><th>Dispatch From</th><th>Customer</th><th>Contents</th><th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Gross Total</th><th></th></tr></thead><tbody>
<?php if (($challans ?? []) === []): ?><tr><td colspan="9" class="text-center py-5 text-muted">No delivery challan created yet.</td></tr><?php endif; ?>
<?php foreach (($challans ?? []) as $row): ?><tr>
<td><span class="dc-no"><?= esc((string) ($row['challan_no'] ?? '-')) ?></span><?php if (! empty($row['order_no'])): ?><div class="small text-muted">Order <?= esc((string) $row['order_no']) ?></div><?php endif; ?></td>
<td><?= ! empty($row['challan_date']) ? date('d M Y', strtotime((string) $row['challan_date'])) : '-' ?></td>
<td><?= esc((string) (($row['dispatch_from'] ?? '') ?: 'Company')) ?></td>
<td><strong><?= esc((string) (($row['display_customer'] ?? '') ?: 'Customer')) ?></strong><div class="small text-muted"><?= esc((string) ($row['customer_gstin'] ?? '')) ?></div></td>
<td><span class="dc-chip"><?= esc((string) (($row['material_types'] ?? '') ?: 'Ornament')) ?></span><div class="small text-muted mt-1"><?= (int) ($row['total_pcs'] ?? 0) ?> pcs</div></td>
<td class="text-end">₹<?= number_format((float) ($row['taxable_value'] ?? 0), 2) ?></td><td class="text-end"><?= number_format((float) ($row['tax_percent'] ?? 0), 2) ?>%<div class="small text-muted">₹<?= number_format((float) ($row['tax_amount'] ?? 0), 2) ?></div></td><td class="text-end fw-bold">₹<?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= site_url('admin/delivery-challans/' . (int) $row['id'] . '/pdf') ?>"><i class="fe fe-file-text me-1"></i>PDF</a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?= $this->endSection() ?>
