<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$statusMeta = static fn(string $status): array => match ($status) {
    'pending_approval' => ['Pending approval', 'warning'],
    'assigned' => ['Bag assigned', 'info'],
    'bag_ready' => ['Bag ready', 'success'],
    'issued' => ['Issued', 'primary'],
    'rejected' => ['Rejected', 'danger'],
    default => [ucwords(str_replace('_', ' ', $status)), 'secondary'],
};
?>
<div class="erp-page-shell">
    <div class="erp-toolbar mb-4">
        <div class="erp-toolbar-copy"><span class="erp-eyebrow">Diamond workflow</span><h4>Diamond Requirements</h4><p>Approve order requirements, assign bag preparation and trace the ready bag.</p></div>
        <a href="<?= site_url('admin/diamond-inventory/bags') ?>" class="btn btn-outline-primary"><i class="fe fe-package me-1"></i>Diamond Bags</a>
    </div>
    <div class="card erp-table-card">
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0" id="diamondRequirementTable">
            <thead><tr><th>Requirement</th><th>Order</th><th>Raised By</th><th>Status</th><th>Assigned To</th><th>Due / Ready</th><th>Bag</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach (($requirements ?? []) as $row): ?>
                <?php [$statusLabel, $statusClass] = $statusMeta((string) ($row['status'] ?? '')); ?>
                <tr>
                    <td><strong><?= esc((string) $row['requirement_no']) ?></strong><div class="small text-muted"><?= esc((string) (($row['requirement_note'] ?? '') ?: 'No note')) ?></div></td>
                    <td><a href="<?= site_url('admin/orders/' . (int) $row['order_id']) ?>" class="fw-semibold"><?= esc((string) $row['order_no']) ?></a><div class="small text-muted"><?= esc((string) (($row['order_name'] ?? '') ?: ($row['order_category_name'] ?? ''))) ?></div></td>
                    <td><?= esc((string) (($row['requester_name'] ?? '') ?: '-')) ?><div class="small text-muted"><?= ! empty($row['created_at']) ? date('d M Y, h:i A', strtotime((string) $row['created_at'])) : '-' ?></div></td>
                    <td><span class="badge bg-<?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
                    <td><?= esc((string) (($row['assignee_name'] ?? '') ?: 'Not assigned')) ?></td>
                    <td><?= ! empty($row['ready_at']) ? 'Ready ' . date('d M, h:i A', strtotime((string) $row['ready_at'])) : (! empty($row['preparation_due_at']) ? date('d M Y, h:i A', strtotime((string) $row['preparation_due_at'])) : (! empty($row['required_by']) ? date('d M Y', strtotime((string) $row['required_by'])) : '-')) ?></td>
                    <td><?php if (! empty($row['bag_id'])): ?><a href="<?= site_url('admin/diamond-inventory/bags/' . (int) $row['bag_id']) ?>" class="badge bg-light text-dark border"><?= esc((string) $row['bag_no']) ?></a><div class="small text-muted"><?= number_format((float) ($row['pcs_balance'] ?? 0), 0) ?> pcs · <?= number_format((float) ($row['cts_balance'] ?? 0), 3) ?> cts</div><?php else: ?>—<?php endif; ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= site_url('admin/diamond-inventory/requirements/' . (int) $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fe fe-eye"></i></a>
                        <?php if (($row['status'] ?? '') === 'pending_approval'): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#approveRequirement<?= (int) $row['id'] ?>">Approve &amp; Assign</button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (($requirements ?? []) === []): ?><tr><td colspan="8" class="text-center text-muted py-5">No diamond requirements have been raised.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
</div>

<?php foreach (($requirements ?? []) as $row): ?><?php if (($row['status'] ?? '') === 'pending_approval'): ?>
<div class="modal fade" id="approveRequirement<?= (int) $row['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="<?= site_url('admin/diamond-inventory/requirements/' . (int) $row['id'] . '/approve') ?>"><?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Approve <?= esc((string) $row['requirement_no']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="alert alert-light border">Order <strong><?= esc((string) $row['order_no']) ?></strong><br><?= esc((string) (($row['requirement_note'] ?? '') ?: 'No requirement note')) ?></div>
        <div class="mb-3"><label class="form-label">Assign bag preparation to *</label><select name="assigned_to" class="form-select" required><option value="">Select staff</option><?php foreach (($staff ?? []) as $user): ?><option value="<?= (int) $user['id'] ?>"><?= esc((string) $user['name']) ?> · <?= esc((string) $user['email']) ?></option><?php endforeach; ?></select></div>
        <?php $defaultDue = ! empty($row['required_by']) ? (string) $row['required_by'] . 'T18:00' : date('Y-m-d\T18:00', strtotime('+1 day')); ?>
        <div class="mb-3"><label class="form-label">Preparation due date &amp; time *</label><input type="datetime-local" name="preparation_due_at" class="form-control" required value="<?= esc($defaultDue) ?>"><div class="form-text">The assigned staff task must be completed by this time.</div></div>
        <div><label class="form-label">Approval note</label><textarea name="approval_note" class="form-control" rows="3"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Approve &amp; Notify</button></div>
</form></div></div>
<?php endif; ?><?php endforeach; ?>
<?= $this->endSection() ?>
