<!doctype html>
<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px}.h{font-size:16px;font-weight:bold;margin-bottom:10px}</style></head><body>
<div class="h">Gold Issue Challan</div>
<p><strong>Voucher:</strong> <?= esc((string)($voucher['voucher_no'] ?? '-')) ?> | <strong>Date:</strong> <?= esc((string)($voucher['voucher_date'] ?? '-')) ?></p>
<p><strong>Order:</strong> <?= esc((string)($voucher['order_id'] ?? '-')) ?> | <strong>Karigar:</strong> <?= esc((string)($karigar_name ?? '-')) ?></p>
<table><thead><tr><th>Item</th><th>Purity</th><th>Weight(gm)</th><th>Fine Gold</th></tr></thead><tbody>
<?php foreach (($lines ?? []) as $l): ?>
<tr><td><?= esc((string)($l['material_name'] ?? $l['item_key'])) ?></td><td><?= esc((string)($l['gold_purity_id'] ?? '-')) ?></td><td><?= esc(number_format((float)($l['qty_weight'] ?? 0),3)) ?></td><td><?= esc(number_format((float)($l['fine_gold'] ?? 0),3)) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p>Karigar Signature: ___________________</p>
</body></html>
