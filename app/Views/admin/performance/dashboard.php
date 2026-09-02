<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
.performance-hero{background:linear-gradient(125deg,#26113f,#7f1734 56%,#bd7b18);border:0;border-radius:18px;color:#fff}.performance-hero .card-body{padding:28px}.performance-rule{align-items:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:12px;display:flex;gap:9px;padding:10px 13px}.performance-rule strong{font-size:18px}.performance-stat{border:1px solid #e7eaf0;border-radius:15px;box-shadow:0 9px 24px rgba(16,24,40,.04);height:100%}.performance-stat .card-body{padding:19px}.performance-stat small{color:#667085;font-weight:650}.performance-stat strong{color:#101828;display:block;font-size:26px;margin-top:4px}.score-pill{align-items:center;border-radius:999px;display:inline-flex;font-size:15px;font-weight:800;justify-content:center;min-width:64px;padding:7px 12px}.score-excellent{background:#dcfae6;color:#067647}.score-good{background:#e0f2fe;color:#026aa2}.score-watch{background:#fef0c7;color:#b54708}.score-risk{background:#fee4e2;color:#b42318}.metric-pair{display:flex;gap:6px;flex-wrap:wrap}.metric-badge{background:#f5f7fa;border:1px solid #e4e7ec;border-radius:8px;color:#344054;font-size:11px;font-weight:700;padding:5px 7px}.performance-table td{vertical-align:middle}.progress{background:#eef1f5;height:7px}.activity-row{border-bottom:1px solid #eef0f3;display:grid;gap:12px;grid-template-columns:100px minmax(180px,1fr) 170px 90px;padding:12px 0}.activity-row:last-child{border-bottom:0}.activity-title{color:#101828;font-weight:700}.activity-meta{color:#667085;font-size:12px}.empty-activity{color:#98a2b3;padding:18px;text-align:center}@media(max-width:767.98px){.performance-hero .card-body{padding:20px}.activity-row{grid-template-columns:1fr}.performance-filter .btn{width:100%}}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$rules = is_array($scoreRules ?? null) ? $scoreRules : [];
$statusClass = static function (float $score): string {
    if ($score >= 105) return 'score-excellent';
    if ($score >= 100) return 'score-good';
    if ($score >= 90) return 'score-watch';
    return 'score-risk';
};
?>
<div class="card performance-hero mb-4"><div class="card-body">
    <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4"><div><span class="text-uppercase fw-bold small opacity-75">People intelligence</span><h2 class="text-white mb-2 mt-1">Staff Performance</h2><p class="mb-0 opacity-75">Due-date based scores for non-admin staff. Every point is traceable to a task or assigned order follow-up.</p></div><a href="<?= site_url('admin/performance/tasks') ?>" class="btn btn-light"><i class="fe fe-check-square me-1"></i> Assign & Review Tasks</a></div>
    <div class="row g-2 mt-3">
        <div class="col-6 col-lg"><div class="performance-rule"><span>Start</span><strong><?= number_format((float) ($rules['base_score'] ?? 100), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Task on time</span><strong>+<?= number_format((float) ($rules['task_on_time'] ?? 2), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Task late</span><strong><?= number_format((float) ($rules['task_late_or_overdue'] ?? -2), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Follow-up on time</span><strong>+<?= number_format((float) ($rules['followup_on_time'] ?? 1), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Follow-up late</span><strong><?= number_format((float) ($rules['followup_late_or_overdue'] ?? -1), 0) ?></strong></div></div>
    </div>
</div></div>

<form method="get" class="card mb-4 performance-filter"><div class="card-body"><div class="row g-3 align-items-end">
    <div class="col-md-2"><label class="form-label">Year</label><input type="number" min="2025" max="2100" name="year" class="form-control" value="<?= esc((string) $year) ?>"></div>
    <div class="col-md-2"><label class="form-label">Month</label><select name="month" class="form-select"><?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= (int) $month === $m ? 'selected' : '' ?>><?= esc(date('F', mktime(0,0,0,$m,1))) ?></option><?php endfor; ?></select></div>
    <div class="col-md-5"><label class="form-label">Employee</label><select name="staff_id" class="form-select select2"><option value="0">All non-admin staff</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (int) ($staffId ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary" type="submit"><i class="fe fe-filter me-1"></i> Apply Period</button></div>
</div></div></form>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl"><div class="card performance-stat"><div class="card-body"><small>Scored Employees</small><strong><?= (int) ($totals['staff_count'] ?? 0) ?></strong></div></div></div>
    <div class="col-6 col-xl"><div class="card performance-stat"><div class="card-body"><small>Average Score</small><strong><?= number_format((float) ($totals['average_score'] ?? 0), 1) ?></strong></div></div></div>
    <div class="col-6 col-xl"><div class="card performance-stat"><div class="card-body"><small>On-time Rate</small><strong><?= number_format((float) ($totals['on_time_rate'] ?? 0), 1) ?>%</strong></div></div></div>
    <div class="col-6 col-xl"><div class="card performance-stat"><div class="card-body"><small>Pending Overdue</small><strong class="text-danger"><?= (int) ($totals['overdue_actions'] ?? 0) ?></strong></div></div></div>
    <div class="col-6 col-xl"><div class="card performance-stat"><div class="card-body"><small>Orders Missing Follower</small><strong class="text-warning"><?= (int) ($totals['unassigned_open_orders'] ?? 0) ?></strong></div></div></div>
</div>

<div class="card mb-4"><div class="card-header"><div><h5 class="card-title mb-1">Employee Scoreboard</h5><small class="text-muted">Admin and SuperAdmin accounts are excluded automatically.</small></div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover datatable performance-table mb-0"><thead><tr><th>Employee</th><th>Score</th><th>Points</th><th>Tasks</th><th>Order Follow-ups</th><th>On-time Rate</th><th>Overdue</th></tr></thead><tbody>
<?php foreach (($rows ?? []) as $row): ?><tr>
    <td><strong><?= esc((string) ($row['name'] ?? '-')) ?></strong><div class="small text-muted"><?= esc((string) ($row['role_label'] ?? 'Staff')) ?><br><?= esc((string) ($row['email'] ?? '')) ?></div></td>
    <td><span class="score-pill <?= $statusClass((float) ($row['score'] ?? 0)) ?>"><?= number_format((float) ($row['score'] ?? 0), 1) ?></span></td>
    <td><span class="badge bg-success-light text-success">+<?= number_format((float) ($row['points_earned'] ?? 0), 1) ?></span> <span class="badge bg-danger-light text-danger">-<?= number_format((float) ($row['points_lost'] ?? 0), 1) ?></span></td>
    <td><div class="metric-pair"><span class="metric-badge">On time <?= (int) ($row['task_on_time'] ?? 0) ?></span><span class="metric-badge">Late <?= (int) ($row['task_late'] ?? 0) ?></span></div></td>
    <td><div class="metric-pair"><span class="metric-badge">On time <?= (int) ($row['followup_on_time'] ?? 0) ?></span><span class="metric-badge">Late <?= (int) ($row['followup_late'] ?? 0) ?></span></div></td>
    <td><strong><?= number_format((float) ($row['on_time_rate'] ?? 0), 1) ?>%</strong><div class="progress mt-2"><div class="progress-bar bg-success" style="width:<?= min(100, max(0, (float) ($row['on_time_rate'] ?? 0))) ?>%"></div></div></td>
    <td><span class="badge <?= (int) ($row['overdue_actions'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' ?>"><?= (int) ($row['overdue_actions'] ?? 0) ?></span></td>
</tr><?php endforeach; ?></tbody></table></div></div></div>

<?php foreach (($rows ?? []) as $row): $events = $eventsByUser[(int) $row['id']] ?? []; ?>
<div class="card mb-3"><div class="card-header"><div><h6 class="mb-1"><?= esc((string) $row['name']) ?> · Point Audit</h6><small class="text-muted"><?= count($events) ?> actions in this period</small></div><span class="score-pill <?= $statusClass((float) ($row['score'] ?? 0)) ?>"><?= number_format((float) $row['score'], 1) ?></span></div><div class="card-body py-1">
<?php if ($events === []): ?><div class="empty-activity">No task or assigned follow-up falls in this period.</div><?php endif; ?>
<?php foreach ($events as $event): $delta=(float)($event['score_delta']??0); ?><div class="activity-row"><div><span class="badge <?= $event['type']==='Task' ? 'bg-primary-light text-primary' : 'bg-warning-light text-warning' ?>"><?= esc((string) $event['type']) ?></span></div><div><div class="activity-title"><?= esc((string) $event['title']) ?></div><div class="activity-meta"><?= esc((string) ($event['reference'] ?? '')) ?></div></div><div><div class="activity-meta">Due</div><?= esc(date('d M Y, h:i A', strtotime((string) $event['due_at']))) ?></div><div><span class="badge <?= $delta>0?'bg-success':($delta<0?'bg-danger':'bg-light text-dark border') ?>"><?= $delta>0?'+':'' ?><?= number_format($delta, 0) ?> point</span><?php if (!empty($event['proof_url'])): ?><div class="mt-1"><a href="<?= esc((string) $event['proof_url'], 'attr') ?>" target="_blank">View proof</a></div><?php endif; ?></div></div><?php endforeach; ?>
</div></div><?php endforeach; ?>
<?= $this->endSection() ?>
