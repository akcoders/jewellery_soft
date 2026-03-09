<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Inventory Transactions</h4>
    <a href="<?= site_url('admin/inventory/adjustments/create') ?>" class="btn btn-outline-primary">
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
                        <th>Date Time</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Item Type</th>
                        <th>Material</th>
                        <th>Batch/Packet</th>
                        <th>PCS</th>
                        <th>gm</th>
                        <th>cts</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($transactions ?? []) === []): ?>
                        <tr><td colspan="13" class="text-center text-muted">No transactions found.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($transactions ?? []) as $tx): ?>
                        <?php
                        $fromWarehouse = (string) ($tx['from_warehouse_name'] ?? '');
                        $toWarehouse = (string) ($tx['to_warehouse_name'] ?? '');
                        $fromBin = (string) ($tx['from_bin_name'] ?? '');
                        $toBin = (string) ($tx['to_bin_name'] ?? '');
                        $fromText = trim($fromWarehouse . ($fromBin !== '' ? ' / ' . $fromBin : ''));
                        $toText = trim($toWarehouse . ($toBin !== '' ? ' / ' . $toBin : ''));
                        if ($fromText === '') {
                            $fromText = (string) ($tx['location_name'] ?: '-');
                        }
                        if ($toText === '') {
                            $toText = (string) ($tx['counter_location_name'] ?: '-');
                        }
                        $batchText = trim((string) (($tx['lot_no'] ?? '') !== '' ? 'LOT: ' . $tx['lot_no'] : '') . ' ' . (($tx['packet_no'] ?? '') !== '' ? 'PKT: ' . $tx['packet_no'] : ''));
                        if ($batchText === '') {
                            $batchText = '-';
                        }
                        ?>
                        <tr>
                            <td><?= esc((string) ($tx['voucher_no'] ?? '-')) ?></td>
                            <td><?= esc((string) (($tx['txn_datetime'] ?? '') !== '' ? $tx['txn_datetime'] : ($tx['txn_date'] ?? '-'))) ?></td>
                            <td><?= esc((string) ($tx['transaction_type'] ?? '-')) ?></td>
                            <td><?= esc($fromText) ?></td>
                            <td><?= esc($toText) ?></td>
                            <td><?= esc((string) ($tx['item_type'] ?? '-')) ?></td>
                            <td><?= esc((string) ($tx['material_name'] ?? '-')) ?></td>
                            <td><?= esc($batchText) ?></td>
                            <td><?= esc((string) ($tx['pcs'] ?? 0)) ?></td>
                            <td><?= esc(number_format((float) ($tx['weight_gm'] ?? 0), 3)) ?></td>
                            <td><?= esc(number_format((float) ($tx['cts'] ?? 0), 3)) ?></td>
                            <td>
                                <?php
                                $refLabel = (string) (($tx['document_type'] ?? '') !== '' ? $tx['document_type'] : (($tx['reference_type'] ?? '') !== '' ? $tx['reference_type'] : '-'));
                                $refSuffix = (string) (($tx['document_no'] ?? '') !== '' ? (' #' . $tx['document_no']) : ((isset($tx['reference_id']) && $tx['reference_id'] !== null) ? (' #' . $tx['reference_id']) : ''));
                                ?>
                                <?= esc($refLabel . $refSuffix) ?>
                            </td>
                            <td><?= esc((string) ($tx['notes'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
