<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi Order Packing List</title>
    <style>
        @page { size: A4 landscape; margin: 4mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202020; font-family: DejaVu Sans, sans-serif; font-size: 4.7pt; }
        .document-head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .document-head td { border: 0; padding: 0 3px 3px; }
        .company { color: #642039; font-size: 10pt; font-weight: 800; }
        .title { font-size: 8.5pt; font-weight: 800; letter-spacing: .6px; text-align: center; }
        .meta { color: #555; font-size: 5pt; text-align: right; }
        .packing-table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .packing-table thead { display: table-header-group; }
        .packing-table tr { page-break-inside: avoid; }
        .packing-table th, .packing-table td { border: .5pt solid #292929; padding: 2.2px 1.5px; text-align: center; vertical-align: middle; overflow-wrap: break-word; }
        .packing-table th { background: #6b2139; color: #fff; font-size: 4.25pt; font-weight: 800; line-height: 1.08; }
        .packing-table .item-row td { height: 45px; line-height: 1.2; }
        .packing-table .left { text-align: left; }
        .packing-table .details { font-size: 4.05pt; line-height: 1.28; }
        .packing-table .money, .packing-table .weight { white-space: nowrap; }
        .packing-table .photo { padding: 2px; }
        .packing-table .photo img { display: block; height: 39px; margin: 0 auto; max-width: 47px; object-fit: contain; }
        .packing-table .item-total td { background: #fbf4d9; font-weight: 800; height: auto; padding: 2.5px 1.5px; }
        .packing-table tfoot td { background: #ead9df; color: #33101e; font-weight: 900; height: auto; padding: 3.5px 1.5px; }
        .muted { color: #777; }
    </style>
</head>
<body>
<?php
    $rows = (array) ($report['rows'] ?? []);
    $totals = (array) ($report['totals'] ?? []);
    $fmtWeight = static fn(mixed $value): string => number_format((float) $value, 3, '.', '');
    $fmtPcs = static fn(mixed $value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    // Use text instead of the Unicode rupee glyph: older PDF renderers/fonts
    // otherwise replace it with a question mark on some devices.
    $fmtMoney = static fn(mixed $value): string => 'INR ' . number_format((float) $value, 2);
    $details = static fn(array $lines): string => $lines === [] ? '-' : implode('<br>', array_map('esc', $lines));
?>
<table class="document-head">
    <tr>
        <td class="company"><?= esc((string) (($company['company_name'] ?? '') ?: 'Aabhushan')) ?></td>
        <td class="title">MULTI ORDER PACKING LIST</td>
        <td class="meta"><?= esc((string) ($report['packing_no'] ?? '')) ?><br><?= count($rows) ?> jewellery item(s)</td>
    </tr>
</table>

<table class="packing-table">
    <colgroup>
        <col style="width:5%"><col style="width:7%"><col style="width:4.2%"><col style="width:4%">
        <col style="width:12%"><col style="width:3.3%"><col style="width:3.8%"><col style="width:5%">
        <col style="width:12%"><col style="width:3.3%"><col style="width:3.8%"><col style="width:5%">
        <col style="width:3.8%"><col style="width:3.8%"><col style="width:5%"><col style="width:4.2%">
        <col style="width:3.2%"><col style="width:4.8%"><col style="width:5.8%">
    </colgroup>
    <thead>
        <tr>
            <th>Photo</th><th>Jewellery Item</th><th>Metal /<br>Purity</th><th>Gross<br>Wt.</th>
            <th>Diamond Details</th><th>Dia.<br>PCS</th><th>Dia.<br>Wt.</th><th>Diamond<br>Amount</th>
            <th>Stone Details</th><th>Stone<br>PCS</th><th>Stone<br>Wt.</th><th>Stone<br>Amount</th>
            <th>Net<br>Wt.</th><th>Pure<br>Wt.</th><th>Metal<br>Amount</th><th>Wastage<br>(pure gm)</th>
            <th>Wastage<br>%</th><th>Labour<br>Charges</th><th>Total<br>Value</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr class="item-row">
            <td class="photo"><?php if (! empty($row['photo_data_uri'])): ?><img src="<?= esc((string) $row['photo_data_uri'], 'attr') ?>" alt="Jewellery"><?php else: ?><span class="muted">No photo</span><?php endif; ?></td>
            <td class="left"><strong><?= esc((string) (($row['item_description'] ?? '') ?: 'Jewellery')) ?></strong></td>
            <td><?= esc((string) (($row['purity_code'] ?? '') ?: (number_format((float) ($row['purity_percent'] ?? 0), 2) . '%'))) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['gross_weight_gm'] ?? 0)) ?></td>
            <td class="left details"><?= $details((array) ($row['diamond_details'] ?? [])) ?></td>
            <td><?= esc($fmtPcs($row['diamond_pcs'] ?? 0)) ?></td>
            <td class="weight"><?= esc($fmtWeight($row['diamond_weight_cts'] ?? 0)) ?></td>
            <td class="money"><?= esc($fmtMoney($row['diamond_amount_total'] ?? 0)) ?></td>
            <td class="left details"><?= $details((array) ($row['stone_details'] ?? [])) ?></td>
            <td><?= esc($fmtPcs($row['stone_pcs'] ?? 0)) ?></td>
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
        <tr class="item-total">
            <td colspan="3" class="left">ITEM TOTAL · <?= esc((string) (($row['item_description'] ?? '') ?: 'Jewellery')) ?></td>
            <td><?= esc($fmtWeight($row['gross_weight_gm'] ?? 0)) ?></td><td></td>
            <td><?= esc($fmtPcs($row['diamond_pcs'] ?? 0)) ?></td><td><?= esc($fmtWeight($row['diamond_weight_cts'] ?? 0)) ?></td><td><?= esc($fmtMoney($row['diamond_amount_total'] ?? 0)) ?></td><td></td>
            <td><?= esc($fmtPcs($row['stone_pcs'] ?? 0)) ?></td><td><?= esc($fmtWeight($row['stone_weight_cts'] ?? 0)) ?></td><td><?= esc($fmtMoney($row['stone_amount_total'] ?? 0)) ?></td>
            <td><?= esc($fmtWeight($row['net_gold_weight_gm'] ?? 0)) ?></td><td><?= esc($fmtWeight($row['pure_gold_weight_gm'] ?? 0)) ?></td><td><?= esc($fmtMoney($row['gold_amount'] ?? 0)) ?></td>
            <td><?= esc($fmtWeight($row['pure_wastage_weight_gm'] ?? 0)) ?></td><td></td><td><?= esc($fmtMoney($row['labour_amount'] ?? 0)) ?></td><td><?= esc($fmtMoney($row['total_valuation'] ?? 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="left">COMBINED TOTAL · <?= count($rows) ?> JEWELLERY ITEM(S)</td>
            <td><?= esc($fmtWeight($totals['gross_weight_gm'] ?? 0)) ?></td><td></td>
            <td><?= esc($fmtPcs($totals['diamond_pcs'] ?? 0)) ?></td><td><?= esc($fmtWeight($totals['diamond_weight_cts'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['diamond_amount_total'] ?? 0)) ?></td><td></td>
            <td><?= esc($fmtPcs($totals['stone_pcs'] ?? 0)) ?></td><td><?= esc($fmtWeight($totals['stone_weight_cts'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['stone_amount_total'] ?? 0)) ?></td>
            <td><?= esc($fmtWeight($totals['net_gold_weight_gm'] ?? 0)) ?></td><td><?= esc($fmtWeight($totals['pure_gold_weight_gm'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['gold_amount'] ?? 0)) ?></td>
            <td><?= esc($fmtWeight($totals['pure_wastage_weight_gm'] ?? 0)) ?></td><td></td><td><?= esc($fmtMoney($totals['labour_amount'] ?? 0)) ?></td><td><?= esc($fmtMoney($totals['total_valuation'] ?? 0)) ?></td>
        </tr>
    </tfoot>
</table>
</body>
</html>
