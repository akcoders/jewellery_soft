<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; padding: 0; color: #111; }
        .page { padding: 18px 24px; }
        .box { border: 1px solid #333; }
        .hdr { border-bottom: 1px solid #333; text-align: center; font-size: 34px; font-weight: 700; padding: 6px 0; }
        .section { padding: 8px 10px; border-bottom: 1px solid #333; }
        .section:last-child { border-bottom: 0; }
        .label { font-weight: 700; }
        .line { margin: 2px 0; }
        .mt8 { margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
        th { font-weight: 700; background: #f5f5f5; }
        .right { text-align: right; }
        .center { text-align: center; }
        .sub-table td, .sub-table th { border: 1px solid #666; padding: 3px 4px; white-space: nowrap; }
        .sig { text-align: right; font-weight: 700; margin-top: 18px; padding-right: 6px; }
    </style>
</head>
<body>
<?php
    $companyName = (string) ($company['company_name'] ?? '-');
    $address = trim((string) (($company['address_line'] ?? '') . ',' . ($company['city'] ?? '') . ',' . ($company['state'] ?? '') . '-' . ($company['pincode'] ?? '')), ",- ");
    $phone = (string) ($company['phone'] ?? '-');
    $gstin = (string) ($company['gstin'] ?? '-');
    $challanRow = is_array($challan ?? null) ? $challan : [];
    $date = (string) ($challanRow['challan_date'] ?? date('Y-m-d'));
    $date = date('d.m.Y', strtotime($date));
    $dcNo = (string) ($challanRow['challan_no'] ?? ($challan_no ?? '-'));

    $gross = (float) ($challanRow['gross_weight_gm'] ?? ($receive['gross'] ?? 0));
    $net = (float) ($challanRow['net_gold_weight_gm'] ?? ($receive['net'] ?? 0));
    $dia = (float) ($challanRow['diamond_weight_cts'] ?? ($receive['diamond_cts'] ?? 0));
    $stone = (float) ($challanRow['color_stone_weight_cts'] ?? ($receive['stone_cts'] ?? 0));
    $other = (float) ($challanRow['other_weight_gm'] ?? ($receive['other_gm'] ?? 0));

    $taxable = (float) ($challanRow['taxable_value'] ?? ($pricing['total'] ?? 0));
    $taxPercent = (float) ($challanRow['tax_percent'] ?? 3.0);
    $tax = (float) ($challanRow['tax_amount'] ?? round($taxable * ($taxPercent / 100), 2));
    $total = (float) ($challanRow['total_amount'] ?? round($taxable + $tax, 2));
?>
<div class="page">
    <div class="box">
        <div class="hdr">Delivery Challan</div>

        <div class="section">
            <div class="label">Consigner</div>
            <div class="line"><span class="label">Karigar Name:</span> <?= esc($companyName) ?></div>
            <div class="line"><span class="label">Address:</span> <?= esc($address !== '' ? $address : '-') ?></div>
            <div class="line"><span class="label">Tel No:</span> <?= esc($phone) ?></div>
            <div class="line"><span class="label">GST No:</span> <?= esc($gstin) ?></div>
            <div class="line"><span class="label">Date:</span> <?= esc($date) ?></div>
            <div class="line"><span class="label">DC No:</span> __________<?= esc($dcNo) ?>__________</div>
            <div class="line mt8"><span class="label">Consignment:</span> Return of Repair Item</div>
        </div>

        <div class="section">
            <div class="label">Consignee</div>
            <div class="line">To,</div>
            <div class="line"><?= esc((string) (($order['customer_name'] ?? '') !== '' ? $order['customer_name'] : 'Customer')) ?></div>
            <div class="line"><?= esc((string) ($order['order_no'] ?? '-')) ?></div>
            <div class="line"><span class="label">Reference Issue Voucher No:</span> <?= esc((string) ($packing['packing_no'] ?? '-')) ?></div>
            <div class="line">(Partially / Completely Return)</div>
        </div>

        <div class="section">
            <table>
                <colgroup>
                    <col style="width:5%">
                    <col style="width:12%">
                    <col style="width:7%">
                    <col style="width:38%">
                    <col style="width:38%">
                </colgroup>
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Description of Items</th>
                        <th>Purity</th>
                        <th>Items Qty</th>
                        <th>Amount Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1.</td>
                        <td>Studded Jewellery</td>
                        <td>18kt</td>
                        <td>
                            <table class="sub-table">
                                <colgroup>
                                    <col style="width:68%">
                                    <col style="width:32%">
                                </colgroup>
                                <tr><td class="label">Gross Wt (gms)</td><td class="right"><?= esc(number_format($gross, 3)) ?></td></tr>
                                <tr><td class="label">Net Wt Gold (gms)</td><td class="right"><?= esc(number_format($net, 3)) ?></td></tr>
                                <tr><td class="label">Diamond Wt (cts)</td><td class="right"><?= esc(number_format($dia, 3)) ?></td></tr>
                                <tr><td class="label">Colour Stone (cts)</td><td class="right"><?= esc(number_format($stone, 3)) ?></td></tr>
                                <tr><td class="label">Other Weight (gms)</td><td class="right"><?= esc(number_format($other, 3)) ?></td></tr>
                            </table>
                        </td>
                        <td>
                            <table class="sub-table">
                                <colgroup>
                                    <col style="width:68%">
                                    <col style="width:32%">
                                </colgroup>
                                <tr><td class="label">Total Valuation</td><td class="right"><?= esc(number_format($taxable, 2)) ?></td></tr>
                                <tr><td class="label">GST Value @ <?= esc(number_format($taxPercent, 2)) ?>%</td><td class="right"><?= esc(number_format($tax, 2)) ?></td></tr>
                                <tr><td class="label">Total Amount</td><td class="right"><?= esc(number_format($total, 2)) ?></td></tr>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="line mt8">The finished goods / studded jewellery are being sent to the consignee as per "Job Work Agreement"</div>
            <div class="sig">For M/s <?= esc($companyName) ?></div>
            <div class="sig" style="margin-top: 20px;">Authorized Signatory</div>
        </div>
    </div>
</div>
</body>
</html>
