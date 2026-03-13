<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
        .page { padding: 18px 22px; }
        .sheet { border: 1px solid #222; }
        .section { border-bottom: 1px solid #222; padding: 8px 10px; }
        .section:last-child { border-bottom: 0; }
        .company { text-align: center; line-height: 1.35; }
        .company .name { font-size: 18px; font-weight: 800; }
        .doc-title { text-align: center; font-size: 17px; font-weight: 800; margin-bottom: 2px; }
        .doc-subtitle { text-align: center; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #222; padding: 5px 6px; vertical-align: top; }
        th { background: #f4f4f4; font-weight: 700; }
        .meta td { border: 0; padding: 2px 4px; }
        .meta .label { width: 20%; font-weight: 700; }
        .right { text-align: right; }
        .center { text-align: center; }
        .terms { line-height: 1.5; }
        .footer-row td { border: 0; padding: 0; font-weight: 700; }
    </style>
</head>
<body>
<?php
    $issue = is_array($issue ?? null) ? $issue : [];
    $company = is_array($company ?? null) ? $company : [];
    $lines = is_array($lines ?? null) ? $lines : [];
    $totals = is_array($totals ?? null) ? $totals : [];
    $materialType = (string) ($materialType ?? 'Material');
    $state = strtoupper(trim((string) ($company['state'] ?? '')));
    $jurisdiction = $state !== '' ? $state . ' Jurisdiction' : 'Jurisdiction';
    $companyAddress = trim(implode(', ', array_filter([
        (string) ($company['address_line'] ?? ''),
        (string) ($company['city'] ?? ''),
        (string) ($company['state'] ?? ''),
        (string) ($company['pincode'] ?? ''),
    ])));

    $lineDescription = static function (array $row, string $type): string {
        return match (strtolower($type)) {
            'diamond' => trim(implode(' ', array_filter([(string) ($row['diamond_type'] ?? ''), (string) ($row['shape'] ?? '')]))),
            'gold' => trim(implode(' ', array_filter([(string) ($row['color_name'] ?? ''), (string) ($row['form_type'] ?? '')]))),
            'stone' => trim(implode(' ', array_filter([(string) ($row['product_name'] ?? ''), (string) ($row['stone_type'] ?? '')]))),
            default => '-',
        };
    };
    $lineGrade = static function (array $row, string $type): string {
        return match (strtolower($type)) {
            'diamond' => trim(implode(' / ', array_filter([
                trim(((string) ($row['chalni_from'] ?? '')) !== '' || ((string) ($row['chalni_to'] ?? '')) !== '' ? ((string) ($row['chalni_from'] ?? '')) . '-' . ((string) ($row['chalni_to'] ?? '')) : ''),
                (string) ($row['color'] ?? ''),
                (string) ($row['clarity'] ?? ''),
            ]))),
            'gold' => trim(implode(' / ', array_filter([
                (string) ($row['purity_code'] ?? ''),
                ((string) ($row['purity_percent'] ?? '')) !== '' ? rtrim(rtrim(number_format((float) ($row['purity_percent'] ?? 0), 3), '0'), '.') . '%' : '',
            ]))),
            'stone' => '-',
            default => '-',
        };
    };
    $pcsValue = static function (array $row, string $type): string {
        return match (strtolower($type)) {
            'diamond' => number_format((float) ($row['pcs'] ?? 0), 3),
            'stone' => number_format((float) ($row['qty'] ?? 0), 3),
            default => '-',
        };
    };
    $weightValue = static function (array $row, string $type): string {
        return match (strtolower($type)) {
            'diamond' => number_format((float) ($row['carat'] ?? 0), 3) . ' cts',
            'gold' => number_format((float) ($row['weight_gm'] ?? 0), 3) . ' gm',
            'stone' => number_format((float) ($row['weight_cts'] ?? 0), 3) . ' cts',
            default => '-',
        };
    };
    $rateValue = static function (array $row): string {
        if (array_key_exists('rate_per_carat', $row)) {
            return number_format((float) ($row['rate_per_carat'] ?? 0), 2);
        }
        if (array_key_exists('rate_per_gm', $row)) {
            return number_format((float) ($row['rate_per_gm'] ?? 0), 2);
        }
        return number_format((float) ($row['rate'] ?? 0), 2);
    };
?>
<div class="page">
    <div class="sheet">
        <div class="section company">
            <div class="name"><?= esc((string) ($company['company_name'] ?? '-')) ?></div>
            <div><?= esc($companyAddress !== '' ? $companyAddress : '-') ?></div>
            <div>Tel: <?= esc((string) ($company['phone'] ?? '-')) ?> | Email: <?= esc((string) ($company['email'] ?? '-')) ?> | Website: <?= esc((string) ($company['website'] ?? '-')) ?></div>
            <div>GSTIN: <?= esc((string) ($company['gstin'] ?? '-')) ?> | PAN No: <?= esc((string) ($company['pan_no'] ?? '-')) ?> | TIN/CST No: <?= esc((string) ($company['tin_no'] ?? '-')) ?></div>
            <div>Excise Reg No: <?= esc((string) ($company['excise_reg_no'] ?? '-')) ?></div>
        </div>

        <div class="section">
            <div class="doc-title">ISSUE VOUCHER</div>
            <div class="doc-subtitle">(Subject to <?= esc($jurisdiction) ?>)</div>
        </div>

        <div class="section">
            <table class="meta">
                <tr><td class="label">Date:</td><td><?= esc((string) ($issue['issue_date'] ?? '-')) ?></td><td class="label">IV No:</td><td><?= esc((string) ($issue['voucher_no'] ?? '-')) ?></td></tr>
                <tr><td class="label">Order No:</td><td><?= esc((string) ($issue['order_no'] ?? '-')) ?></td><td class="label">Material:</td><td><?= esc($materialType) ?></td></tr>
            </table>
        </div>

        <div class="section">
            <div style="font-weight:700; margin-bottom:6px;">Supplier Details</div>
            <table class="meta">
                <tr><td class="label">Supplier/Manufacturer:</td><td><?= esc((string) ($issue['issue_to'] ?? $issue['karigar_name'] ?? '-')) ?></td></tr>
                <tr><td class="label">Address:</td><td><?= esc(trim(implode(', ', array_filter([(string) ($issue['labour_address'] ?? ''), (string) ($issue['labour_city'] ?? ''), (string) ($issue['labour_state'] ?? ''), (string) ($issue['labour_pincode'] ?? '')])) ?: '-') ?></td></tr>
                <tr><td class="label">Contact Number:</td><td><?= esc((string) ($issue['labour_phone'] ?? '-')) ?></td></tr>
                <tr><td class="label">Contact Email:</td><td><?= esc((string) ($issue['labour_email'] ?? '-')) ?></td></tr>
            </table>
        </div>

        <div class="section">
            <div style="font-weight:700; margin-bottom:6px;">Description of Goods</div>
            <table>
                <colgroup><col style="width:6%"><col style="width:22%"><col style="width:23%"><col style="width:12%"><col style="width:14%"><col style="width:11%"><col style="width:12%"></colgroup>
                <thead><tr><th>#</th><th>Description</th><th>Grade / Purity</th><th>PCS</th><th>Weight</th><th>Rate</th><th>Value</th></tr></thead>
                <tbody>
                    <?php if ($lines === []): ?>
                        <tr><td colspan="7" class="center">No lines</td></tr>
                    <?php else: foreach ($lines as $index => $row): ?>
                        <tr>
                            <td class="center"><?= esc((string) ($index + 1)) ?></td>
                            <td><?= esc($lineDescription((array) $row, $materialType) ?: '-') ?></td>
                            <td><?= esc($lineGrade((array) $row, $materialType) ?: '-') ?></td>
                            <td class="right"><?= esc($pcsValue((array) $row, $materialType)) ?></td>
                            <td class="right"><?= esc($weightValue((array) $row, $materialType)) ?></td>
                            <td class="right"><?= esc($rateValue((array) $row)) ?></td>
                            <td class="right"><?= esc(number_format((float) ($row['line_value'] ?? 0), 2)) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div style="font-weight:700; margin-bottom:6px;">Order Details</div>
            <table>
                <colgroup><col style="width:20%"><col style="width:30%"><col style="width:20%"><col style="width:30%"></colgroup>
                <tr><td><strong>Order No</strong></td><td><?= esc((string) ($issue['order_no'] ?? '-')) ?></td><td><strong>Purpose</strong></td><td><?= esc((string) ($issue['purpose'] ?? '-')) ?></td></tr>
                <tr><td><strong>Warehouse</strong></td><td><?= esc((string) ($issue['warehouse_name'] ?? '-')) ?></td><td><strong>Issue To</strong></td><td><?= esc((string) ($issue['issue_to'] ?? $issue['karigar_name'] ?? '-')) ?></td></tr>
                <tr><td><strong>Notes</strong></td><td colspan="3"><?= esc((string) ($issue['notes'] ?? '-')) ?></td></tr>
            </table>
        </div>

        <div class="section terms">
            <div><strong>Terms &amp; Conditions:</strong></div>
            <div>1. Partial delivery allowed.</div>
            <div>2. No changes/deviations allowed in the specification ordered.</div>
            <div>3. Finished goods to be delivered within the time frame agreed.</div>
            <div style="margin-top:8px;"><strong>Declaration:</strong></div>
            <div>1. Material being moved for jobwork purpose only.</div>
            <div>2. GOODS ARE NOT FOR SALE.</div>
            <div>3. Value of Goods: Rs. <?= esc(number_format((float) ($totals['total_value'] ?? 0), 2)) ?>/ -</div>
        </div>

        <div class="section">
            <table class="footer-row"><tr><td>I declare that I have received goods in good condition.</td><td class="right">Authorized Signatory</td></tr></table>
        </div>
    </div>
</div>
</body>
</html>
