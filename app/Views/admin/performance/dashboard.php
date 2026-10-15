<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
.performance-hero{background:linear-gradient(125deg,#26113f,#7f1734 56%,#bd7b18);border:0;border-radius:18px;color:#fff}.performance-hero .card-body{padding:28px}.performance-rule{align-items:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:12px;display:flex;gap:9px;padding:10px 13px}.performance-rule strong{font-size:18px}.performance-stat,.analysis-card,.chart-card{border:1px solid #e7eaf0;border-radius:15px;box-shadow:0 9px 24px rgba(16,24,40,.04);height:100%}.performance-stat .card-body,.analysis-card .card-body{padding:19px}.performance-stat small{color:#667085;font-weight:650}.performance-stat strong{color:#101828;display:block;font-size:26px;margin-top:4px}.stat-note{color:#98a2b3;font-size:11px;margin-top:3px}.score-pill,.band-pill{align-items:center;border-radius:999px;display:inline-flex;font-size:13px;font-weight:800;justify-content:center;padding:7px 12px}.score-pill{font-size:15px;min-width:64px}.score-excellent{background:#dcfae6;color:#067647}.score-good{background:#e0f2fe;color:#026aa2}.score-watch{background:#fef0c7;color:#b54708}.score-risk{background:#fee4e2;color:#b42318}.metric-pair{display:flex;gap:6px;flex-wrap:wrap}.metric-badge{background:#f5f7fa;border:1px solid #e4e7ec;border-radius:8px;color:#344054;font-size:11px;font-weight:700;padding:5px 7px}.quality-line{margin-bottom:16px}.quality-line:last-child{margin-bottom:0}.quality-label{display:flex;justify-content:space-between;margin-bottom:6px}.quality-label span{color:#667085;font-size:12px}.attention-item{align-items:flex-start;border-bottom:1px solid #eef0f3;display:flex;gap:12px;padding:10px 0}.attention-item:last-child{border-bottom:0}.attention-icon{align-items:center;background:#fef0c7;border-radius:10px;color:#b54708;display:flex;height:34px;justify-content:center;min-width:34px}.chart-box{min-height:310px}.performance-table td,.audit-table td{vertical-align:middle}.performance-table .analysis-summary{color:#667085;font-size:12px;max-width:270px}.audit-reason{color:#667085;font-size:12px;max-width:320px;white-space:normal}.progress{background:#eef1f5;height:7px}.empty-activity{color:#98a2b3;padding:18px;text-align:center}@media(max-width:767.98px){.performance-hero .card-body{padding:20px}.performance-filter .btn{width:100%}.chart-box{min-height:260px}}
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
$bandClass = static function (string $band): string {
    return match ($band) {
        'Excellent', 'Strong' => 'score-excellent',
        'No activity' => 'bg-light text-dark border',
        'Needs attention', 'Schedule risk' => 'score-watch',
        default => 'score-risk',
    };
};
$chartJson = static fn(mixed $value): string => (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$followupEventTypes = ['Follow-up', 'Approved follow-up', 'Disapproved follow-up'];
?>
<div class="card performance-hero mb-4"><div class="card-body">
    <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4"><div><span class="text-uppercase fw-bold small opacity-75">People intelligence</span><h2 class="text-white mb-2 mt-1">Staff Performance</h2><p class="mb-0 opacity-75">Simple scoring for approvals, delays, tasks and business entries.</p></div><a href="<?= site_url('admin/performance/tasks') ?>" class="btn btn-light"><i class="fe fe-check-square me-1"></i> Assign & Review Tasks</a></div>
    <div class="row g-2 mt-3">
        <div class="col-6 col-lg"><div class="performance-rule"><span>Start</span><strong><?= number_format((float) ($rules['base_score'] ?? 100), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Task on time</span><strong>+<?= number_format((float) ($rules['task_on_time'] ?? 2), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Task late / day</span><strong><?= number_format((float) ($rules['task_delay_per_day'] ?? -2), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Follow-up late / day</span><strong><?= number_format((float) ($rules['followup_delay_per_day'] ?? -2), 0) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Order entry</span><strong>+<?= number_format((float) ($rules['order_entry'] ?? .25), 2) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Approved follow-up</span><strong>+<?= number_format((float) ($rules['approved_followup'] ?? 2), 1) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Approved · no point</span><strong>0</strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Disapproved follow-up</span><strong><?= number_format((float) ($rules['disapproved_followup'] ?? -2), 1) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Approval pending</span><strong>No delay</strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Rating above 3</span><strong>+<?= number_format((float) ($rules['rating_point_step'] ?? 5), 0) ?>/star</strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Rating below 3</span><strong>-<?= number_format((float) ($rules['rating_point_step'] ?? 5), 0) ?>/star</strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Issuement</span><strong>+<?= number_format((float) ($rules['issuement'] ?? .25), 2) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Return</span><strong>+<?= number_format((float) ($rules['return'] ?? .25), 2) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Receiving</span><strong>+<?= number_format((float) ($rules['receiving'] ?? .25), 2) ?></strong></div></div>
        <div class="col-6 col-lg"><div class="performance-rule"><span>Purchase</span><strong>+<?= number_format((float) ($rules['purchase'] ?? .25), 2) ?></strong></div></div>
    </div>
</div></div>

<form method="get" class="card mb-4 performance-filter"><div class="card-body"><div class="row g-3 align-items-end">
    <div class="col-md-2"><label class="form-label">Year</label><input type="number" min="2025" max="2100" name="year" class="form-control" value="<?= esc((string) $year) ?>"></div>
    <div class="col-md-2"><label class="form-label">Month</label><select name="month" class="form-select"><?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= (int) $month === $m ? 'selected' : '' ?>><?= esc(date('F', mktime(0,0,0,$m,1))) ?></option><?php endfor; ?></select></div>
    <div class="col-md-5"><label class="form-label">Employee</label><select name="staff_id" class="form-select select2"><option value="0">All non-admin staff</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (int) ($staffId ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= esc((string) $person['name']) ?> · <?= esc((string) ($person['role_label'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary" type="submit"><i class="fe fe-filter me-1"></i> Apply Period</button></div>
</div></div></form>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>Average Score</small><strong><?= number_format((float) ($totals['average_score'] ?? 0), 1) ?></strong><div class="stat-note"><?= (int) ($totals['staff_count'] ?? 0) ?> active / <?= (int) ($totals['total_staff_count'] ?? 0) ?> staff</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>On-time Rate</small><strong><?= number_format((float) ($totals['on_time_rate'] ?? 0), 1) ?>%</strong><div class="stat-note">Tasks + assigned follow-ups</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>Approved +2</small><strong><?= (int) ($totals['approved_followups'] ?? 0) ?></strong><div class="stat-note"><?= (int) ($totals['no_point_followups'] ?? 0) ?> approved at 0 points</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>Disapproved</small><strong><?= (int) ($totals['disapproved_followups'] ?? 0) ?></strong><div class="stat-note">−2 each</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>Needs Attention</small><strong class="text-danger"><?= (int) ($totals['action_required_count'] ?? 0) ?></strong><div class="stat-note"><?= (int) ($totals['overdue_actions'] ?? 0) ?> overdue actions</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card performance-stat"><div class="card-body"><small>Unassigned Orders</small><strong class="text-warning"><?= (int) ($totals['unassigned_open_orders'] ?? 0) ?></strong><div class="stat-note">Missing follower or due date</div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7"><div class="card analysis-card"><div class="card-header"><div><h5 class="card-title mb-1">Follow-up Decisions</h5><small class="text-muted">Only approval decision and lateness affect follow-up points.</small></div></div><div class="card-body">
        <div class="metric-pair"><span class="metric-badge">Approved +2: <?= (int) ($totals['approved_followups'] ?? 0) ?></span><span class="metric-badge">Approved 0: <?= (int) ($totals['no_point_followups'] ?? 0) ?></span><span class="metric-badge">Disapproved −2: <?= (int) ($totals['disapproved_followups'] ?? 0) ?></span><span class="metric-badge">Submitted <?= (int) ($totals['followup_submissions'] ?? 0) ?></span><span class="metric-badge">Net points <?= number_format((float) ($totals['points_earned'] ?? 0) - (float) ($totals['points_lost'] ?? 0), 1) ?></span></div>
    </div></div></div>
    <div class="col-xl-5"><div class="card analysis-card"><div class="card-header"><div><h5 class="card-title mb-1">Management Focus</h5><small class="text-muted">Exceptions requiring review this period.</small></div></div><div class="card-body">
        <?php $attentionRows = array_values(array_filter(($rows ?? []), static fn(array $item): bool => (bool) ($item['needs_attention'] ?? false))); ?>
        <?php if ($attentionRows === []): ?><div class="text-center py-3"><div class="text-success fw-bold">No active performance exception</div><small class="text-muted">Approval, schedule, and score checks are clear.</small></div><?php endif; ?>
        <?php foreach (array_slice($attentionRows, 0, 5) as $item): ?><div class="attention-item"><div class="attention-icon"><i class="fe fe-alert-triangle"></i></div><div><strong><?= esc((string) ($item['name'] ?? 'Staff')) ?></strong><div class="small text-muted"><?= esc((string) ($item['analysis_summary'] ?? 'Review performance details.')) ?></div></div><span class="score-pill <?= $statusClass((float) ($item['score'] ?? 0)) ?> ms-auto"><?= number_format((float) ($item['score'] ?? 0), 1) ?></span></div><?php endforeach; ?>
    </div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7"><div class="card chart-card"><div class="card-header"><div><h5 class="card-title mb-1">Points Earned vs Lost by Employee</h5><small class="text-muted">Compare contribution and deductions without hiding either side inside the final score.</small></div></div><div class="card-body"><div id="staff-points-chart" class="chart-box"></div></div></div></div>
    <div class="col-xl-5"><div class="card chart-card"><div class="card-header"><div><h5 class="card-title mb-1">Why Points Were Lost</h5><small class="text-muted">Team deductions grouped by their actual scoring reason.</small></div></div><div class="card-body"><div id="loss-reasons-chart" class="chart-box"></div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7"><div class="card chart-card"><div class="card-header"><div><h5 class="card-title mb-1">Where Points Were Earned</h5><small class="text-muted">Positive score contribution by work type for the selected period.</small></div></div><div class="card-body"><div id="earned-reasons-chart" class="chart-box"></div></div></div></div>
    <div class="col-xl-5"><div class="card chart-card"><div class="card-header"><div><h5 class="card-title mb-1">Overall Team Discipline</h5><small class="text-muted">On-time task and follow-up completion rate.</small></div></div><div class="card-body"><div id="team-discipline-chart" class="chart-box"></div></div></div></div>
</div>

<div class="card mb-4"><div class="card-header"><div><h5 class="card-title mb-1">Employee Performance Analysis</h5><small class="text-muted">Active staff are ranked by score and on-time rate; staff without scored activity follow. Admin accounts are excluded.</small></div><div class="small text-muted">Top active performer: <strong><?= esc((string) (($totals['top_performer_name'] ?? '') ?: '—')) ?></strong><?= ($totals['top_performer_name'] ?? '') !== '' ? ' ' . number_format((float) ($totals['top_performer_score'] ?? 0), 1) : '' ?></div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover datatable performance-table mb-0"><thead><tr><th>#</th><th>Employee</th><th>Assessment</th><th>Score Impact</th><th>Follow-up Decisions</th><th>Delivery Discipline</th><th>Business Output</th><th>Rating</th></tr></thead><tbody>
<?php foreach (($rows ?? []) as $rank => $row): ?><tr>
    <td class="fw-bold text-muted"><?= $rank + 1 ?></td>
    <td><strong><?= esc((string) ($row['name'] ?? '-')) ?></strong><div class="small text-muted"><?= esc((string) ($row['role_label'] ?? 'Staff')) ?><br><?= esc((string) ($row['email'] ?? '')) ?></div></td>
    <td><span class="band-pill <?= $bandClass((string) ($row['performance_band'] ?? '')) ?>"><?= esc((string) ($row['performance_band'] ?? 'Review')) ?></span><div class="analysis-summary mt-2"><?= esc((string) ($row['analysis_summary'] ?? '')) ?></div></td>
    <td><span class="score-pill <?= $statusClass((float) ($row['score'] ?? 0)) ?>"><?= number_format((float) ($row['score'] ?? 0), 1) ?></span><div class="small mt-2"><span class="text-success">+<?= number_format((float) ($row['points_earned'] ?? 0), 1) ?></span> <span class="text-danger ms-1">−<?= number_format((float) ($row['points_lost'] ?? 0), 1) ?></span></div></td>
    <td><div class="metric-pair"><span class="metric-badge">Approved +2: <?= (int) ($row['approved_followups'] ?? 0) ?></span><span class="metric-badge">Approved 0: <?= (int) ($row['no_point_followups'] ?? 0) ?></span><span class="metric-badge">Disapproved −2: <?= (int) ($row['disapproved_followups'] ?? 0) ?></span></div></td>
    <td><strong><?= number_format((float) ($row['on_time_rate'] ?? 0), 1) ?>% on time</strong><div class="progress my-2"><div class="progress-bar bg-success" style="width:<?= min(100, max(0, (float) ($row['on_time_rate'] ?? 0))) ?>%"></div></div><div class="small text-muted">Late <?= (int) ($row['task_late'] ?? 0) + (int) ($row['followup_late'] ?? 0) ?> · Overdue <?= (int) ($row['overdue_actions'] ?? 0) ?></div></td>
    <td><div class="small">Orders <?= (int) ($row['order_entries'] ?? 0) ?> · Follow-ups <?= (int) ($row['followup_submission_count'] ?? 0) ?><br>Issues <?= (int) ($row['issuements'] ?? 0) ?> · Returns <?= (int) ($row['returns'] ?? 0) ?><br>Receiving <?= (int) ($row['receivings'] ?? 0) ?> · Purchases <?= (int) ($row['purchases'] ?? 0) ?></div></td>
    <td><strong><?= (int) ($row['rating_count'] ?? 0) > 0 ? number_format((float) ($row['rating_average'] ?? 0), 1) . '/5' : '—' ?></strong><div class="small text-muted"><?= (int) ($row['rating_count'] ?? 0) ?> rated order(s)</div></td>
</tr><?php endforeach; ?></tbody></table></div></div></div>

<div class="card mb-4"><div class="card-header"><div><h5 class="card-title mb-1">Follow-up Scoring Audit</h5><small class="text-muted">Approval, disapproval and late-point entries.</small></div></div><div class="card-body p-0"><div class="table-responsive"><table id="followup-audit-table" class="table table-hover datatable audit-table mb-0" data-dt-page-length="25"><thead><tr><th>Employee</th><th>Event</th><th>Order</th><th>Reason</th><th>Date</th><th>Status</th><th>Points</th><th>Attachment</th></tr></thead><tbody>
<?php foreach (($rows ?? []) as $row): foreach (($eventsByUser[(int) $row['id']] ?? []) as $event): if (! in_array((string) ($event['type'] ?? ''), $followupEventTypes, true)) continue; $delta=(float)($event['score_delta']??0); ?>
<tr><td><strong><?= esc((string) ($row['name'] ?? 'Staff')) ?></strong></td><td><span class="badge <?= $delta < 0 ? 'bg-danger-light text-danger' : ($delta > 0 ? 'bg-success-light text-success' : 'bg-light text-dark border') ?>"><?= esc((string) ($event['type'] ?? 'Activity')) ?></span></td><td><strong><?= esc((string) ($event['title'] ?? '')) ?></strong><?php if ((int) ($event['order_id'] ?? 0) > 0): ?><div class="small text-muted">Order ID <?= (int) $event['order_id'] ?></div><?php endif; ?></td><td><div class="audit-reason"><?= esc((string) (($event['comment'] ?? '') ?: ($event['reference'] ?? '—'))) ?></div></td><td data-order="<?= esc((string) ($event['due_at'] ?? ''), 'attr') ?>"><?= !empty($event['due_at']) ? esc(date('d M Y, h:i A', strtotime((string) $event['due_at']))) : '—' ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($event['status'] ?? '')))) ?></td><td data-order="<?= $delta ?>"><span class="badge <?= $delta>0?'bg-success':($delta<0?'bg-danger':'bg-light text-dark border') ?>"><?= $delta>0?'+':'' ?><?= number_format($delta, 2) ?></span></td><td><?php if (!empty($event['proof_url'])): ?><a href="<?= esc((string) $event['proof_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer">View</a><?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; endforeach; ?>
</tbody></table></div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('template/assets/plugins/apexchart/apexcharts.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    const staff = <?= $chartJson(array_map(static fn(array $row): string => (string) ($row['name'] ?? 'Staff'), $rows ?? [])) ?>;
    const scores = <?= $chartJson(array_map(static fn(array $row): float => (float) ($row['score'] ?? 0), $rows ?? [])) ?>;
    const earned = <?= $chartJson(array_map(static fn(array $row): float => (float) ($row['points_earned'] ?? 0), $rows ?? [])) ?>;
    const lost = <?= $chartJson(array_map(static fn(array $row): float => (float) ($row['points_lost'] ?? 0), $rows ?? [])) ?>;
    const lossMap = <?= $chartJson((array) ($totals['point_loss_breakdown'] ?? [])) ?>;
    const earnedMap = <?= $chartJson((array) ($totals['point_earned_breakdown'] ?? [])) ?>;
    const base = {chart:{fontFamily:'inherit',toolbar:{show:false}},dataLabels:{enabled:false},grid:{borderColor:'#eef0f3'},legend:{position:'top'},noData:{text:'No scored activity in this period'}};
    new ApexCharts(document.querySelector('#staff-points-chart'), {...base,chart:{...base.chart,type:'line',height:310},series:[{name:'Earned',type:'column',data:earned},{name:'Lost',type:'column',data:lost},{name:'Overall score',type:'line',data:scores}],stroke:{width:[0,0,3],curve:'smooth'},markers:{size:[0,0,4]},colors:['#12b76a','#d92d20','#175cd3'],plotOptions:{bar:{borderRadius:4,columnWidth:'55%'}},xaxis:{categories:staff,labels:{rotate:-35}},yaxis:[{title:{text:'Points earned / lost'}},{opposite:true,min:0,title:{text:'Overall score'}}]}).render();
    const lossLabels = Object.keys(lossMap), lossValues = Object.values(lossMap).map(Number);
    new ApexCharts(document.querySelector('#loss-reasons-chart'), {...base,chart:{...base.chart,type:'donut',height:310},series:lossValues.length?lossValues:[0],labels:lossLabels.length?lossLabels:['No deductions'],colors:['#d92d20','#f79009','#f04438','#b54708','#7a271a','#667085'],dataLabels:{enabled:true,formatter:(value)=>value.toFixed(1)+'%'},legend:{position:'bottom'}}).render();
    const earnLabels = Object.keys(earnedMap), earnValues = Object.values(earnedMap).map(Number);
    new ApexCharts(document.querySelector('#earned-reasons-chart'), {...base,chart:{...base.chart,type:'bar',height:310},series:[{name:'Points earned',data:earnValues}],colors:['#175cd3'],plotOptions:{bar:{horizontal:true,borderRadius:4}},xaxis:{categories:earnLabels},tooltip:{y:{formatter:(value)=>Number(value).toFixed(2)+' pts'}}}).render();
    new ApexCharts(document.querySelector('#team-discipline-chart'), {...base,chart:{...base.chart,type:'radialBar',height:310},series:[<?= (float) ($totals['on_time_rate'] ?? 0) ?>],labels:['On time'],colors:['#175cd3'],plotOptions:{radialBar:{dataLabels:{total:{show:true,label:'On-time rate',formatter:()=><?= number_format((float) ($totals['on_time_rate'] ?? 0), 1, '.', '') ?>+'%'}}}},legend:{show:true,position:'bottom'}}).render();
});
</script>
<?= $this->endSection() ?>
