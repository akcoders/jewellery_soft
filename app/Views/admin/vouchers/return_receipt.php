<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc((string) (isset($title) ? $title : 'Return Receipt')) ?></title>
    <style>
        body { margin: 0; padding: 14px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .no-print { margin-bottom: 10px; text-align: right; }
        .voucher { max-width: 980px; margin: 0 auto; border: 2px solid #000; background: #fff; }
        .section { border-top: 1px solid #000; padding: 6px 8px; }
        .section:first-child { border-top: 0; }
        .center { text-align: center; }
        .title-main { font-size: 26px; font-weight: 700; letter-spacing: 0.5px; margin: 0; }
        .subtitle { margin-top: 2px; font-size: 12px; font-weight: 700; }
        .company-name { font-size: 22px; font-weight: 700; margin: 0; }
        .company-line { font-size: 13px; margin: 1px 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 4px; font-weight: 700; }
        .meta .lbl { width: 130px; white-space: nowrap; }
        .simple td { border: 0; padding: 2px 3px; vertical-align: top; font-weight: 700; }
        .simple .lbl { width: 160px; white-space: nowrap; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th, .grid td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        .grid th { font-size: 11px; font-weight: 700; }
        .text-right { text-align: right; }
        .line-head { font-weight: 700; margin-bottom: 3px; }
        .terms p { margin: 2px 0; font-weight: 700; }
        .sign-row { display: table; width: 100%; border-top: 1px solid #000; }
        .sign-cell { display: table-cell; width: 50%; padding: 10px 8px 6px; font-weight: 700; vertical-align: bottom; height: 36px; }
        .sign-cell.right { text-align: right; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .voucher { border-width: 1px; }
        }
    </style>
</head>
<body>
<?php
$materialType = isset($materialType) ? (string) $materialType : 'Material';
$returnDate = isset($return['return_date']) ? (string) $return['return_date'] : '-';
$receiptNo = isset($return['voucher_no']) && $return['voucher_no'] !== '' ? (string) $return['voucher_no'] : (isset($return['id']) ? ('RET-' . $return['id']) : '-');
$orderNo = isset($return['order_no']) && $return['order_no'] !== '' ? (string) $return['order_no'] : '-';
$issueNo = isset($return['issue_voucher_no']) && $return['issue_voucher_no'] !== '' ? (string) $return['issue_voucher_no'] : '-';
$issueDate = isset($return['issue_date']) && $return['issue_date'] !== '' ? (string) $return['issue_date'] : '-';
$purpose = isset($return['purpose']) && $return['purpose'] !== '' ? (string) $return['purpose'] : '-';
$locationName = isset($return['location_name']) && $return['location_name'] !== '' ? (string) $return['location_name'] : '-';
$notes = isset($return['notes']) && trim((string) $return['notes']) !== '' ? (string) $return['notes'] : '-';

$companyName = isset($company['company_name']) && trim((string) $company['company_name']) !== '' ? (string) $company['company_name'] : 'Company Name';
$companyAddress = trim((string) (isset($company['address_line']) ? $company['address_line'] : ''));
$companyCityState = trim((string) ((isset($company['city']) ? $company['city'] : '') . ', ' . (isset($company['state']) ? $company['state'] : '') . ' ' . (isset($company['pincode']) ? $company['pincode'] : '')));
$companyPhone = isset($company['phone']) && trim((string) $company['phone']) !== '' ? (string) $company['phone'] : '-';
$companyEmail = isset($company['email']) && trim((string) $company['email']) !== '' ? (string) $company['email'] : '-';
$companyWebsite = isset($company['website']) && trim((string) $company['website']) !== '' ? (string) $company['website'] : '-';
$companyGstin = isset($company['gstin']) && trim((string) $company['gstin']) !== '' ? (string) $company['gstin'] : '-';
$companyPan = isset($company['pan']) && trim((string) $company['pan']) !== '' ? (string) $company['pan'] : '-';
$companyTin = isset($company['tin_no']) && trim((string) $company['tin_no']) !== '' ? (string) $company['tin_no'] : '-';
$companyExcise = isset($company['excise_reg_no']) && trim((string) $company['excise_reg_no']) !== '' ? (string) $company['excise_reg_no'] : '-';
$jurisdictionState = isset($company['state']) && trim((string) $company['state']) !== '' ? trim((string) $company['state']) : 'Company State';

$partyName = isset($return['return_from']) && trim((string) $return['return_from']) !== ''
    ? (string) $return['return_from']
    : ((isset($return['karigar_name']) && trim((string) $return['karigar_name']) !== '') ? (string) $return['karigar_name'] : '-');
$partyPhone = isset($return['karigar_phone']) && trim((string) $return['karigar_phone']) !== '' ? (string) $return['karigar_phone'] : '-';
$partyEmail = isset($return['karigar_email']) && trim((string) $return['karigar_email']) !== '' ? (string) $return['karigar_email'] : '-';
$partyAddressParts = [];
if (isset($return['karigar_address']) && trim((string) $return['karigar_address']) !== '') {
    $partyAddressParts[] = trim((string) $return['karigar_address']);
}
$partyCityState = trim((string) ((isset($return['karigar_city']) ? $return['karigar_city'] : '') . ' ' . (isset($return['karigar_state']) ? $return['karigar_state'] : '') . ' ' . (isset($return['karigar_pincode']) ? $return['karigar_pincode'] : '')));
if ($partyCityState !== '') {
    $partyAddressParts[] = preg_replace('/\s+/', ' ', $partyCityState);
}
$partyAddress = $partyAddressParts ? implode(', ', $partyAddressParts) : '-';

$totalValue = isset($totals['total_value']) ? (float) $totals['total_value'] : 0.0;
$rows = isset($lines) && is_array($lines) ? $lines : [];
$minRows = 8;
$blankRows = $minRows > count($rows) ? $minRows - count($rows) : 0;
?>

<div class="no-print">
    <button onclick="window.print()">Print Receipt</button>
</div>

<div class="voucher">
    <div class="section center">
        <h1 class="company-name"><?= esc($companyName) ?></h1>
        <div class="company-line"><?= esc($companyAddress !== '' ? $companyAddress : 'Company Address') ?></div>
        <div class="company-line"><?= esc($companyCityState !== '' ? $companyCityState : 'Address') ?></div>

        <table class="meta" style="margin-top:6px;">
            <tr>
                <td class="lbl">Tel:</td>
                <td><?= esc($companyPhone) ?></td>
                <td class="lbl">Excise Reg No:</td>
                <td><?= esc($companyExcise) ?></td>
            </tr>
            <tr>
                <td class="lbl">Email:</td>
                <td><?= esc($companyEmail) ?></td>
                <td class="lbl">TIN/CST No:</td>
                <td><?= esc($companyTin) ?></td>
            </tr>
            <tr>
                <td class="lbl">Website:</td>
                <td><?= esc($companyWebsite) ?></td>
                <td class="lbl">PAN No:</td>
                <td><?= esc($companyPan) ?></td>
            </tr>
            <tr>
                <td class="lbl"></td>
                <td></td>
                <td class="lbl">GSTIN:</td>
                <td><?= esc($companyGstin) ?></td>
            </tr>
        </table>
    </div>

    <div class="section center">
        <p class="title-main">RETURN RECEIPT</p>
        <div class="subtitle">(Subject to <?= esc($jurisdictionState) ?> Jurisdiction)</div>
    </div>

    <div class="section">
        <table class="simple">
            <tr>
                <td class="lbl">Date:</td>
                <td><?= esc($returnDate) ?></td>
                <td class="lbl">Receipt No:</td>
                <td><?= esc($receiptNo) ?></td>
            </tr>
            <tr>
                <td class="lbl">Order No:</td>
                <td><?= esc($orderNo) ?></td>
                <td class="lbl">Material:</td>
                <td><?= esc($materialType) ?></td>
            </tr>
            <tr>
                <td class="lbl">Issue Ref:</td>
                <td><?= esc($issueNo) ?></td>
                <td class="lbl">Issue Date:</td>
                <td><?= esc($issueDate) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="simple">
            <tr>
                <td class="lbl">Received From:</td>
                <td><?= esc($partyName) ?></td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td><?= esc($partyAddress) ?></td>
            </tr>
            <tr>
                <td class="lbl">Contact Number:</td>
                <td><?= esc($partyPhone) ?></td>
            </tr>
            <tr>
                <td class="lbl">Contact Email:</td>
                <td><?= esc($partyEmail) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="line-head">Description of Returned Goods</div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Description</th>
                    <th style="width:150px;">Grade / Purity</th>
                    <th style="width:90px;" class="text-right">PCS</th>
                    <th style="width:110px;" class="text-right">Weight</th>
                    <th style="width:110px;" class="text-right">Rate</th>
                    <th style="width:120px;" class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <?php $sr = 1; ?>
                <?php foreach ($rows as $line): ?>
                    <?php if ($materialType === 'Gold'): ?>
                        <?php
                        $desc = trim((string) ((isset($line['form_type']) ? $line['form_type'] : 'Gold') . ' ' . (isset($line['color_name']) ? $line['color_name'] : '')));
                        $grade = isset($line['master_purity_code']) && $line['master_purity_code'] !== '' ? $line['master_purity_code'] : (isset($line['purity_code']) ? $line['purity_code'] : '-');
                        $pcs = '-';
                        $weight = number_format((float) (isset($line['weight_gm']) ? $line['weight_gm'] : 0), 3) . ' gm';
                        $rate = isset($line['rate_per_gm']) && $line['rate_per_gm'] !== null ? number_format((float) $line['rate_per_gm'], 2) : '-';
                        $value = isset($line['line_value']) && $line['line_value'] !== null ? number_format((float) $line['line_value'], 2) : '-';
                        ?>
                    <?php elseif ($materialType === 'Stone'): ?>
                        <?php
                        $desc = trim((string) ((isset($line['product_name']) ? $line['product_name'] : '-') . ' ' . (isset($line['stone_type']) ? $line['stone_type'] : '')));
                        $grade = '-';
                        $pcs = number_format((float) (isset($line['qty']) ? $line['qty'] : 0), 3);
                        $weight = '-';
                        $rate = isset($line['rate']) && $line['rate'] !== null ? number_format((float) $line['rate'], 2) : '-';
                        $value = isset($line['line_value']) && $line['line_value'] !== null ? number_format((float) $line['line_value'], 2) : '-';
                        ?>
                    <?php else: ?>
                        <?php
                        $desc = trim((string) ((isset($line['diamond_type']) ? $line['diamond_type'] : '-') . ' ' . (isset($line['shape']) ? $line['shape'] : '')));
                        $chalni = (isset($line['chalni_from']) && $line['chalni_from'] !== null && isset($line['chalni_to']) && $line['chalni_to'] !== null)
                            ? ($line['chalni_from'] . '-' . $line['chalni_to'])
                            : 'NA';
                        $grade = trim((string) ($chalni . ' / ' . (isset($line['color']) ? $line['color'] : '-') . ' / ' . (isset($line['clarity']) ? $line['clarity'] : '-')));
                        $pcs = number_format((float) (isset($line['pcs']) ? $line['pcs'] : 0), 3);
                        $weight = number_format((float) (isset($line['carat']) ? $line['carat'] : 0), 3) . ' cts';
                        $rate = isset($line['rate_per_carat']) && $line['rate_per_carat'] !== null ? number_format((float) $line['rate_per_carat'], 2) : '-';
                        $value = isset($line['line_value']) && $line['line_value'] !== null ? number_format((float) $line['line_value'], 2) : '-';
                        ?>
                    <?php endif; ?>
                    <tr>
                        <td class="text-right"><?= $sr ?></td>
                        <td><?= esc($desc) ?></td>
                        <td><?= esc((string) $grade) ?></td>
                        <td class="text-right"><?= esc((string) $pcs) ?></td>
                        <td class="text-right"><?= esc((string) $weight) ?></td>
                        <td class="text-right"><?= esc((string) $rate) ?></td>
                        <td class="text-right"><?= esc((string) $value) ?></td>
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
        <div class="line-head">Reference Details</div>
        <table class="grid">
            <tbody>
                <tr>
                    <td style="width:20%;"><strong>Order No</strong></td>
                    <td style="width:30%;"><?= esc($orderNo) ?></td>
                    <td style="width:20%;"><strong>Issue Ref</strong></td>
                    <td style="width:30%;"><?= esc($issueNo) ?></td>
                </tr>
                <tr>
                    <td><strong>Purpose</strong></td>
                    <td><?= esc($purpose) ?></td>
                    <td><strong>Location</strong></td>
                    <td><?= esc($locationName) ?></td>
                </tr>
                <tr>
                    <td><strong>Notes</strong></td>
                    <td colspan="3"><?= esc($notes) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section terms">
        <p>Declaration:</p>
        <p>1. Returned material received in good condition.</p>
        <p>2. Goods are for jobwork reconciliation only, not for direct sale.</p>
        <p>3. Total Return Value: Rs. <?= esc(number_format($totalValue, 2)) ?>/-</p>
    </div>

    <div class="sign-row">
        <div class="sign-cell">Received From (Karigar Sign)</div>
        <div class="sign-cell right">Authorized Signatory</div>
    </div>
</div>
</body>
</html>
