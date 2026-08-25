<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Gold inventory</span>
        <h4 class="mb-1">Gold Purchases</h4>
        <p class="mb-0">Supplier invoices, GST values, weights and payment position.</p>
    </div>
    <a href="<?= site_url('admin/gold-inventory/purchases/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Purchase
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
                <a href="<?= site_url('admin/gold-inventory/purchases') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Invoice</th>
                        <th>Weight</th>
                        <th>Taxable</th>
                        <th>GST</th>
                        <th>Invoice Total</th>
                        <th>Payment</th>
                        <th>PDF</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($purchases ?? []) === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No purchase records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($purchases ?? []) as $purchase): ?>
                        <tr>
                            <td><?= (int) $purchase['id'] ?></td>
                            <td><?= esc((string) $purchase['purchase_date']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc((string) ($purchase['resolved_supplier_name'] ?? $purchase['supplier_name'] ?? '-')) ?></div>
                                <?php if (! empty($purchase['supplier_gstin'])): ?><div class="small text-muted">GSTIN: <?= esc((string) $purchase['supplier_gstin']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <div><?= esc((string) ($purchase['invoice_no'] ?? '-')) ?></div>
                                <div class="small text-muted"><?= esc((string) ($purchase['location_name'] ?? '-')) ?></div>
                            </td>
                            <td><?= number_format((float) $purchase['total_weight'], 3) ?> gm</td>
                            <td>₹<?= number_format((float) ($purchase['taxable_amount'] ?? $purchase['total_value']), 2) ?></td>
                            <td>₹<?= number_format((float) ($purchase['gst_amount'] ?? 0), 2) ?></td>
                            <td class="fw-semibold">₹<?= number_format((float) ($purchase['invoice_total'] ?? $purchase['total_value']), 2) ?></td>
                            <td>
                                <?php $status = (string) ($purchase['payment_status'] ?? 'Pending'); ?>
                                <span class="badge <?= $status === 'Paid' ? 'bg-success' : ($status === 'Partial' ? 'bg-info text-dark' : 'bg-warning text-dark') ?>"><?= esc($status) ?></span>
                            </td>
                            <td>
                                <?php if ((int) ($purchase['production_document_id'] ?? 0) > 0): ?>
                                    <a href="<?= site_url('admin/accounts/production-document/' . (int) $purchase['production_document_id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fe fe-paperclip"></i></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= site_url('admin/gold-inventory/purchases/view/' . $purchase['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                                    <?php if ((int) ($purchase['production_document_id'] ?? 0) <= 0): ?>
                                        <a href="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/edit') ?>" class="btn btn-sm btn-outline-info"><i class="fe fe-edit"></i></a>
                                        <form method="post" action="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/delete') ?>" onsubmit="return confirm('Delete this purchase?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-trash-2"></i></button>
                                        </form>
                                    <?php endif; ?>
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
