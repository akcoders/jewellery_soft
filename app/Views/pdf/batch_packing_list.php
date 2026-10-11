<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi Order Packing List</title>
    <style>
        @page { size: A3 landscape; margin: 5mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f1f1f; font-family: DejaVu Sans, sans-serif; font-size: 5.4pt; }
        .document-head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .document-head td { border: 0; padding: 0 3px 3px; }
        .company { color: #642039; font-size: 11pt; font-weight: 800; }
        .title { font-size: 9pt; font-weight: 800; letter-spacing: .7px; text-align: center; }
        .meta { color: #555; font-size: 5.5pt; text-align: right; }
        .packing-table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .packing-table thead { display: table-header-group; }
        .packing-table tr { page-break-inside: avoid; }
        .packing-table th, .packing-table td { border: .55pt solid #292929; padding: 2.6px 2px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .packing-table th { background: #6b2139; color: #fff; font-size: 5.05pt; font-weight: 800; line-height: 1.08; }
        .packing-table td { height: 51px; line-height: 1.26; }
        .packing-table .highlight { background: #fff900; }
        .packing-table .left { text-align: left; }
        .packing-table .details { font-size: 4.7pt; line-height: 1.35; }
        .packing-table .money, .packing-table .weight { white-space: nowrap; }
        .packing-table .photo { padding: 2px; }
        .packing-table .photo img { display: block; height: 45px; margin: 0 auto; max-width: 64px; object-fit: contain; }
        .packing-table tfoot td { background: #f2e7ea; font-weight: 800; height: auto; padding: 4px 2px; }
        .muted { color: #777; }
    </style>
</head>
<body>
<?php
    $rows = (array) ($report['rows'] ?? []);
    $totals = (array) ($report['totals'] ?? []);
    $fmtWeight = static fn(mixed $value): string => number_format((float) $value, 3, '.', '');
    $fmtMoney = static fn(mixed $value): string => '₹' . number_format((float) $value, 2);
    $fmtDate = static function (mixed $value): string {
        $text = trim((string) $value);
        return $text !== '' && strtotime($text) !== false ? date('d.m.y', strtotime($text)) : '-';
    };
?>
<table class="document-head">
    <tr>
        <td class="company"><?= esc((string) (($company['company_name'] ?? '') ?: 'Aabhushan')) ?></td>
        <td class="title">MULTI ORDER PACKING LIST</td>
        <td class="meta">
            <?= esc((string) ($report['packing_no'] ?? '')) ?><br>
            Generated <?= esc(date('d M Y, h:i A', strtotime((string) ($report['generated_at'] ?? 'now')))) ?> · <?= count($rows) ?> order(s)
        </td>
    </tr>
</table>

<table class="packing-table">
    <colgroup>
        <col style="width:3.5%"><col style="width:2.3%"><col style="width:2.3%"><col style="width:6%">
        <col style="width:5%"><col style="width:4%"><col style="width:4%"><col style="width:4%"><col style="width:4.2%">
        <col style="width:5%"><col style="width:3.8%"><col style="width:3.5%"><col style="width:12.3%"><col style="width:3.3%">
        <col style="width:3.3%"><col style="width:4.3%"><col style="width:3.3%"><col style="width:3.3%"><col style="width:4.7%">
        <col style="width:4.2%"><col style="width:4%"><col style="width:4.5%"><col style="width:5.2%">
    </colgroup>
    <thead>
        <tr>
            <th>Vendor</th><th>Batch<br>No.</th><th>Item</th><th>Photo</th>
            <th>Customer<br>/ Party</th><th>Sales M.</th><th>Order Date</th><th>Due Date</th><th>Receiving<br>Date</th>
            <th>Reference</th><th>Metal /<br>Purity</th><th>Gross</th><th>Stone Details</th><th>Stone PCS</th>
            <th>Stone<br>Wt.</th><th>Stone<br>Amount</th><th>Net Wt.</th><th>Pure</th><th>Metal<br>Amount</th>
            <th>Labour<br>Wastage (gm pure)</th><th>Labour<br>Wastage (%)</th><th>Labour<br>Charges</th><th>Total Value</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= esc((string) (($row['karigar_name'] ?? '') ?: '-')) ?></td>
            <td><?= (int) ($row['batch_no'] ?? 0) ?></td>
            <td title="<?= esc((string) ($row['item_description'] ?? ''), 'attr') ?>"><?= (int) ($row['item_no'] ?? 1) ?></td>
            <td class="photo">
                <?php if (! empty($row['photo_data_uri'])): ?>
                    <img src="<?= esc((string) $row['photo_data_uri'], 'attr') ?>" alt="Jewellery">
                <?php else: ?><span class="muted">No photo</span><?php endif; ?>
            </td>
            <td class="highlight"><?= esc((string) (($row['customer_name'] ?? '') ?: '-')) ?></td>
            <td class="highlight"><?= esc((string) (($row['sales_person_name'] ?? '') ?: '-')) ?></td>
            <td class="highlight"><?= esc($fmtDate(($row['order_received_date'] ?? '') ?: ($row['order_created_at'] ?? ''))) ?></td>
            <td class="highlight"><?= esc($fmtDate($row['due_date'] ?? '')) ?></td>
            <td><?= esc($fmtDate(($row['received_at'] ?? '') ?: ($row['receipt_created_at'] ?? ''))) ?></td>
            <td><strong><?= esc((string) ($row['order_no'] ?? '-')) ?></strong><br><span class="muted"><?= esc((string) ($row['item_description'] ?? '')) ?></span></td>
            <td><?= esc((string) (($row['purity_code'] ?? '') ?: (number_format((float) ($row['purity_percent'] ?? 0), 2) . '%'))) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['gross_weight_gm'] ?? 0)) ?></td>
            <td class="left details"><?php if (($row['stone_details'] ?? []) === []): ?>-<?php else: ?><?= implode('<br>', array_map('esc', (array) $row['stone_details'])) ?><?php endif; ?></td>
            <td><?= esc(rtrim(rtrim(number_format((float) ($row['stone_pcs'] ?? 0), 3, '.', ''), '0'), '.')) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['stone_weight_cts'] ?? 0)) ?></td>
            <td class="money"><?= esc($fmtMoney($row['stone_amount_total'] ?? 0)) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['net_gold_weight_gm'] ?? 0)) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['pure_gold_weight_gm'] ?? 0)) ?></td>
            <td class="money"><?= esc($fmtMoney($row['gold_amount'] ?? 0)) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['pure_wastage_weight_gm'] ?? 0)) ?></td>
            <td><?= esc(number_format((float) ($row['wastage_percent'] ?? 0), 3, '.', '')) ?>%</td>
            <td class="money"><?= esc($fmtMoney($row['labour_amount'] ?? 0)) ?></td>
            <td class="money"><strong><?= esc($fmtMoney($row['total_valuation'] ?? 0)) ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="11" class="left">TOTAL · <?= count($rows) ?> COMPLETED &amp; RECEIVED ORDER(S)</td>
            <td><?= esc($fmtWeight($totals['gross_weight_gm'] ?? 0)) ?></td><td></td>
            <td><?= esc(rtrim(rtrim(number_format((float) ($totals['stone_pcs'] ?? 0), 3, '.', ''), '0'), '.')) ?></td>
            <td><?= esc($fmtWeight($totals['stone_weight_cts'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['stone_amount_total'] ?? 0)) ?></td>
            <td><?= esc($fmtWeight($totals['net_gold_weight_gm'] ?? 0)) ?></td><td><?= esc($fmtWeight($totals['pure_gold_weight_gm'] ?? 0)) ?></td>
            <td><?= esc($fmtMoney($totals['gold_amount'] ?? 0)) ?></td><td><?= esc($fmtWeight($totals['pure_wastage_weight_gm'] ?? 0)) ?></td>
            <td></td><td><?= esc($fmtMoney($totals['labour_amount'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['total_valuation'] ?? 0)) ?></td>
        </tr>
    </tfoot>
</table>
</body>
</html>
