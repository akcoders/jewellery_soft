<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$query = static function (array $overrides = []) use ($filters): string {
    return http_build_query(array_filter(array_merge($filters, $overrides), static fn ($value): bool => $value !== '' && $value !== null));
};
$lastPage = max(1, (int) ceil($total / $perPage));
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><h4 class="mb-1">Arattai Message Audit</h4><div class="text-muted">What was queued, why it was sent, API result and delivery/failure reason.</div></div>
    <a href="<?= site_url('admin/company-settings') ?>#arattai-test" class="btn btn-outline-primary">Arattai Settings &amp; Test</a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['All', $summary['all'], 'primary'], ['Queued', $summary['pending'], 'warning'], ['API accepted', $summary['sent'], 'info'], ['Delivered', $summary['delivered'], 'success'], ['Failed', $summary['failed'], 'danger']] as [$label, $value, $color]): ?>
        <div class="col-6 col-md"><div class="card h-100"><div class="card-body py-3"><div class="small text-muted"><?= esc($label) ?></div><div class="fs-4 fw-semibold text-<?= esc($color) ?>"><?= (int) $value ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Search</label><input class="form-control" name="q" value="<?= esc($filters['q']) ?>" placeholder="Message no, recipient, message or error"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All statuses</option>
        <?php foreach (['pending' => 'Queued', 'sent' => 'API accepted', 'delivered' => 'Delivered', 'failed' => 'Failed'] as $value => $label): ?><option value="<?= esc($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><label class="form-label">Reason / event</label><select class="form-select" name="event"><option value="">All events</option>
        <?php foreach ($events as $value): ?><option value="<?= esc((string) $value) ?>" <?= $filters['event'] === $value ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', (string) $value))) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?= site_url('admin/arattai-messages') ?>">Reset</a></div>
</form></div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Message</th><th>Why sent</th><th>Recipient</th><th>Content / image</th><th>Status</th><th>Reason</th><th>Time</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-5">No Arattai message records found.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <?php $status = (string) ($row['status'] ?? 'pending'); $delivery = (string) ($row['delivery_status'] ?? ''); $badge = $status === 'failed' || $delivery === 'failed' ? 'danger' : ($delivery === 'delivered' ? 'success' : ($status === 'sent' ? 'info' : 'warning')); ?>
        <tr>
            <td><code><?= esc((string) ($row['message_no'] ?? '')) ?></code><div class="small text-muted">#<?= (int) ($row['id'] ?? 0) ?> · <?= esc((string) ($row['event_key'] ?? '-')) ?></div></td>
            <td><?= esc((string) ($row['event_label'] ?? '')) ?><?php if (! empty($row['order_id'])): ?><div class="small text-muted">Order ID: <?= (int) $row['order_id'] ?></div><?php endif; ?></td>
            <td><code><?= esc((string) ($row['recipient_number'] ?? '')) ?></code><div class="small text-muted"><?= esc((string) ($row['recipient_name'] ?? '')) ?></div></td>
            <td style="min-width:260px"><div class="small" style="white-space:pre-line;max-height:90px;overflow:auto"><?= esc((string) ($row['message_text'] ?? '')) ?></div><?php if (! empty($row['media_url'])): ?><a class="small" href="<?= esc((string) $row['media_url']) ?>" target="_blank" rel="noopener">Open attached image</a><?php else: ?><span class="small text-muted">No image (expected for points)</span><?php endif; ?></td>
            <td><span class="badge bg-<?= esc($badge) ?>"><?= esc($delivery !== '' ? $delivery : $status) ?></span><div class="small text-muted">HTTP <?= esc((string) ($row['http_status_code'] ?? '-')) ?> · attempts <?= (int) ($row['attempts'] ?? 0) ?>/<?= (int) ($row['max_attempts'] ?? 0) ?></div></td>
            <td style="min-width:240px"><div class="small <?= $badge === 'danger' ? 'text-danger' : 'text-muted' ?>"><?= esc((string) ($row['failure_reason'] ?? '')) ?></div></td>
            <td class="small text-nowrap"><?= esc((string) (($row['sent_at'] ?? '') ?: ($row['last_attempt_at'] ?? '') ?: ($row['created_at'] ?? ''))) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($lastPage > 1): ?><div class="card-footer d-flex justify-content-between align-items-center"><span class="small text-muted"><?= (int) $total ?> records · page <?= (int) $page ?>/<?= $lastPage ?></span><div class="btn-group"><a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= esc($query(['page' => max(1, $page - 1)])) ?>">Previous</a><a class="btn btn-sm btn-outline-secondary <?= $page >= $lastPage ? 'disabled' : '' ?>" href="?<?= esc($query(['page' => min($lastPage, $page + 1)])) ?>">Next</a></div></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
