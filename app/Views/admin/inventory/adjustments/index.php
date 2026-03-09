<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Inventory Adjustments</h4>
    <a href="<?= site_url('admin/inventory/adjustments/create') ?>" class="btn btn-primary">
        <i class="fe fe-plus"></i> Create Adjustment
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Voucher</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Warehouse</th>
                        <th>Bin</th>
                        <th>Item Type</th>
                        <th>Material</th>
                        <th>PCS</th>
                        <th>Weight (gm)</th>
                        <th>CTS</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($adjustments ?? []) === []): ?>
                        <tr><td colspan="11" class="text-center text-muted">No adjustments found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($adjustments ?? []) as $tx): ?>
                        <tr>
                            <td><?= esc((string) ($tx['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['txn_date'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['transaction_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['location_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['bin_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['item_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['material_name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['pcs'] ?? 0)) ?></td>
                            <td><?= esc(number_format((float) ($tx['weight_gm'] ?? 0), 3)) ?></td>
                            <td><?= esc(number_format((float) ($tx['cts'] ?? 0), 3)) ?></td>
                            <td><?= esc((string) ($tx['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
