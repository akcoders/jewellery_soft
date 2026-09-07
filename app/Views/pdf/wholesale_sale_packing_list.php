<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page{size:A4 landscape;margin:8mm}body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}.header{border:1px solid #333;padding:9px 10px}.header h1{font-size:17px;margin:0 0 5px}.meta{width:100%;border-collapse:collapse}.meta td{padding:3px 12px 3px 0}.items{border-collapse:collapse;margin-top:10px;width:100%}.items th,.items td{border:1px solid #555;padding:6px;vertical-align:middle}.items th{background:#f0f2f5;font-size:8px;text-transform:uppercase}.thumb{height:58px;max-width:76px;object-fit:contain}.center{text-align:center}.right{text-align:right}.bold{font-weight:700}.totals td{background:#f7f7f7;font-weight:700}.note{font-size:8px;margin-top:8px}.muted{color:#666}
    </style>
</head>
<body>
<div class="header">
    <h1>Packing List</h1>
    <table class="meta">
        <tr><td><strong>Company:</strong> <?= esc((string) ($company['company_name'] ?? 'Aabhushan')) ?></td><td><strong>Packing No:</strong> <?= esc((string) ($sale['packing_no'] ?? '-')) ?></td><td><strong>Date:</strong> <?= esc((string) ($sale['packing_date'] ?? $sale['sale_date'] ?? '-')) ?></td></tr>
        <tr><td><strong>Customer:</strong> <?= esc((string) ($sale['customer_name'] ?? '-')) ?></td><td><strong>Invoice:</strong> <?= esc((string) ($sale['invoice_no'] ?? '-')) ?></td><td><strong>Items:</strong> <?= count($items ?? []) ?></td></tr>
    </table>
</div>
<table class="items">
    <thead><tr><th>Sl</th><th>Image</th><th>Tag</th><th>Jewellery / Design</th><th>Order</th><th>Purity</th><th>Qty</th><th>Gross Wt (gm)</th><th>Net Gold (gm)</th><th>Diamond (cts)</th><th>Stone (cts)</th></tr></thead>
    <tbody>
    <?php $qty=0;$gross=0;$gold=0;$diamond=0;$stone=0; foreach (($items ?? []) as $index => $item): $qty+=(float)($item['qty']??0);$gross+=(float)($item['gross_wt']??0);$gold+=(float)($item['net_gold_wt']??0);$diamond+=(float)($item['diamond_cts']??0);$stone+=(float)($item['stone_wt']??0); ?>
        <tr>
            <td class="center"><?= $index + 1 ?></td>
            <td class="center"><?php if (! empty($item['embedded_image'])): ?><img class="thumb" src="<?= esc((string) $item['embedded_image'], 'attr') ?>" alt="Jewellery"><?php else: ?><span class="muted">No photo</span><?php endif; ?></td>
            <td class="bold"><?= esc((string) ($item['tag_no'] ?? '-')) ?></td>
            <td><?= esc((string) ($item['description'] ?? '-')) ?></td>
            <td><?= esc((string) ($item['order_no'] ?? '-')) ?><br><span class="muted"><?= esc((string) ($item['order_name'] ?? '')) ?></span></td>
            <td class="center"><?= esc((string) ($item['purity_label'] ?? '-')) ?></td>
            <td class="right"><?= number_format((float) ($item['qty'] ?? 0), 0) ?></td>
            <td class="right"><?= number_format((float) ($item['gross_wt'] ?? 0), 3) ?></td>
            <td class="right"><?= number_format((float) ($item['net_gold_wt'] ?? 0), 3) ?></td>
            <td class="right"><?= number_format((float) ($item['diamond_cts'] ?? 0), 3) ?></td>
            <td class="right"><?= number_format((float) ($item['stone_wt'] ?? 0), 3) ?></td>
        </tr>
    <?php endforeach; ?>
        <tr class="totals"><td colspan="6" class="right">Total</td><td class="right"><?= number_format($qty, 0) ?></td><td class="right"><?= number_format($gross, 3) ?></td><td class="right"><?= number_format($gold, 3) ?></td><td class="right"><?= number_format($diamond, 3) ?></td><td class="right"><?= number_format($stone, 3) ?></td></tr>
    </tbody>
</table>
<div class="note"><strong>Note:</strong> This combined packing list was generated automatically from the finished jewellery selected in sale bill <?= esc((string) ($sale['invoice_no'] ?? '-')) ?>.</div>
</body>
</html>
