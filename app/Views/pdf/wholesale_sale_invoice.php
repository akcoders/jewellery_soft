<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page{margin:10mm 9mm}body{color:#111;font-family:DejaVu Sans,sans-serif;font-size:10px;margin:0}.invoice{border:1px solid #222}.title{text-align:center;font-size:17px;font-weight:700;padding:9px;position:relative}.copy{font-size:9px;font-style:italic;font-weight:400;margin-left:18px}.muted{color:#555}.grid{border-collapse:collapse;width:100%}.grid td,.grid th{border:1px solid #333;padding:5px;vertical-align:top}.party{font-size:9.5px;line-height:1.5}.party strong{font-size:11px}.meta td{height:25px;width:25%}.label{color:#444;display:block;font-size:8px;margin-bottom:2px}.items th{background:#f3f3f3;font-size:9px;text-align:center}.items td{font-size:9px}.right{text-align:right}.center{text-align:center}.bold{font-weight:700}.description{font-size:10px;font-weight:700}.spacer td{height:120px}.totals-label{text-align:right;font-weight:700}.grand{font-size:12px;font-weight:700}.words{font-size:10px;font-weight:700}.declaration{font-size:8px;line-height:1.4}.signature{height:55px;text-align:right;vertical-align:bottom!important}.no-border{border:0!important}.tax th,.tax td{font-size:8.5px;text-align:right}.tax th:first-child,.tax td:first-child{text-align:left}
    </style>
</head>
<body>
<?php
$companyAddress = array_filter([$company['address_line'] ?? '', $company['city'] ?? '', $company['state'] ?? '', $company['pincode'] ?? '']);
$buyerAddress = array_filter([$customerAddress['line1'] ?? '', $customerAddress['line2'] ?? '', $customerAddress['city'] ?? '', $customerAddress['state'] ?? '', $customerAddress['pincode'] ?? '']);
$componentRows = [
    ['label' => 'Gold Studded Jewellery', 'weight' => (float) ($sale['total_gold_weight'] ?? 0), 'unit' => 'GMS', 'rate' => (float) ($sale['gold_rate'] ?? 0), 'amount' => (float) ($sale['gold_amount'] ?? 0)],
    ['label' => 'Studded Diamond', 'weight' => (float) ($sale['total_diamond_weight'] ?? 0), 'unit' => 'CTS', 'rate' => (float) ($sale['diamond_rate'] ?? 0), 'amount' => (float) ($sale['diamond_amount'] ?? 0)],
    ['label' => 'Studded Precious & Semi-Precious Stones', 'weight' => (float) ($sale['total_stone_weight'] ?? 0), 'unit' => 'CTS', 'rate' => (float) ($sale['stone_rate'] ?? 0), 'amount' => (float) ($sale['stone_amount'] ?? 0)],
];
?>
<div class="invoice">
    <div class="title">Tax Invoice <span class="copy">(ORIGINAL FOR RECIPIENT)</span></div>
    <table class="grid">
        <tr>
            <td rowspan="4" style="width:52%" class="party">
                <strong><?= esc((string) ($company['company_name'] ?? 'Aabhushan')) ?></strong><br>
                <?= esc(implode(', ', $companyAddress)) ?><br>
                <?php if (! empty($company['gstin'])): ?>GSTIN/UIN: <?= esc((string) $company['gstin']) ?><br><?php endif; ?>
                <?php if (! empty($company['phone'])): ?>Phone: <?= esc((string) $company['phone']) ?><br><?php endif; ?>
                <?php if (! empty($company['email'])): ?>E-Mail: <?= esc((string) $company['email']) ?><?php endif; ?>
            </td>
            <td><span class="label">Invoice No.</span><strong><?= esc((string) ($sale['invoice_no'] ?? $sale['sale_no'] ?? '-')) ?></strong></td>
            <td><span class="label">Dated</span><strong><?= ! empty($sale['invoice_date']) ? esc(date('d-M-Y', strtotime((string) $sale['invoice_date']))) : '-' ?></strong></td>
        </tr>
        <tr><td><span class="label">Packing List No.</span><?= esc((string) ($sale['packing_no'] ?? '-')) ?></td><td><span class="label">Mode / Terms of Payment</span><?= esc((string) (($sale['terms_text'] ?? '') ?: ($sale['payment_status'] ?? '-'))) ?></td></tr>
        <tr><td><span class="label">Reference No.</span><?= esc((string) ($sale['sale_no'] ?? '-')) ?></td><td><span class="label">Other References</span>-</td></tr>
        <tr><td><span class="label">Delivery Note</span><?= esc((string) ($sale['packing_no'] ?? '-')) ?></td><td><span class="label">Delivery Note Date</span><?= ! empty($sale['packing_date']) ? esc(date('d-M-Y', strtotime((string) $sale['packing_date']))) : '-' ?></td></tr>
        <tr>
            <td rowspan="3" class="party">
                <span class="label">Buyer (Bill to)</span>
                <strong><?= esc((string) ($sale['customer_name'] ?? '-')) ?></strong><br>
                <?= esc(implode(', ', $buyerAddress)) ?><br>
                <?php if (! empty($sale['customer_gstin'])): ?>GSTIN/UIN: <?= esc((string) $sale['customer_gstin']) ?><br><?php endif; ?>
                <?php if (! empty($sale['customer_phone'])): ?>Phone: <?= esc((string) $sale['customer_phone']) ?><?php endif; ?>
            </td>
            <td><span class="label">Buyer's Order No.</span><?= esc((string) ($sale['sale_no'] ?? '-')) ?></td><td><span class="label">Dated</span><?= esc((string) ($sale['sale_date'] ?? '-')) ?></td>
        </tr>
        <tr><td><span class="label">Dispatched Through</span>-</td><td><span class="label">Destination</span><?= esc((string) ($customerAddress['city'] ?? '-')) ?></td></tr>
        <tr><td colspan="2"><span class="label">Terms of Delivery</span><?= esc((string) (($sale['notes'] ?? '') ?: 'As agreed')) ?></td></tr>
    </table>

    <table class="grid items">
        <thead><tr><th style="width:5%">Sl No.</th><th>Description of Goods</th><th style="width:12%">HSN/SAC</th><th style="width:13%">Quantity</th><th style="width:13%">Rate</th><th style="width:7%">per</th><th style="width:15%">Amount</th></tr></thead>
        <tbody>
        <?php $serial = 1; foreach ($componentRows as $row): if ($row['weight'] <= 0 && $row['amount'] <= 0) continue; ?>
            <tr><td class="center"><?= $serial++ ?></td><td class="description"><?= esc($row['label']) ?></td><td class="center"><?= esc((string) ($sale['hsn_sac'] ?? '711319')) ?></td><td class="right bold"><?= number_format($row['weight'], 3) ?> <?= esc($row['unit']) ?></td><td class="right"><?= number_format($row['rate'], 2) ?></td><td class="center"><?= esc($row['unit']) ?></td><td class="right bold"><?= number_format($row['amount'], 2) ?></td></tr>
        <?php endforeach; ?>
        <?php if ((float) ($sale['other_amount'] ?? 0) > 0): ?>
            <tr><td class="center"><?= $serial ?></td><td class="description">Other Charges</td><td class="center"><?= esc((string) ($sale['hsn_sac'] ?? '711319')) ?></td><td></td><td></td><td></td><td class="right bold"><?= number_format((float) $sale['other_amount'], 2) ?></td></tr>
        <?php endif; ?>
            <tr class="spacer"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <tr><td colspan="6" class="totals-label">Taxable Value</td><td class="right bold"><?= number_format((float) ($sale['taxable_amount'] ?? 0), 2) ?></td></tr>
            <?php foreach (($sale['tax_components'] ?? []) as $tax): ?>
                <tr><td colspan="6" class="totals-label"><?= esc((string) ($tax['name'] ?? 'GST')) ?> <?= number_format((float) ($tax['percentage'] ?? 0), 2) ?>%</td><td class="right bold"><?= number_format((float) ($tax['amount'] ?? 0), 2) ?></td></tr>
            <?php endforeach; ?>
            <?php if ((float) ($sale['round_off_amount'] ?? 0) != 0.0): ?><tr><td colspan="6" class="totals-label">Round Off</td><td class="right bold"><?= number_format((float) $sale['round_off_amount'], 2) ?></td></tr><?php endif; ?>
            <tr><td colspan="3" class="totals-label">Total</td><td class="right bold"><?= number_format((float) ($sale['total_gold_weight'] ?? 0), 3) ?> GMS</td><td colspan="2"></td><td class="right grand">₹ <?= number_format((float) ($sale['total_amount'] ?? 0), 2) ?></td></tr>
        </tbody>
    </table>
    <table class="grid"><tr><td><span class="label">Amount Chargeable (in words)</span><div class="words">INR <?= esc((string) ($amountInWords ?? '')) ?></div></td><td class="right" style="width:20%">E. &amp; O.E</td></tr></table>
    <?php if (($sale['tax_components'] ?? []) !== []): ?>
    <table class="grid tax"><thead><tr><th>HSN/SAC</th><th>Taxable Value</th><th>Tax Type</th><th>Rate</th><th>Tax Amount</th></tr></thead><tbody>
        <?php foreach ($sale['tax_components'] as $tax): ?><tr><td><?= esc((string) ($sale['hsn_sac'] ?? '711319')) ?></td><td><?= number_format((float) ($sale['taxable_amount'] ?? 0), 2) ?></td><td><?= esc((string) ($tax['name'] ?? 'GST')) ?></td><td><?= number_format((float) ($tax['percentage'] ?? 0), 2) ?>%</td><td><?= number_format((float) ($tax['amount'] ?? 0), 2) ?></td></tr><?php endforeach; ?>
        <tr><td class="bold">Total</td><td class="bold"><?= number_format((float) ($sale['taxable_amount'] ?? 0), 2) ?></td><td></td><td></td><td class="bold"><?= number_format((float) ($sale['gst_amount'] ?? 0), 2) ?></td></tr>
    </tbody></table>
    <?php endif; ?>
    <table class="grid"><tr><td class="declaration" style="width:58%"><strong>Declaration</strong><br>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</td><td class="signature"><strong>for <?= esc((string) ($company['company_name'] ?? 'Aabhushan')) ?></strong><br><br><br>Authorised Signatory</td></tr></table>
</div>
<div class="center muted" style="margin-top:5px">This is a computer-generated invoice.</div>
</body>
</html>
