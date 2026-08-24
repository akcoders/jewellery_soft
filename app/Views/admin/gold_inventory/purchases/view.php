<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Gold purchase invoice</span>
        <h4 class="mb-1">Purchase #<?= (int) $purchase['id'] ?></h4>
        <p class="mb-0"><?= esc((string) ($purchase['resolved_supplier_name'] ?: 'Supplier')) ?> · <?= esc((string) ($purchase['invoice_no'] ?: 'No invoice number')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ((int) ($purchase['production_document_id'] ?? 0) <= 0): ?>
            <a href="<?= site_url('admin/gold-inventory/purchases/' . $purchase['id'] . '/edit') ?>" class="btn btn-outline-info">
                <i class="fe fe-edit"></i> Edit
            </a>
        <?php else: ?>
            <a href="<?= site_url('admin/accounts/production-document/' . (int) $purchase['production_document_id']) ?>" target="_blank" class="btn btn-outline-primary">
                <i class="fe fe-paperclip"></i> Open Invoice PDF
            </a>
        <?php endif; ?>
        <a href="<?= site_url('admin/gold-inventory/purchases') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card erp-detail-card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><strong>Date:</strong> <?= esc((string) $purchase['purchase_date']) ?></div>
            <div class="col-md-5"><strong>Supplier:</strong> <?= esc((string) ($purchase['resolved_supplier_name'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Invoice:</strong> <?= esc((string) ($purchase['invoice_no'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>GSTIN:</strong> <?= esc((string) ($purchase['supplier_gstin'] ?: '-')) ?></div>
            <div class="col-md-9"><strong>Address:</strong> <?= esc((string) ($purchase['supplier_address'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Phone:</strong> <?= esc((string) ($purchase['supplier_phone'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Email:</strong> <?= esc((string) ($purchase['supplier_email'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Place of Supply:</strong> <?= esc((string) ($purchase['place_of_supply'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Due Date:</strong> <?= esc((string) ($purchase['due_date'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Location:</strong> <?= esc((string) ($purchase['location_name'] ?? '-')) ?></div>
            <div class="col-md-9"><strong>Description:</strong> <?= esc((string) ($purchase['purchase_description'] ?: '-')) ?></div>
            <div class="col-md-3"><strong>Total Weight:</strong> <?= number_format((float) $totals['total_weight'], 3) ?> gm</div>
            <div class="col-md-3"><strong>Total Fine:</strong> <?= number_format((float) $totals['total_fine'], 3) ?> gm</div>
            <div class="col-md-3"><strong>Payment:</strong> <span class="badge <?= ($purchase['payment_status'] ?? 'Pending') === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc((string) ($purchase['payment_status'] ?? 'Pending')) ?></span></div>
            <div class="col-md-3"><strong>Paid Amount:</strong> ₹<?= number_format((float) ($purchase['paid_amount'] ?? 0), 2) ?></div>
            <div class="col-md-12"><strong>Notes:</strong> <?= esc((string) ($purchase['notes'] ?: '-')) ?></div>
        </div>
    </div>
</div>

<div class="card erp-finance-card mb-3">
    <div class="card-header"><h6 class="mb-0">Invoice Tax Summary</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2"><div class="small text-muted">Taxable</div><div class="fw-semibold">₹<?= number_format((float) ($purchase['taxable_amount'] ?? 0), 2) ?></div></div>
            <div class="col-md-2"><div class="small text-muted">CGST <?= $purchase['cgst_rate'] !== null ? '(' . number_format((float) $purchase['cgst_rate'], 3) . '%)' : '' ?></div><div class="fw-semibold">₹<?= number_format((float) ($purchase['cgst_amount'] ?? 0), 2) ?></div></div>
            <div class="col-md-2"><div class="small text-muted">SGST <?= $purchase['sgst_rate'] !== null ? '(' . number_format((float) $purchase['sgst_rate'], 3) . '%)' : '' ?></div><div class="fw-semibold">₹<?= number_format((float) ($purchase['sgst_amount'] ?? 0), 2) ?></div></div>
            <div class="col-md-2"><div class="small text-muted">IGST <?= $purchase['igst_rate'] !== null ? '(' . number_format((float) $purchase['igst_rate'], 3) . '%)' : '' ?></div><div class="fw-semibold">₹<?= number_format((float) ($purchase['igst_amount'] ?? 0), 2) ?></div></div>
            <div class="col-md-2"><div class="small text-muted">Round Off</div><div class="fw-semibold">₹<?= number_format((float) ($purchase['round_off_amount'] ?? 0), 2) ?></div></div>
            <div class="col-md-2"><div class="small text-muted">Invoice Total</div><div class="h5 mb-0">₹<?= number_format((float) ($purchase['invoice_total'] ?? $totals['total_value']), 2) ?></div></div>
        </div>
    </div>
</div>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Purity</th>
                        <th>Description</th>
                        <th>HSN/SAC</th>
                        <th>Color</th>
                        <th>Form</th>
                        <th>Weight (gm)</th>
                        <th>Fine (gm)</th>
                        <th>Rate/gm</th>
                        <th>Line Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($lines ?? []) === []): ?>
                        <tr><td colspan="9" class="text-center text-muted">No lines found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lines ?? []) as $line): ?>
                        <tr>
                            <td><?= esc((string) ($line['master_purity_code'] ?: $line['purity_code'] ?: 'NA')) ?></td>
                            <td><?= esc((string) ($line['description'] ?: '-')) ?></td>
                            <td><?= esc((string) ($line['hsn_sac'] ?: '-')) ?></td>
                            <td><?= esc((string) ($line['color_name'] ?? 'NA')) ?></td>
                            <td><?= esc((string) ($line['form_type'] ?? 'Raw')) ?></td>
                            <td><?= number_format((float) $line['weight_gm'], 3) ?></td>
                            <td><?= number_format((float) $line['fine_weight_gm'], 3) ?></td>
                            <td><?= number_format((float) $line['rate_per_gm'], 2) ?></td>
                            <td><?= number_format((float) $line['line_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
