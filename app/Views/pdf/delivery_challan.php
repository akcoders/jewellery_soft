<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page{margin:16px}*{box-sizing:border-box}body{margin:0;color:#2d2029;font:10px DejaVu Sans,sans-serif;background:#fff}.sheet{border:1px solid #d8b55b;position:relative;overflow:hidden}.challan-top{height:11px;background:linear-gradient(90deg,#351426,#7b2947,#c69a42)}.header{padding:19px 23px 15px;background:#fff9ed;border-bottom:2px solid #c69a42}.brand{font:700 25px DejaVu Serif,serif;color:#421d32;letter-spacing:.4px}.brand span{color:#b28224}.tagline{margin-top:3px;color:#866e60;font-size:8px;letter-spacing:1.4px;text-transform:uppercase}.doc-title{text-align:right;font:700 21px DejaVu Serif,serif;color:#421d32;letter-spacing:1px}.doc-note{text-align:right;color:#9b7b3e;font-size:8px;letter-spacing:1.2px;text-transform:uppercase}.meta{padding:10px 23px;background:#421d32;color:#fff}.meta table,.parties,.items,.summary,.totals,.footer{width:100%;border-collapse:collapse}.meta td{padding:1px 0}.meta-label{color:#e3c67b;font-size:7px;text-transform:uppercase;letter-spacing:.8px}.meta-value{font-size:11px;font-weight:bold}.parties-wrap{padding:16px 23px}.parties td{width:50%;vertical-align:top;padding-right:15px}.parties td+td{border-left:1px solid #e8d8b9;padding:0 0 0 18px}.party-label{margin-bottom:6px;color:#a77522;font-size:8px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase}.party-name{margin-bottom:4px;color:#421d32;font:700 14px DejaVu Serif,serif}.muted{color:#72666d;line-height:1.55}.content{padding:0 23px 15px}.items th{padding:8px 5px;background:#63263f;color:#fff;border-right:1px solid #825069;font-size:7px;letter-spacing:.55px;text-transform:uppercase}.items td{padding:8px 5px;border-bottom:1px solid #eadfcb;vertical-align:top}.items tbody tr:nth-child(even){background:#fffaf0}.center{text-align:center}.right{text-align:right}.item-name{font-weight:bold;color:#421d32}.type{margin-top:2px;color:#a77522;font-size:7px;font-weight:bold;text-transform:uppercase}.diamond{color:#2d7898}.metal{color:#9b6815}.weights{line-height:1.6;white-space:nowrap}.summary{margin-top:14px}.summary-info{width:57%;vertical-align:top;padding:3px 20px 0 0;color:#6f626a;line-height:1.6}.summary-total{width:43%;vertical-align:top}.totals td{padding:6px 8px;border-bottom:1px solid #eadfcb}.totals .grand td{padding:10px 8px;border:0;background:#421d32;color:#fff;font-size:12px;font-weight:bold}.totals .grand td:last-child{color:#eccb72}.notice{margin:0 23px 14px;padding:9px 12px;background:#fff5da;border-left:4px solid #c69a42;color:#695744;line-height:1.5}.footer-wrap{padding:15px 23px 18px;border-top:1px solid #e7d8bc}.footer td{width:50%;vertical-align:bottom}.sign{text-align:right;padding-top:22px}.sign-line{display:inline-block;width:160px;padding-top:5px;border-top:1px solid #421d32;text-align:center;font-weight:bold}.seal{color:#a77522;font:700 11px DejaVu Serif,serif}
</style>
</head>
<body>
<?php
$row = is_array($challan ?? null) ? $challan : [];
$companyRow = is_array($company ?? null) ? $company : [];
$decoded = json_decode((string) ($row['summary_json'] ?? ''), true);
$decoded = is_array($decoded) ? $decoded : [];
$lines = is_array($items ?? null) ? $items : (is_array($decoded['items'] ?? null) ? $decoded['items'] : []);
if ($lines === []) {
    $lines = [[
        'item_type' => 'ornament', 'description' => 'Studded Jewellery', 'purity' => '18 KT',
        'pcs' => max(1, (int) ($row['total_pcs'] ?? 1)),
        'gross_weight_gm' => (float) ($row['gross_weight_gm'] ?? ($receive['gross'] ?? 0)),
        'net_weight_gm' => (float) ($row['net_gold_weight_gm'] ?? ($receive['net'] ?? 0)),
        'diamond_weight_cts' => (float) ($row['diamond_weight_cts'] ?? ($receive['diamond_cts'] ?? 0)),
        'stone_weight_cts' => (float) ($row['color_stone_weight_cts'] ?? ($receive['stone_cts'] ?? 0)),
        'other_weight_gm' => (float) ($row['other_weight_gm'] ?? ($receive['other_gm'] ?? 0)),
        'value' => (float) ($row['taxable_value'] ?? ($pricing['total'] ?? 0)),
    ]];
}
$companyName = trim((string) ($companyRow['company_name'] ?? 'Aabhushan')) ?: 'Aabhushan';
$companyAddress = implode(', ', array_filter(array_map('trim', [(string) ($companyRow['address_line'] ?? ''), (string) ($companyRow['city'] ?? ''), (string) ($companyRow['state'] ?? ''), (string) ($companyRow['pincode'] ?? '')])));
$fromCity = trim((string) ($row['dispatch_from'] ?? '')) ?: (trim((string) ($companyRow['city'] ?? '')) ?: 'Company');
$fromAddress = trim((string) ($row['dispatch_from_address'] ?? '')) ?: $companyAddress;
$customerName = trim((string) ($row['display_customer'] ?? $row['customer_name'] ?? $order['customer_name'] ?? 'Customer')) ?: 'Customer';
$customerAddress = trim((string) ($row['customer_address'] ?? ''));
$dcNo = (string) ($row['challan_no'] ?? ($challan_no ?? '-'));
$date = ! empty($row['challan_date']) ? date('d M Y', strtotime((string) $row['challan_date'])) : date('d M Y');
$taxable = (float) ($row['taxable_value'] ?? ($pricing['total'] ?? 0));
$taxPercent = (float) ($row['tax_percent'] ?? 3);
$tax = (float) ($row['tax_amount'] ?? round($taxable * $taxPercent / 100, 2));
$total = (float) ($row['total_amount'] ?? round($taxable + $tax, 2));
$totalPcs = (int) ($row['total_pcs'] ?? array_sum(array_map(static fn($line): int => (int) ($line['pcs'] ?? 0), $lines)));
$typeLabel = static fn(string $type): string => match ($type) { 'loose_diamond' => 'Loose Diamond', 'loose_gold' => 'Loose Gold / Metal', default => 'Ornament' };
?>
<div class="sheet">
<div class="challan-top"></div>
<div class="header"><table style="width:100%;border-collapse:collapse"><tr><td style="width:58%"><div class="brand"><?= esc($companyName) ?> <span>Jewels</span></div><div class="tagline">Fine Jewellery • Diamonds • Precious Metal</div></td><td><div class="doc-title">DELIVERY CHALLAN</div><div class="doc-note">Original for recipient • Not a tax invoice</div></td></tr></table></div>
<div class="meta"><table><tr><td><div class="meta-label">Challan Number</div><div class="meta-value"><?= esc($dcNo) ?></div></td><td><div class="meta-label">Challan Date</div><div class="meta-value"><?= esc($date) ?></div></td><td><div class="meta-label">Dispatch From</div><div class="meta-value"><?= esc($fromCity) ?></div></td><td class="right"><div class="meta-label">Total Quantity</div><div class="meta-value"><?= $totalPcs ?> PCS</div></td></tr></table></div>
<div class="parties-wrap"><table class="parties"><tr><td><div class="party-label">Consignor / From</div><div class="party-name"><?= esc($companyName) ?> — <?= esc($fromCity) ?></div><div class="muted"><?= nl2br(esc($fromAddress !== '' ? $fromAddress : '-')) ?><br>GSTIN: <?= esc((string) (($companyRow['gstin'] ?? '') ?: '-')) ?><br>Phone: <?= esc((string) (($companyRow['phone'] ?? '') ?: '-')) ?></div></td><td><div class="party-label">Consignee / Deliver To</div><div class="party-name"><?= esc($customerName) ?></div><div class="muted"><?= nl2br(esc($customerAddress !== '' ? $customerAddress : '-')) ?><br>GSTIN: <?= esc((string) (($row['customer_gstin'] ?? '') ?: '-')) ?><?php if (! empty($row['order_no'])): ?><br>Reference: Order <?= esc((string) $row['order_no']) ?><?php endif; ?></div></td></tr></table></div>
<div class="content"><table class="items"><thead><tr><th style="width:5%">#</th><th style="width:23%">Description</th><th style="width:9%">Purity</th><th style="width:7%">PCS</th><th style="width:36%">Weight Details</th><th style="width:20%;text-align:right">Value (Rs.)</th></tr></thead><tbody>
<?php foreach ($lines as $index => $line): ?><?php $type=(string)($line['item_type']??'ornament'); $class=$type==='loose_diamond'?'diamond':($type==='loose_gold'?'metal':''); ?><tr><td class="center"><?= $index + 1 ?></td><td><div class="item-name"><?= esc((string) (($line['description'] ?? '') ?: $typeLabel($type))) ?></div><div class="type <?= $class ?>"><?= esc($typeLabel($type)) ?></div></td><td class="center"><?= esc((string) (($line['purity'] ?? '') ?: '—')) ?></td><td class="center"><?= (int) ($line['pcs'] ?? 0) ?></td><td class="weights"><?php if ($type === 'ornament'): ?>Gross: <b><?= number_format((float)($line['gross_weight_gm']??0),3) ?> gm</b> &nbsp; Net: <b><?= number_format((float)($line['net_weight_gm']??0),3) ?> gm</b><br>Diamond: <?= number_format((float)($line['diamond_weight_cts']??0),3) ?> ct &nbsp; Stone: <?= number_format((float)($line['stone_weight_cts']??0),3) ?> ct &nbsp; Other: <?= number_format((float)($line['other_weight_gm']??0),3) ?> gm<?php elseif ($type === 'loose_diamond'): ?>Diamond Weight: <b><?= number_format((float)($line['diamond_weight_cts']??0),3) ?> ct</b><?php else: ?>Gold Weight: <b><?= number_format((float)($line['net_weight_gm']??0),3) ?> gm</b><?php endif; ?></td><td class="right"><b><?= number_format((float)($line['value']??0),2) ?></b></td></tr><?php endforeach; ?>
</tbody></table>
<table class="summary"><tr><td class="summary-info"><b>Purpose / Notes</b><br><?= nl2br(esc((string) (($row['notes'] ?? '') ?: 'Goods dispatched as per mutual business arrangement.'))) ?><br><br><b>Declaration</b><br>The goods described above are dispatched in good condition. This challan records movement and acknowledgement of goods only.</td><td class="summary-total"><table class="totals"><tr><td>Taxable Value</td><td class="right">Rs. <?= number_format($taxable,2) ?></td></tr><tr><td>GST @ <?= number_format($taxPercent,2) ?>%</td><td class="right">Rs. <?= number_format($tax,2) ?></td></tr><tr class="grand"><td>GROSS TOTAL</td><td class="right">Rs. <?= number_format($total,2) ?></td></tr></table></td></tr></table></div>
<div class="notice">Please verify quantity, purity and weight at delivery. Any discrepancy must be reported immediately.</div>
<div class="footer-wrap"><table class="footer"><tr><td class="sign"><div>For <?= esc($companyName) ?></div><div class="sign-line">Authorized Signatory</div></td></tr></table></div>
</div>
</body></html>
