<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$summary = $summary ?? [];
$filters = $filters ?? ['date_from' => '', 'date_to' => ''];
$monthly = $monthly ?? [];
$purchaseBreakdown = $purchase_breakdown ?? [];
$paymentMix = $payment_mix ?? [];
$money = static fn ($value): string => '₹' . number_format((float) $value, 2);
$fyYear = (int) date('n') >= 4 ? (int) date('Y') : ((int) date('Y') - 1);
$today = date('Y-m-d');
?>
<style>
.si-shell{--ink:#172033;--muted:#667085;--line:#e7eaf0}.si-toolbar{align-items:flex-start;gap:18px}.si-filter{align-items:end;display:flex;flex-wrap:wrap;gap:10px}.si-filter .form-control{min-width:150px}.si-filter label{color:var(--muted);font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.si-quick{display:flex;flex-wrap:wrap;gap:6px}.si-quick .btn{border-radius:999px}.si-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;height:100%;overflow:hidden;padding:18px;position:relative}.si-kpi:before{background:var(--accent,#bd1523);border-radius:0 0 0 10px;content:"";height:4px;position:absolute;right:0;top:0;width:46px}.si-kpi-top{align-items:center;display:flex;justify-content:space-between;margin-bottom:12px}.si-kpi-icon{align-items:center;background:var(--soft,#fff0f1);border-radius:12px;color:var(--accent,#bd1523);display:flex;height:40px;justify-content:center;width:40px}.si-kpi-label{color:var(--muted);font-size:.72rem;font-weight:700;text-transform:uppercase}.si-kpi-value{color:var(--ink);font-size:clamp(1.05rem,2vw,1.5rem);font-weight:800;line-height:1.2;overflow-wrap:anywhere}.si-kpi-note{color:var(--muted);font-size:.74rem;margin-top:7px}.si-panel{background:#fff;border:1px solid var(--line);border-radius:16px;height:100%;overflow:hidden}.si-panel-head{align-items:flex-start;border-bottom:1px solid #edf0f4;display:flex;gap:12px;justify-content:space-between;padding:17px 19px}.si-panel-head h5{color:var(--ink);font-size:1rem;margin:0}.si-panel-head p{color:var(--muted);font-size:.76rem;margin:4px 0 0}.si-panel-body{padding:18px 19px}.si-cost-grid{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}.si-cost-item{background:#f8fafc;border:1px solid #edf0f4;border-radius:13px;padding:14px}.si-cost-item span{color:var(--muted);display:block;font-size:.69rem;font-weight:700;margin-bottom:5px;text-transform:uppercase}.si-cost-item strong{color:var(--ink);font-size:1.02rem}.si-positive{color:#027a48!important}.si-negative{color:#b42318!important}.si-chart{min-height:315px}.si-method{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;color:#854d0e;font-size:.76rem;line-height:1.5;padding:11px 13px}.si-insight{align-items:flex-start;border:1px solid #e7eaf0;border-radius:13px;display:flex;gap:11px;padding:13px}.si-insight+.si-insight{margin-top:10px}.si-insight i{align-items:center;border-radius:10px;display:flex;flex:0 0 34px;height:34px;justify-content:center}.si-insight.success i{background:#ecfdf3;color:#027a48}.si-insight.warning i{background:#fffaeb;color:#b54708}.si-insight.danger i{background:#fff1f3;color:#c01048}.si-insight.neutral i{background:#f2f4f7;color:#475467}.si-insight strong{color:var(--ink);display:block;font-size:.84rem}.si-insight span{color:var(--muted);display:block;font-size:.74rem;line-height:1.45;margin-top:3px}.si-breakdown-row{align-items:center;display:grid;gap:10px;grid-template-columns:minmax(90px,1fr) 2fr auto;margin-bottom:14px}.si-breakdown-row:last-child{margin-bottom:0}.si-breakdown-name strong{color:var(--ink);display:block;font-size:.8rem}.si-breakdown-name small{color:var(--muted)}.si-progress{background:#eef1f5;border-radius:999px;height:8px;overflow:hidden}.si-progress span{background:linear-gradient(90deg,#bd1523,#d29b16);border-radius:inherit;display:block;height:100%}.si-breakdown-amount{color:var(--ink);font-size:.78rem;font-weight:700;text-align:right}.si-table{margin:0}.si-table thead th{background:#f8fafc;color:#475467;font-size:.67rem;letter-spacing:.03em;padding:11px 13px;text-transform:uppercase;white-space:nowrap}.si-table tbody td{border-color:#eef1f5;color:#344054;font-size:.78rem;padding:12px 13px;vertical-align:middle}.si-table strong{color:var(--ink)}.si-badge{border-radius:999px;display:inline-flex;font-size:.67rem;font-weight:700;padding:5px 8px}.si-badge.paid{background:#ecfdf3;color:#027a48}.si-badge.partial{background:#fffaeb;color:#b54708}.si-badge.pending{background:#fff1f3;color:#c01048}.si-empty{color:var(--muted);font-size:.8rem;padding:28px!important;text-align:center}.si-weight-strip{align-items:center;display:flex;gap:16px;justify-content:flex-end}.si-weight{border-left:1px solid #e4e7ec;padding-left:16px}.si-weight:first-child{border-left:0}.si-weight small{color:var(--muted);display:block;font-size:.66rem}.si-weight strong{color:var(--ink);font-size:.8rem;white-space:nowrap}
@media(max-width:1199.98px){.si-cost-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){.si-toolbar{display:block}.si-filter{align-items:stretch;margin-top:15px}.si-filter>div{flex:1 1 130px}.si-filter .form-control{min-width:0}.si-filter .btn{width:100%}.si-quick{margin-top:10px}.si-weight-strip{justify-content:flex-start;overflow-x:auto;padding-top:8px;width:100%}.si-panel-head{display:block}.si-cost-grid{grid-template-columns:1fr 1fr}.si-chart{min-height:270px}.si-breakdown-row{grid-template-columns:minmax(80px,1fr) 1.4fr}.si-breakdown-amount{grid-column:1/-1;text-align:left}.si-table{min-width:680px}}
@media(max-width:420px){.si-cost-grid{grid-template-columns:1fr}.si-kpi{padding:15px}}
</style>

<div class="si-shell">
    <div class="erp-page-toolbar erp-command-toolbar si-toolbar flex-wrap mb-3">
        <div>
            <span class="erp-eyebrow">Studded Jewellery Intelligence</span>
            <h4 class="mb-1">Total Sales Dashboard</h4>
            <p class="mb-0">Sales, collections, purchases and operating expenses in one decision view.</p>
        </div>
        <form method="get" class="si-filter">
            <div><label for="si-from">From</label><input id="si-from" type="date" name="date_from" class="form-control" value="<?= esc((string) $filters['date_from']) ?>"></div>
            <div><label for="si-to">To</label><input id="si-to" type="date" name="date_to" class="form-control" value="<?= esc((string) $filters['date_to']) ?>"></div>
            <button class="btn btn-primary" type="submit"><i class="fe fe-filter me-1"></i>Apply</button>
        </form>
        <div class="si-quick w-100 justify-content-end">
            <a class="btn btn-sm btn-outline-secondary" href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= $today ?>">This month</a>
            <a class="btn btn-sm btn-outline-secondary" href="?date_from=<?= $fyYear ?>-04-01&date_to=<?= $today ?>">Financial year</a>
            <a class="btn btn-sm btn-outline-secondary" href="?date_from=2000-01-01&date_to=<?= $today ?>">All time</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="si-kpi" style="--accent:#bd1523;--soft:#fff0f1"><div class="si-kpi-top"><span class="si-kpi-label">Total sales</span><span class="si-kpi-icon"><i class="fe fe-trending-up"></i></span></div><div class="si-kpi-value"><?= $money($summary['sales_total'] ?? 0) ?></div><div class="si-kpi-note"><?= number_format((int) ($summary['sale_count'] ?? 0)) ?> bills · <?= number_format((float) ($summary['sold_qty'] ?? 0), 0) ?> pieces</div></div></div>
        <div class="col-6 col-xl-3"><div class="si-kpi" style="--accent:#027a48;--soft:#ecfdf3"><div class="si-kpi-top"><span class="si-kpi-label">Collected</span><span class="si-kpi-icon"><i class="fe fe-check-circle"></i></span></div><div class="si-kpi-value"><?= $money($summary['received'] ?? 0) ?></div><div class="si-kpi-note"><?= number_format((float) ($summary['collection_rate'] ?? 0), 1) ?>% of invoice value</div></div></div>
        <div class="col-6 col-xl-3"><div class="si-kpi" style="--accent:#d97706;--soft:#fffbeb"><div class="si-kpi-top"><span class="si-kpi-label">Outstanding</span><span class="si-kpi-icon"><i class="fe fe-clock"></i></span></div><div class="si-kpi-value"><?= $money($summary['outstanding'] ?? 0) ?></div><div class="si-kpi-note"><?= number_format((int) (($paymentMix['Pending']['count'] ?? 0) + ($paymentMix['Partial']['count'] ?? 0))) ?> bills need collection</div></div></div>
        <div class="col-6 col-xl-3"><div class="si-kpi" style="--accent:#6941c6;--soft:#f4f3ff"><div class="si-kpi-top"><span class="si-kpi-label">Output tax</span><span class="si-kpi-icon"><i class="fe fe-percent"></i></span></div><div class="si-kpi-value"><?= $money($summary['output_tax'] ?? 0) ?></div><div class="si-kpi-note">Taxable sales <?= $money($summary['taxable_sales'] ?? 0) ?></div></div></div>
    </div>

    <div class="si-panel mb-3">
        <div class="si-panel-head">
            <div><h5>Purchase & Expense vs Sale</h5><p>Intelligent bill-basis operating comparison for the selected period.</p></div>
            <div class="si-weight-strip">
                <div class="si-weight"><small>Gold sold</small><strong><?= number_format((float) ($summary['gold_weight'] ?? 0), 3) ?> gm</strong></div>
                <div class="si-weight"><small>Diamond sold</small><strong><?= number_format((float) ($summary['diamond_weight'] ?? 0), 3) ?> cts</strong></div>
                <div class="si-weight"><small>Stone sold</small><strong><?= number_format((float) ($summary['stone_weight'] ?? 0), 3) ?> cts</strong></div>
            </div>
        </div>
        <div class="si-panel-body">
            <?php $spread = (float) ($summary['operating_spread'] ?? 0); ?>
            <div class="si-cost-grid mb-3">
                <div class="si-cost-item"><span>Material purchases</span><strong><?= $money($summary['purchase_total'] ?? 0) ?></strong><small class="text-muted d-block mt-1"><?= number_format((int) ($summary['purchase_count'] ?? 0)) ?> bills</small></div>
                <div class="si-cost-item"><span>Labour expense</span><strong><?= $money($summary['labour_expense'] ?? 0) ?></strong><small class="text-muted d-block mt-1"><?= number_format((int) ($summary['labour_bill_count'] ?? 0)) ?> bills</small></div>
                <div class="si-cost-item"><span>Other expense</span><strong><?= $money($summary['other_expense'] ?? 0) ?></strong><small class="text-muted d-block mt-1"><?= number_format((int) ($summary['other_expense_count'] ?? 0)) ?> posted vouchers</small></div>
                <div class="si-cost-item"><span>Estimated spread</span><strong class="<?= $spread >= 0 ? 'si-positive' : 'si-negative' ?>"><?= ($spread < 0 ? '−' : '') . $money(abs($spread)) ?></strong><small class="text-muted d-block mt-1">Cost / sales <?= number_format((float) ($summary['cost_to_sales_ratio'] ?? 0), 1) ?>%</small></div>
            </div>
            <div class="si-method"><i class="fe fe-info me-1"></i><?= esc((string) ($method_note ?? '')) ?></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8"><div class="si-panel"><div class="si-panel-head"><div><h5>Monthly business movement</h5><p>Invoice sales compared with purchase bills and expenses · latest 24 months in range.</p></div></div><div class="si-panel-body"><div id="si-monthly-chart" class="si-chart"></div></div></div></div>
        <div class="col-xl-4"><div class="si-panel"><div class="si-panel-head"><div><h5>Payment position</h5><p>Collected and outstanding value for selected sales.</p></div></div><div class="si-panel-body"><div id="si-payment-chart" class="si-chart"></div></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5"><div class="si-panel"><div class="si-panel-head"><div><h5>Purchase mix</h5><p>Material purchase value by inventory category.</p></div><strong><?= $money($summary['purchase_total'] ?? 0) ?></strong></div><div class="si-panel-body">
            <?php $maxPurchase = max(1.0, ...array_map(static fn (array $row): float => (float) ($row['amount'] ?? 0), $purchaseBreakdown ?: [['amount' => 1]])); ?>
            <?php if ($purchaseBreakdown === []): ?><div class="si-empty">No purchase bills in this period.</div><?php endif; ?>
            <?php foreach ($purchaseBreakdown as $row): ?><div class="si-breakdown-row"><div class="si-breakdown-name"><strong><?= esc((string) ($row['category'] ?? 'Other')) ?></strong><small><?= number_format((int) ($row['bill_count'] ?? 0)) ?> bills</small></div><div class="si-progress"><span style="width:<?= min(100, ((float) ($row['amount'] ?? 0) / $maxPurchase) * 100) ?>%"></span></div><div class="si-breakdown-amount"><?= $money($row['amount'] ?? 0) ?></div></div><?php endforeach; ?>
        </div></div></div>
        <div class="col-xl-7"><div class="si-panel"><div class="si-panel-head"><div><h5>Intelligent observations</h5><p>Automatic signals from the selected business period.</p></div></div><div class="si-panel-body">
            <?php foreach (($insights ?? []) as $insight): ?>
                <?php $tone = (string) ($insight['tone'] ?? 'neutral'); $icon = $tone === 'success' ? 'fe-check' : ($tone === 'danger' ? 'fe-alert-triangle' : ($tone === 'warning' ? 'fe-alert-circle' : 'fe-info')); ?>
                <div class="si-insight <?= esc($tone) ?>"><i class="fe <?= $icon ?>"></i><div><strong><?= esc((string) ($insight['title'] ?? '')) ?></strong><span><?= esc((string) ($insight['message'] ?? '')) ?></span></div></div>
            <?php endforeach; ?>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7"><div class="si-panel"><div class="si-panel-head"><div><h5>Top customers</h5><p>Ranked by invoice value in the selected period.</p></div></div><div class="table-responsive"><table class="table si-table" data-dt-skip="1"><thead><tr><th>Customer</th><th>Bills</th><th>Sales</th><th>Collected</th><th>Outstanding</th></tr></thead><tbody>
            <?php foreach (($top_customers ?? []) as $row): ?><tr><td><strong><?= esc((string) ($row['customer_name'] ?? '-')) ?></strong></td><td><?= number_format((int) ($row['bill_count'] ?? 0)) ?></td><td><?= $money($row['sales_total'] ?? 0) ?></td><td class="text-success"><?= $money($row['received'] ?? 0) ?></td><td class="text-danger"><?= $money($row['outstanding'] ?? 0) ?></td></tr><?php endforeach; ?>
            <?php if (($top_customers ?? []) === []): ?><tr><td colspan="5" class="si-empty">No customer sales found.</td></tr><?php endif; ?>
        </tbody></table></div></div></div>
        <div class="col-xl-5"><div class="si-panel"><div class="si-panel-head"><div><h5>Recent sale bills</h5><p>Latest bills inside the selected period.</p></div><a href="<?= site_url('admin/studded-jewellery/sale-bills') ?>" class="btn btn-sm btn-outline-primary">View all</a></div><div class="table-responsive"><table class="table si-table" data-dt-skip="1"><thead><tr><th>Bill</th><th>Customer</th><th>Value</th><th>Status</th></tr></thead><tbody>
            <?php foreach (($recent_sales ?? []) as $row): ?><?php $status = strtolower((string) ($row['payment_status'] ?? 'Pending')); ?><tr><td><a href="<?= site_url('admin/studded-jewellery/sale-bills/' . (int) ($row['id'] ?? 0)) ?>"><strong><?= esc((string) ($row['sale_no'] ?? '-')) ?></strong></a><br><small class="text-muted"><?= esc((string) ($row['sale_date'] ?? '')) ?></small></td><td><?= esc((string) ($row['customer_name'] ?? '-')) ?></td><td><strong><?= $money($row['total_amount'] ?? 0) ?></strong></td><td><span class="si-badge <?= esc($status) ?>"><?= esc(ucfirst($status)) ?></span></td></tr><?php endforeach; ?>
            <?php if (($recent_sales ?? []) === []): ?><tr><td colspan="4" class="si-empty">No recent sale bills found.</td></tr><?php endif; ?>
        </tbody></table></div></div></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('template/assets/plugins/apexchart/apexcharts.min.js') ?>"></script>
<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;
    const monthly = <?= json_encode(array_values($monthly), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const currency = function (value) { return '₹' + Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); };
    new ApexCharts(document.querySelector('#si-monthly-chart'), {
        chart:{type:'bar',height:315,toolbar:{show:false},fontFamily:'inherit'},
        series:[{name:'Sales',data:monthly.map(row=>row.sales)},{name:'Purchases',data:monthly.map(row=>row.purchases)},{name:'Expenses',data:monthly.map(row=>row.expenses)}],
        colors:['#1570ef','#bd1523','#d29b16'],plotOptions:{bar:{borderRadius:5,columnWidth:'58%'}},dataLabels:{enabled:false},stroke:{show:true,width:2,colors:['transparent']},
        xaxis:{categories:monthly.map(row=>row.label),labels:{rotate:-35}},yaxis:{labels:{formatter:currency}},tooltip:{y:{formatter:currency}},legend:{position:'top',horizontalAlign:'left'},grid:{borderColor:'#eef1f5'}
    }).render();
    new ApexCharts(document.querySelector('#si-payment-chart'), {
        chart:{type:'donut',height:315,fontFamily:'inherit'},series:[<?= json_encode((float) ($summary['received'] ?? 0)) ?>,<?= json_encode((float) ($summary['outstanding'] ?? 0)) ?>],labels:['Collected','Outstanding'],colors:['#12b76a','#f04438'],stroke:{colors:['#fff'],width:4},dataLabels:{enabled:false},legend:{position:'bottom'},tooltip:{y:{formatter:currency}},plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Invoice value',formatter:function(w){return currency(w.globals.seriesTotals.reduce((a,b)=>a+b,0));}}}}}}
    }).render();
})();
</script>
<?= $this->endSection() ?>
