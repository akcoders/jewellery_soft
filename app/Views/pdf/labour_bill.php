<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2b2f83; margin: 0; }
        .page { padding: 20px 24px; }
        .sheet { border: 1px solid #8a8a8a; padding: 18px 18px 12px; }
        .top-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .top-grid td { border: 0; vertical-align: top; }
        .top-left, .top-center, .top-right { font-weight: 700; line-height: 1.55; }
        .top-left { width: 33%; font-size: 12px; }
        .top-center { width: 34%; text-align: center; font-size: 13px; }
        .top-right { width: 33%; text-align: right; font-size: 12px; }
        .vendor-name { text-align: center; font-size: 38px; font-weight: 800; letter-spacing: 1px; margin: 14px 0 8px; }
        .subhead { text-align: center; font-size: 11px; font-weight: 700; line-height: 1.35; margin-bottom: 10px; }
        .party-table, .item-table, .bottom-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .party-table td, .item-table td, .item-table th, .bottom-table td { border: 1px solid #777; padding: 5px 7px; vertical-align: top; }
        .party-table .label { font-weight: 800; }
        .party-name { font-size: 15px; font-weight: 800; }
        .right-box-title { font-size: 13px; font-weight: 800; }
        .item-table th { text-align: center; font-size: 10.5px; font-weight: 800; }
        .center { text-align: center; }
        .right { text-align: right; }
        .desc { color: #2b2f83; }
        .spacer-row td { height: 20px; }
        .words-box { font-size: 11px; color: #222; }
        .declaration { font-size: 10.5px; color: #2b2f83; line-height: 1.35; }
        .bank-sign { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .bank-sign td { border: 0; vertical-align: top; }
        .bank-box { font-size: 11px; line-height: 1.45; font-weight: 700; }
        .sign-box { text-align: right; font-size: 13px; font-weight: 800; padding-top: 48px; }
        .black { color: #222; }
    </style>
</head>
<body>
<?php
    $bill = is_array($bill ?? null) ? $bill : [];
    $company = is_array($company ?? null) ? $company : [];
    $receive = is_array($receive ?? null) ? $receive : [];
    $purityCode = trim((string) ($purityCode ?? '-')) ?: '-';
    $karigarName = strtoupper(trim((string) ($bill['karigar_name'] ?? '-')));
    $karigarAddress = trim(implode(', ', array_filter([
        (string) ($bill['karigar_address'] ?? ''),
        (string) ($bill['karigar_city'] ?? ''),
        (string) ($bill['karigar_state'] ?? ''),
        (string) ($bill['karigar_pincode'] ?? ''),
    ]))) ?: '-';
    $companyAddress = trim(implode(', ', array_filter([
        (string) ($company['address_line'] ?? ''),
        (string) ($company['city'] ?? ''),
        (string) ($company['state'] ?? ''),
        (string) ($company['pincode'] ?? ''),
    ]))) ?: '-';
    $studdedLabel = ((float) ($receive['diamond_weight_cts'] ?? 0) > 0 || (float) ($receive['stone_weight_cts'] ?? 0) > 0)
        ? $purityCode . ' STUDDED JEWELLRY (Net Wt)'
        : $purityCode . ' JEWELLRY (Net Wt)';
    $billDate = trim((string) ($bill['bill_date'] ?? ''));
    $billDate = $billDate !== '' ? date('d/m/Y', strtotime($billDate)) : '-';
    $otherAmount = round((float) ($receive['other_amount'] ?? 0), 2);
    $stoneWeight = (float) ($receive['stone_weight_cts'] ?? 0);
    $diamondWeight = (float) ($receive['diamond_weight_cts'] ?? 0);
    $grossWeight = (float) ($receive['gross_weight_gm'] ?? 0);
    $netWeight = (float) ($receive['net_gold_weight_gm'] ?? 0);
    $labourRate = (float) ($receive['labour_rate_per_gm'] ?? ($bill['rate_per_gm'] ?? 0));
    $labourAmount = (float) ($receive['labour_amount'] ?? ($bill['labour_amount'] ?? 0));
    $invoiceNo = (string) ($bill['bill_no'] ?? '-');
    $transportMode = 'By Hand';
    $blankRows = max(0, 2 - ($otherAmount > 0 ? 1 : 0));
?>
<div class="page">
    <div class="sheet">
        <table class="top-grid">
            <tr>
                <td class="top-left">
                    GSTIN : <?= esc((string) ($bill['karigar_gstin'] ?? '-')) ?><br>
                    PAN No: <?= esc((string) ($bill['karigar_pan_no'] ?? '-')) ?>
                </td>
                <td class="top-center">
                    <div style="text-decoration: underline;">Job Work</div>
                    <div style="text-decoration: underline;">Tax Invoice</div>
                </td>
                <td class="top-right">
                    Cell:<?= esc((string) ($bill['karigar_phone'] ?? '-')) ?><br>
                    <?= esc((string) ($bill['karigar_email'] ?? '-')) ?>
                </td>
            </tr>
        </table>

        <div class="vendor-name"><?= esc($karigarName) ?></div>
        <div class="subhead">
            :: Designer &amp; Manufacturer ::<br>
            <?= esc(trim((string) ($bill['karigar_department'] ?? 'Jewellery Job Work')) ?: 'Jewellery Job Work') ?><br>
            <?= esc(trim((string) ($bill['karigar_skills'] ?? '')) ?: $karigarAddress) ?>
        </div>

        <table class="party-table">
            <colgroup>
                <col style="width:60%">
                <col style="width:40%">
            </colgroup>
            <tr>
                <td>
                    <div class="party-name">Name: <?= esc(strtoupper((string) ($company['company_name'] ?? '-'))) ?></div>
                    <div style="margin-top:14px;" class="party-name">
                        Address: <?= esc($companyAddress) ?>
                    </div>
                    <div style="margin-top:18px;" class="party-name">
                        Party GSTIN: <?= esc((string) ($company['gstin'] ?? '-')) ?>
                    </div>
                    <div style="margin-top:14px;" class="party-name">
                        Party Pan No: -
                    </div>
                </td>
                <td>
                    <div class="right-box-title">INVOICE NO: <span class="black"><?= esc($invoiceNo) ?></span></div>
                    <div style="margin-top:22px;" class="right-box-title">Date: <span class="black"><?= esc($billDate) ?></span></div>
                    <div style="margin-top:18px;" class="right-box-title">State Code: <span class="black"><?= esc((string) ($stateCode ?? '-')) ?></span></div>
                    <div style="margin-top:18px;" class="right-box-title">Transport Mode: <span class="black"><?= esc($transportMode) ?></span></div>
                </td>
            </tr>
        </table>

        <table class="item-table">
            <colgroup>
                <col style="width:7%">
                <col style="width:10%">
                <col style="width:39%">
                <col style="width:7%">
                <col style="width:11%">
                <col style="width:11%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th>DC No:</th>
                    <th>HSN/ SAC<br>CODE</th>
                    <th>DESCRIPTION</th>
                    <th>UOM</th>
                    <th>WEIGHT</th>
                    <th>RATE</th>
                    <th>AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center"></td>
                    <td class="center">998892</td>
                    <td class="center desc"><?= esc($studdedLabel) ?></td>
                    <td class="center">GRM</td>
                    <td class="right"><?= esc(number_format($netWeight, 3)) ?></td>
                    <td class="center"><?= esc(rtrim(rtrim(number_format($labourRate, 2), '0'), '.')) ?>/-</td>
                    <td class="right"><?= esc(number_format($labourAmount, 2)) ?></td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td class="center desc">Gross Wt</td>
                    <td class="center">GRM</td>
                    <td class="right"><?= esc(number_format($grossWeight, 3)) ?></td>
                    <td></td><td></td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td class="center desc">Diamond (Wt)</td>
                    <td class="center">CT</td>
                    <td class="right"><?= $diamondWeight > 0 ? esc(number_format($diamondWeight, 3)) : '--' ?></td>
                    <td></td><td></td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td class="center desc">Semi-Precious Stones / CS</td>
                    <td class="center">CT</td>
                    <td class="right"><?= $stoneWeight > 0 ? esc(number_format($stoneWeight, 3)) : '--' ?></td>
                    <td></td><td></td>
                </tr>
                <?php if ((float) ($receive['other_weight_gm'] ?? 0) > 0): ?>
                    <tr>
                        <td></td><td></td>
                        <td class="center desc">Other Weight</td>
                        <td class="center">GRM</td>
                        <td class="right"><?= esc(number_format((float) ($receive['other_weight_gm'] ?? 0), 3)) ?></td>
                        <td></td><td></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td></td><td></td>
                    <td class="center desc">Wastage</td>
                    <td class="center">GRM</td>
                    <td class="right"><?= esc(number_format((float) ($wastageWeight ?? 0), 3)) ?></td>
                    <td></td><td></td>
                </tr>
                <?php if ($otherAmount > 0): ?>
                    <tr>
                        <td></td><td></td>
                        <td class="center desc">Other Charges</td>
                        <td class="center">-</td>
                        <td></td>
                        <td></td>
                        <td class="right"><?= esc(number_format($otherAmount, 2)) ?></td>
                    </tr>
                <?php endif; ?>
                <?php for ($i = 0; $i < $blankRows; $i++): ?>
                    <tr class="spacer-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="4" rowspan="5" class="words-box">
                        <strong>Amount in Words:</strong> <?= esc((string) ($amountInWords ?? '-')) ?>
                        <div class="declaration" style="margin-top:22px;">
                            <strong>Declaration:</strong><br>
                            1. I/We declare that this invoice shows price of goods or services described and that all particulars are true and correct.<br>
                            2. Error and omission in this invoice shall be subjected to the jurisdiction of <?= esc((string) ($bill['karigar_city'] ?? $bill['karigar_state'] ?? '')) ?>.
                        </div>
                    </td>
                    <td colspan="2" class="right-box-title">Total Amount before GST</td>
                    <td class="right black"><?= esc(number_format((float) ($taxableAmount ?? 0), 2)) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="right-box-title">SGST:</td>
                    <td class="right black"><?= esc(number_format((float) ($sgstAmount ?? 0), 2)) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="right-box-title">CGST:</td>
                    <td class="right black"><?= esc(number_format((float) ($cgstAmount ?? 0), 2)) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="right-box-title">IGST: <?= esc(rtrim(rtrim(number_format((float) ($igstPercent ?? 0), 2), '0'), '.')) ?>%</td>
                    <td class="right black"><?= esc(number_format((float) ($igstAmount ?? 0), 2)) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="right-box-title">Total Amount Inclusive of GST</td>
                    <td class="right black"><?= esc(number_format((float) ($totalWithTax ?? 0), 2)) ?></td>
                </tr>
            </tbody>
        </table>

        <table class="bank-sign">
            <tr>
                <td style="width:60%;">
                    <div class="bank-box">
                        Our Bank Details:<br>
                        Name : <?= esc((string) ($bill['karigar_name'] ?? '-')) ?><br>
                        Bank Name : <?= esc((string) ($bill['karigar_bank_name'] ?? '-')) ?><br>
                        Branch : <?= esc((string) ($bill['karigar_city'] ?? '-')) ?><br>
                        Account Number : <?= esc((string) ($bill['karigar_bank_account_no'] ?? '-')) ?><br>
                        IFSC Code : <?= esc((string) ($bill['karigar_ifsc_code'] ?? '-')) ?>
                    </div>
                </td>
                <td style="width:40%;" class="sign-box">
                    FOR <?= esc($karigarName) ?>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
