<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>.task-form-shell{border-left:4px solid #be1825}.task-proof{border-radius:8px;height:44px;object-fit:cover;width:58px}.task-title{color:#101828;font-weight:750}.task-note{color:#667085;font-size:12px;line-height:1.45;max-width:420px}.task-status{border-radius:999px;font-size:11px;font-weight:750;padding:6px 9px}.task-table td{vertical-align:middle}</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar mb-4"><div><span class="erp-eyebrow">Staff accountability</span><h4 class="mb-1">Tasks & Completion Proof</h4><p class="mb-0">Assign a deadline. Staff complete it in the mobile app with mandatory proof.</p></div><a href="<?= site_url('admin/performance/dashboard') ?>" class="btn btn-outline-primary"><i class="fe fe-bar-chart-2 me-1"></i> Performance Dashboard</a></div>

<?php if (admin_can('performance.tasks.manage')): ?>
<div class="card task-form-shell mb-4"><div class="card-header"><h5 class="card-title mb-0">Assign New Task</h5></div><div class="card-body"><form method="post" action="<?= site_url('admin/performance/tasks') ?>"><?= csrf_field() ?><div class="row g-3">
    <div class="col-md-4"><label class="form-label">Assign To <span class="text-danger">*</span></label><select name="admin_user_id" class="form-select select2" required><option value="">Select non-admin staff</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (string) old('admin_user_id') === (string) $person['id'] ? 'selected':'' ?>><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-5"><label class="form-label">Task <span class="text-danger">*</span></label><input name="title" maxlength="160" class="form-control" required value="<?= esc((string) old('title')) ?>" placeholder="Clear action to be completed"></div>
    <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select"><?php foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $value=>$label): ?><option value="<?= $value ?>" <?= old('priority','normal')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Due Date & Time <span class="text-danger">*</span></label><input type="datetime-local" name="scheduled_at" class="form-control" required value="<?= esc((string) old('scheduled_at')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Instructions / Expected Proof</label><textarea name="note" class="form-control" rows="2" placeholder="What should be attached as proof?"><?= esc((string) old('note')) ?></textarea></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="fe fe-send me-1"></i> Assign</button></div>
</div></form></div></div>
<?php endif; ?>

<div class="card"><div class="card-header"><div><h5 class="card-title mb-1">Task Register</h5><small class="text-muted">On-time +2 · Late/overdue -2 · Cancelled tasks do not affect score.</small></div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover datatable task-table mb-0"><thead><tr><th>Task</th><th>Employee</th><th>Due</th><th>Priority</th><th>Status / Points</th><th>Proof</th><th>Assigned By</th><th>Action</th></tr></thead><tbody>
<?php foreach (($rows ?? []) as $row): $status=strtolower((string)($row['status']??'pending')); $overdue=(int)($row['is_done']??0)===0 && strtotime((string)$row['scheduled_at'])<time(); ?>
<tr>
    <td><div class="task-title"><?= esc((string) $row['title']) ?></div><div class="task-note"><?= esc((string) (($row['note']??'')?:'No extra instructions')) ?></div></td>
    <td><strong><?= esc((string)($row['assignee_name']??'-')) ?></strong><div class="small text-muted"><?= esc((string)($row['assignee_email']??'')) ?></div></td>
    <td><?= esc(date('d M Y',strtotime((string)$row['scheduled_at']))) ?><div class="small text-muted"><?= esc(date('h:i A',strtotime((string)$row['scheduled_at']))) ?></div></td>
    <td><span class="badge bg-light text-dark border text-capitalize"><?= esc((string)($row['priority']??'normal')) ?></span></td>
    <td><?php if($overdue): ?><span class="task-status bg-danger text-white">Overdue · -2</span><?php elseif($status==='completed_on_time'): ?><span class="task-status bg-success text-white">On time · +2</span><?php elseif($status==='completed_late'): ?><span class="task-status bg-warning text-dark">Late · -2</span><?php elseif($status==='cancelled'): ?><span class="task-status bg-light text-muted border">Cancelled · 0</span><?php else: ?><span class="task-status bg-primary-light text-primary">Pending</span><?php endif; ?><?php if(!empty($row['completed_at'])): ?><div class="small text-muted mt-1"><?= esc(date('d M, h:i A',strtotime((string)$row['completed_at']))) ?></div><?php endif; ?></td>
    <td><?php if(!empty($row['proof_path'])): ?><a href="<?= base_url(ltrim((string)$row['proof_path'],'/')) ?>" target="_blank"><img class="task-proof" src="<?= base_url(ltrim((string)$row['proof_path'],'/')) ?>" alt="Task proof"></a><div class="task-note mt-1"><?= esc((string)($row['proof_note']??'')) ?></div><?php else: ?><span class="text-muted">Awaiting proof</span><?php endif; ?></td>
    <td><?= esc((string)(($row['created_by_name']??'')?:'System')) ?></td>
    <td><?php if((int)($row['is_done']??0)===0 && admin_can('performance.tasks.manage')): ?><form method="post" action="<?= site_url('admin/performance/tasks/'.(int)$row['id'].'/cancel') ?>" onsubmit="return confirm('Cancel this task?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php else: ?>—<?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?= $this->endSection() ?>
