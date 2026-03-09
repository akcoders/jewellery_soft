<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc((string) ($title ?? 'Issue Voucher')) ?></title>
    <style>
        @page {
            margin: 8mm 10mm;
        }
        body {
            margin: 0;
            padding: 12px 14px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
        }
        .no-print {
            margin-bottom: 10px;
            text-align: right;
        }
        .voucher {
            max-width: 980px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #000;
            background: #fff;
        }
        .section {
            border-top: 1px solid #000;
            padding: 6px 8px;
        }
        .section:first-child {
            border-top: 0;
        }
        .center {
            text-align: center;
        }
        .title-main {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .subtitle {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
        }
        .company-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }
        .company-line {
            font-size: 13px;
            margin: 1px 0;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta td {
            vertical-align: top;
            padding: 2px 4px;
            font-weight: 700;
        }
        .meta .lbl {
            width: 130px;
            white-space: nowrap;
        }
        .meta .right {
            width: 45%;
        }
        .company-meta {
            margin-top: 6px;
            text-align: center;
            font-weight: 700;
            line-height: 1.45;
        }
        .company-meta-line {
            margin: 0;
        }
        .simple td {
            border: 0;
            padding: 2px 3px;
            vertical-align: top;
            font-weight: 700;
        }
        .simple .lbl {
            width: 140px;
            white-space: nowrap;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid th,
        .grid td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: top;
        }
        .grid th {
            font-size: 11px;
            font-weight: 700;
        }
        .text-right {
            text-align: right;
        }
        .line-head {
            font-weight: 700;
            margin-bottom: 3px;
        }
        .terms p {
            margin: 2px 0;
            font-weight: 700;
        }
        .sign-row {
            display: table;
            width: 100%;
            border-top: 1px solid #000;
        }
        .sign-cell {
            display: table-cell;
            width: 50%;
            padding: 10px 8px 6px;
            font-weight: 700;
            vertical-align: bottom;
            height: 36px;
        }
        .sign-cell.right {
            text-align: right;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .voucher {
                border-width: 1px;
            }
        }
    </style>
</head>
<body>
<?php
$issueDate = isset($issue['issue_date']) ? (string) $issue['issue_date'] : '-';
$orderNo = isset($issue['order_no']) && $issue['order_no'] !== '' ? (string) $issue['order_no'] : '-';
$purpose = isset($issue['purpose']) && $issue['purpose'] !== '' ? (string) $issue['purpose'] : '-';
$warehouseName = isset($issue['warehouse_name']) && $issue['warehouse_name'] !== '' ? (string) $issue['warehouse_name'] : '-';
$notes = isset($issue['notes']) && trim((string) $issue['notes']) !== '' ? (string) $issue['notes'] : '-';

$companyName = isset($company['company_name']) && trim((string) $company['company_name']) !== '' ? (string) $company['company_name'] : 'Company Name';
$companyAddress = trim((string) ($company['address_line'] ?? ''));
$companyCityState = trim((string) (($company['city'] ?? '') . ', ' . ($company['state'] ?? '') . ' ' . ($company['pincode'] ?? '')));
$companyPhone = isset($company['phone']) && trim((string) $company['phone']) !== '' ? (string) $company['phone'] : '-';
$companyEmail = isset($company['email']) && trim((string) $company['email']) !== '' ? (string) $company['email'] : '-';
$companyWebsite = isset($company['website']) && trim((string) $company['website']) !== '' ? (string) $company['website'] : '-';
$companyGstin = isset($company['gstin']) && trim((string) $company['gstin']) !== '' ? (string) $company['gstin'] : '-';
$companyPan = isset($company['pan']) && trim((string) $company['pan']) !== '' ? (string) $company['pan'] : '-';
$companyTin = isset($company['tin_no']) && trim((string) $company['tin_no']) !== '' ? (string) $company['tin_no'] : '-';
$companyExcise = isset($company['excise_reg_no']) && trim((string) $company['excise_reg_no']) !== '' ? (string) $company['excise_reg_no'] : '-';
$jurisdictionState = isset($company['state']) && trim((string) $company['state']) !== '' ? trim((string) $company['state']) : 'Company State';

$supplierName = trim((string) ($supplierName ?? ''));
if ($supplierName === '') {
    $supplierName = '-';
}

$supplierPhone = isset($issue['labour_phone']) && trim((string) $issue['labour_phone']) !== '' ? (string) $issue['labour_phone'] : '-';
$supplierEmail = isset($issue['labour_email']) && trim((string) $issue['labour_email']) !== '' ? (string) $issue['labour_email'] : '-';

$supplierAddressParts = [];
if (isset($issue['labour_address']) && trim((string) $issue['labour_address']) !== '') {
    $supplierAddressParts[] = trim((string) $issue['labour_address']);
}
$cityStatePin = trim((string) (($issue['labour_city'] ?? '') . ' ' . ($issue['labour_state'] ?? '') . ' ' . ($issue['labour_pincode'] ?? '')));
if ($cityStatePin !== '') {
    $supplierAddressParts[] = preg_replace('/\s+/', ' ', $cityStatePin);
}
$supplierAddress = $supplierAddressParts ? implode(', ', $supplierAddressParts) : '-';

$rows = [];
foreach (($goldLines ?? []) as $line) {
    $rows[] = [
        'description' => trim((string) (($line['form_type'] ?? 'Gold') . ' ' . ($line['color_name'] ?? ''))),
        'grade' => (string) (($line['master_purity_code'] ?? $line['purity_code'] ?? '-') ?: '-'),
        'pcs' => '-',
        'weight' => number_format((float) ($line['weight_gm'] ?? 0), 3) . ' gm',
        'rate' => ($line['rate_per_gm'] ?? null) === null ? '-' : number_format((float) $line['rate_per_gm'], 2),
        'value' => ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2),
    ];
}
foreach (($diamondLines ?? []) as $line) {
    $chalni = ((string) ($line['chalni_from'] ?? '') !== '' || (string) ($line['chalni_to'] ?? '') !== '')
        ? ((string) ($line['chalni_from'] ?? '') . '-' . (string) ($line['chalni_to'] ?? ''))
        : 'NA';
    $rows[] = [
        'description' => trim((string) (($line['diamond_type'] ?? '-') . ' ' . ($line['shape'] ?? ''))),
        'grade' => trim($chalni . ' / ' . (string) ($line['color'] ?? '-') . ' / ' . (string) ($line['clarity'] ?? '-')),
        'pcs' => number_format((float) ($line['pcs'] ?? 0), 3),
        'weight' => number_format((float) ($line['carat'] ?? 0), 3) . ' cts',
        'rate' => ($line['rate_per_carat'] ?? null) === null ? '-' : number_format((float) $line['rate_per_carat'], 2),
        'value' => ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2),
    ];
}
foreach (($stoneLines ?? []) as $line) {
    $rows[] = [
        'description' => trim((string) (($line['product_name'] ?? '-') . ' ' . ($line['stone_type'] ?? ''))),
        'grade' => '-',
        'pcs' => number_format((float) ($line['pcs'] ?? 0), 3),
        'weight' => number_format((float) ($line['qty'] ?? 0), 3),
        'rate' => ($line['rate'] ?? null) === null ? '-' : number_format((float) $line['rate'], 2),
        'value' => ($line['line_value'] ?? null) === null ? '-' : number_format((float) $line['line_value'], 2),
    ];
}

$minRows = 8;
$blankRows = $minRows > count($rows) ? $minRows - count($rows) : 0;
?>

<div class="no-print">
    <button onclick="window.print()">Print Voucher</button>
</div>

    <div class="voucher">
    <div class="section center">
        <h1 class="company-name"><?= esc($companyName) ?></h1>
        <div class="company-line"><?= esc($companyAddress !== '' ? $companyAddress : 'Company Address') ?></div>
        <div class="company-line"><?= esc($companyCityState !== '' ? $companyCityState : 'Address') ?></div>
        <div class="company-meta">
            <p class="company-meta-line">Tel: <?= esc($companyPhone) ?> | Email: <?= esc($companyEmail) ?> | Website: <?= esc($companyWebsite) ?></p>
            <p class="company-meta-line">GSTIN: <?= esc($companyGstin) ?> | PAN No: <?= esc($companyPan) ?> | TIN/CST No: <?= esc($companyTin) ?></p>
            <p class="company-meta-line">Excise Reg No: <?= esc($companyExcise) ?></p>
        </div>
    </div>

    <div class="section center">
        <p class="title-main">ISSUE VOUCHER</p>
        <div class="subtitle">(Subject to <?= esc($jurisdictionState) ?> Jurisdiction)</div>
    </div>

    <div class="section">
        <table class="simple">
            <tr>
                <td class="lbl">Date:</td>
                <td><?= esc($issueDate) ?></td>
                <td class="lbl">IV No:</td>
                <td><?= esc((string) ($voucherNo ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="lbl">Order No:</td>
                <td><?= esc($orderNo) ?></td>
                <td class="lbl">Material:</td>
                <td><?= esc((string) ($materialType ?? '-')) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="line-head">Supplier Details</div>
        <table class="simple">
            <tr>
                <td class="lbl">Supplier/Manufacturer:</td>
                <td><?= esc($supplierName) ?></td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td><?= esc($supplierAddress) ?></td>
            </tr>
            <tr>
                <td class="lbl">Contact Number:</td>
                <td><?= esc($supplierPhone) ?></td>
            </tr>
            <tr>
                <td class="lbl">Contact Email:</td>
                <td><?= esc($supplierEmail) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="line-head">Description of Goods</div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Description</th>
                    <th style="width:140px;">Grade / Purity</th>
                    <th style="width:90px;" class="text-right">PCS</th>
                    <th style="width:110px;" class="text-right">Weight</th>
                    <th style="width:110px;" class="text-right">Rate</th>
                    <th style="width:120px;" class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <?php $sr = 1; ?>
                <?php foreach ($rows as $line): ?>
                    <tr>
                        <td class="text-right"><?= $sr ?></td>
                        <td><?= esc((string) ($line['description'] ?? '-')) ?></td>
                        <td><?= esc((string) ($line['grade'] ?? '-')) ?></td>
                        <td class="text-right"><?= esc((string) ($line['pcs'] ?? '-')) ?></td>
                        <td class="text-right"><?= esc((string) ($line['weight'] ?? '-')) ?></td>
                        <td class="text-right"><?= esc((string) ($line['rate'] ?? '-')) ?></td>
                        <td class="text-right"><?= esc((string) ($line['value'] ?? '-')) ?></td>
                    </tr>
                    <?php $sr++; ?>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $blankRows; $i++): ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="line-head">Order Details</div>
        <table class="grid">
            <tbody>
                <tr>
                    <td style="width:20%;"><strong>Order No</strong></td>
                    <td style="width:30%;"><?= esc($orderNo) ?></td>
                    <td style="width:20%;"><strong>Purpose</strong></td>
                    <td style="width:30%;"><?= esc($purpose) ?></td>
                </tr>
                <tr>
                    <td><strong>Warehouse</strong></td>
                    <td><?= esc($warehouseName) ?></td>
                    <td><strong>Issue To</strong></td>
                    <td><?= esc($supplierName) ?></td>
                </tr>
                <tr>
                    <td><strong>Notes</strong></td>
                    <td colspan="3"><?= esc($notes) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section terms">
        <p>Terms & Conditions:</p>
        <p>1. Partial delivery allowed.</p>
        <p>2. No changes/deviations allowed in the specification ordered.</p>
        <p>3. Finished goods to be delivered within the time frame agreed.</p>
        <p style="margin-top:8px;">Declaration:</p>
        <p>1. Material being moved for jobwork purpose only.</p>
        <p>2. GOODS ARE NOT FOR SALE.</p>
        <p>3. Value of Goods: Rs. <?= esc(number_format((float) ($totalValue ?? 0), 2)) ?>/-</p>
    </div>

    <div class="sign-row">
        <div class="sign-cell">I declare that I have received goods in good condition.</div>
        <div class="sign-cell right">Authorized Signatory</div>
    </div>
</div>
</body>
</html>
