<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div class="erp-toolbar-copy"><span class="erp-eyebrow">Diamond traceability</span><h4>Diamond Bags</h4><p>Physical packet balance, order allocations and studding trail in one place.</p></div>
    <div class="d-flex gap-2"><a href="<?= site_url('admin/diamond-inventory/shape-sizes') ?>" class="btn btn-outline-primary">Shape &amp; Size Master</a><a href="<?= site_url('admin/diamond-inventory/bags/create') ?>" class="btn btn-primary"><i class="fe fe-plus me-1"></i>Prepare Bag</a></div>
</div>
<div class="card"><div class="table-responsive"><table class="table datatable align-middle mb-0"><thead><tr><th>Bag</th><th>Prepared</th><th>Lots</th><th>Available</th><th>Order Allocations</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach (($bags ?? []) as $bag): ?>
<tr>
    <td><a class="fw-bold" href="<?= site_url('admin/diamond-inventory/bags/' . (int) $bag['id']) ?>"><?= esc((string) $bag['bag_no']) ?></a></td>
    <td><?= esc((string) (($bag['prepared_date'] ?? '') ?: substr((string) ($bag['created_at'] ?? ''), 0, 10))) ?></td>
    <td><span class="badge bg-light text-dark"><?= number_format((int) ($bag['item_count'] ?? 0)) ?> size row(s)</span></td>
    <td><strong><?= number_format((float) ($bag['pcs_balance'] ?? 0), 0) ?> pcs</strong><div class="small text-muted"><?= number_format((float) ($bag['cts_balance'] ?? 0), 3) ?> cts</div></td>
    <td><?= number_format((int) ($bag['order_count'] ?? 0)) ?> order(s)</td>
    <td><?php if ((float) ($bag['cts_balance'] ?? 0) <= .0005): ?><span class="badge bg-secondary">Consumed</span><?php elseif ((int) ($bag['issue_line_count'] ?? 0) > 0): ?><span class="badge bg-warning text-dark">Partly Issued</span><?php else: ?><span class="badge bg-success">Ready</span><?php endif; ?></td>
    <td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/diamond-inventory/bags/' . (int) $bag['id']) ?>"><i class="fe fe-eye"></i></a><?php if ((int) ($bag['issue_line_count'] ?? 0) === 0): ?><a class="btn btn-sm btn-outline-warning" href="<?= site_url('admin/diamond-inventory/bags/' . (int) $bag['id'] . '/edit') ?>"><i class="fe fe-edit"></i></a><?php endif; ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
