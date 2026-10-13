<?php
$bag = is_array($bag ?? null) ? $bag : [];
$items = is_array($items ?? null) ? $items : [];
$movements = is_array($movements ?? null) ? $movements : [];
?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <span class="badge bg-success-subtle text-success mb-2">Issued diamond bag</span>
        <h4 class="mb-1"><?= esc((string) ($bag['bag_no'] ?? 'Diamond Bag')) ?></h4>
        <div class="text-muted"><?= esc((string) (($bag['prepared_date'] ?? '') ?: '-')) ?> · <?= number_format((float) ($bag['pcs_balance'] ?? 0), 0) ?> PCS / <?= number_format((float) ($bag['cts_balance'] ?? 0), 3) ?> CTS currently available</div>
    </div>
    <?php if (! empty($bag['audit_image_path'])): ?>
        <a href="<?= esc(base_url((string) $bag['audit_image_path']), 'attr') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fe fe-image me-1"></i>Open bag photo</a>
    <?php endif; ?>
</div>

<div class="card border mb-4">
    <div class="card-header"><h6 class="mb-0">Calibrated Contents</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-dt-skip="true">
            <thead><tr><th>Diamond</th><th>Shape</th><th>Size / Chalni</th><th>Total</th><th>Available</th><th>Issued / Consumed</th></tr></thead>
            <tbody>
            <?php if ($items === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No calibrated rows found.</td></tr><?php endif; ?>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><strong><?= esc((string) (($row['diamond_type'] ?? '') ?: 'Diamond')) ?></strong><div class="small text-muted"><?= esc(trim((string) (($row['color'] ?? '') ?: '-') . ' / ' . (string) (($row['clarity'] ?? '') ?: '-'))) ?></div></td>
                    <td><?= esc((string) (($row['shape_name'] ?? '') ?: '-')) ?></td>
                    <td><strong><?= esc((string) (($row['size_label'] ?? '') ?: ($row['size'] ?? '-'))) ?></strong><?php if (! empty($row['chalni_group_name'])): ?><div class="small text-muted"><?= esc((string) $row['chalni_group_name']) ?><?= ! empty($row['chalni_group_range']) ? ' · ' . esc((string) $row['chalni_group_range']) : '' ?></div><?php endif; ?></td>
                    <td><?= number_format((float) ($row['pcs_total'] ?? 0), 0) ?> PCS<div class="small text-muted"><?= number_format((float) ($row['weight_cts_total'] ?? 0), 3) ?> CTS</div></td>
                    <td class="text-success"><?= number_format((float) ($row['pcs_available'] ?? 0), 0) ?> PCS<div><?= number_format((float) ($row['weight_cts_available'] ?? 0), 3) ?> CTS</div></td>
                    <td><?= number_format((float) ($row['pcs_total'] ?? 0) - (float) ($row['pcs_available'] ?? 0), 0) ?> PCS<div class="small text-muted"><?= number_format((float) ($row['weight_cts_total'] ?? 0) - (float) ($row['weight_cts_available'] ?? 0), 3) ?> CTS</div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border mb-0">
    <div class="card-header"><h6 class="mb-0">Issue → Order → Studding Trail</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-dt-skip="true">
            <thead><tr><th>Date</th><th>Action</th><th>Diamond / Size</th><th>Voucher</th><th>Order</th><th>Karigar</th><th>PCS</th><th>CTS</th></tr></thead>
            <tbody>
            <?php if ($movements === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No movement history found.</td></tr><?php endif; ?>
            <?php foreach ($movements as $movement): $type = strtoupper((string) ($movement['movement_type'] ?? '')); ?>
                <tr>
                    <td><?= esc((string) ($movement['movement_date'] ?? '-')) ?></td>
                    <td><span class="badge <?= $type === 'STUDDED' ? 'bg-success' : ($type === 'RETURN' ? 'bg-info' : 'bg-warning text-dark') ?>"><?= esc($type ?: '-') ?></span></td>
                    <td><strong><?= esc((string) (($movement['diamond_type'] ?? '') ?: '-')) ?></strong><div class="small text-muted"><?= esc(trim((string) (($movement['shape_name'] ?? '') ?: '-') . ' / ' . (string) (($movement['size_label'] ?? '') ?: ($movement['chalni_group_name'] ?? '-')))) ?></div></td>
                    <td><?= esc((string) (($movement['voucher_no'] ?? '') ?: '-')) ?></td>
                    <td><?= esc((string) (($movement['order_no'] ?? '') ?: 'Unallocated')) ?></td>
                    <td><?= esc((string) (($movement['karigar_name'] ?? '') ?: '-')) ?></td>
                    <td><?= number_format((float) ($movement['pcs'] ?? 0), 0) ?></td>
                    <td><?= number_format((float) ($movement['carat'] ?? 0), 3) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (! empty($bag['notes'])): ?><div class="alert alert-light border mt-3 mb-0"><strong>Bag note:</strong> <?= esc((string) $bag['notes']) ?></div><?php endif; ?>
