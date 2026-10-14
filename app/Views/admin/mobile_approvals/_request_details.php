<?php
$payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
$type = (string) ($row['request_type'] ?? '');
$status = (string) ($row['status'] ?? 'pending');
?>
<?php if ($type === 'issuement'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Karigar</small><strong class="d-block"><?= esc($payload['karigar_name'] ?? '-') ?></strong></div></div>
        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Warehouse</small><strong class="d-block"><?= esc($payload['location_name'] ?? '-') ?></strong></div></div>
        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Issue Date</small><strong class="d-block"><?= esc($payload['issue_date'] ?? '-') ?></strong></div></div>
        <div class="col-md-3"><div class="approval-material"><small class="text-muted">Purpose</small><strong class="d-block"><?= esc($payload['purpose'] ?? '-') ?></strong></div></div>
        <?php if (! empty($payload['stone_lines']) && ! empty($payload['stone_order_id'])): ?>
        <div class="col-md-6"><div class="approval-material"><small class="text-muted">Stone issued for order</small><strong class="d-block"><a href="<?= site_url('admin/orders/' . (int) $payload['stone_order_id']) ?>"><?= esc((string) (($payload['stone_order_no'] ?? '') ?: ('#' . (int) $payload['stone_order_id']))) ?></a></strong></div></div>
        <?php endif; ?>
    </div>
    <div class="row g-3">
    <?php foreach (['gold' => ['Gold','weight_gm','gm'], 'diamond' => ['Diamond','carat','ct'], 'stone' => ['Stone','qty','qty']] as $material => $meta): $lines = is_array($payload[$material . '_lines'] ?? null) ? $payload[$material . '_lines'] : []; if ($lines === []) continue; ?>
        <div class="col-lg-4"><div class="approval-material"><strong><?= esc($meta[0]) ?> · <?= count($lines) ?> line(s)</strong>
            <?php foreach ($lines as $i => $line): ?><div class="small border-top mt-2 pt-2"><strong><?= esc((string) ($line['display_name'] ?? ('Item #' . (int) ($line['item_id'] ?? 0)))) ?></strong><br>#<?= $i + 1 ?> · <?= esc(number_format((float) ($line[$meta[1]] ?? 0), 3)) ?> <?= esc($meta[2]) ?><?= isset($line['pcs']) ? ' · ' . (int) $line['pcs'] . ' pcs' : '' ?></div><?php endforeach; ?>
        </div></div>
    <?php endforeach; ?>
    </div>
<?php elseif ($type === 'followup'): $previous = is_array($payload['previous_followup'] ?? null) ? $payload['previous_followup'] : []; ?>
    <div class="row g-3">
        <div class="col-md-4"><div class="approval-material"><small class="text-muted">Order</small><strong class="d-block"><?= esc((string) ($payload['order_no'] ?? '-')) ?></strong></div></div>
        <div class="col-md-4"><div class="approval-material"><small class="text-muted">Proposed Stage</small><strong class="d-block"><?= esc((string) ($payload['stage'] ?? '-')) ?></strong></div></div>
        <div class="col-md-4"><div class="approval-material"><small class="text-muted">Next Follow-up</small><strong class="d-block"><?= ! empty($payload['next_followup_date']) ? esc(date('d M Y, h:i A', strtotime((string) $payload['next_followup_date']))) : 'Not scheduled' ?></strong></div></div>
        <div class="col-12"><div class="approval-material"><small class="text-muted">New Follow-up</small><strong class="d-block"><?= esc((string) ($payload['description'] ?? '-')) ?></strong></div></div>
        <div class="col-12"><div class="approval-material"><small class="text-muted">Previous Follow-up</small>
            <?php if ($previous !== []): ?><strong class="d-block"><?= esc((string) (($previous['stage'] ?? '') ?: '-')) ?> · <?= esc((string) (($previous['description'] ?? '') ?: '-')) ?></strong><span class="small text-muted"><?= ! empty($previous['followup_taken_on']) ? esc(date('d M Y, h:i A', strtotime((string) $previous['followup_taken_on']))) : '' ?><?= ! empty($previous['followup_taken_by_name']) ? ' · ' . esc((string) $previous['followup_taken_by_name']) : '' ?></span><?php else: ?><strong class="d-block">No previous follow-up recorded.</strong><?php endif; ?>
        </div></div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($payload as $key => $value): if (is_array($value) || (string) $value === '') continue; ?>
        <div class="col-md-4"><div class="approval-material"><small class="text-muted text-capitalize"><?= esc(str_replace('_', ' ', (string) $key)) ?></small><strong class="d-block"><?= esc((string) $value) ?></strong></div></div>
        <?php endforeach; ?>
        <?php if (is_array($payload['address'] ?? null)): ?><div class="col-12"><div class="approval-material"><small class="text-muted">Address</small><strong class="d-block"><?= esc(implode(', ', array_filter(array_map('strval', $payload['address'])))) ?></strong></div></div><?php endif; ?>
    </div>
    <?php if ($type === 'delivery_challan' && is_array($payload['items'] ?? null) && $payload['items'] !== []): ?>
    <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>#</th><th>Description</th><th>Type / Purity</th><th class="text-end">PCS</th><th class="text-end">Gross / Net</th><th class="text-end">Value</th></tr></thead><tbody>
        <?php foreach ($payload['items'] as $i => $line): if (! is_array($line)) continue; ?>
        <tr><td><?= (int) $i + 1 ?></td><td><?= esc((string) ($line['description'] ?? '-')) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($line['item_type'] ?? '-')))) ?> · <?= esc((string) (($line['purity'] ?? '') ?: '-')) ?></td><td class="text-end"><?= (int) ($line['pcs'] ?? 0) ?></td><td class="text-end"><?= number_format((float) ($line['gross_weight_gm'] ?? 0), 3) ?> / <?= number_format((float) ($line['net_weight_gm'] ?? 0), 3) ?> gm</td><td class="text-end">Rs. <?= number_format((float) ($line['value'] ?? 0), 2) ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
<?php endif; ?>
<?php if (! empty($row['attachment_url'])): ?><div class="mt-3"><small class="text-muted d-block mb-2">REQUEST IMAGE / ATTACHMENT</small><a href="<?= esc($row['attachment_url']) ?>" target="_blank" rel="noopener"><img class="approval-photo" src="<?= esc($row['attachment_url']) ?>" alt="Request attachment"></a></div><?php endif; ?>
<?php if ($status !== 'pending'): ?>
    <div class="alert alert-light border mt-3 mb-0">
        <strong><?= esc(ucfirst($status)) ?> by <?= esc($row['reviewed_by_name'] ?? '-') ?></strong>
        <?php if (! empty($row['reviewed_at'])): ?> · <?= esc(date('d M Y, h:i A', strtotime((string) $row['reviewed_at']))) ?><?php endif; ?>
        <?= ! empty($row['review_note']) ? ' · ' . esc($row['review_note']) : '' ?>
        <?= ! empty($row['result_reference']) ? ' · Reference ' . esc($row['result_reference']) : '' ?>
    </div>
<?php endif; ?>
