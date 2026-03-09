<!doctype html>
<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px}.h{font-size:16px;font-weight:bold;margin-bottom:10px}</style></head><body>
<div class="h">Diamond Bag Issue Challan</div>
<p><strong>Voucher:</strong> <?= esc((string)($voucher['voucher_no'] ?? '-')) ?> | <strong>Date:</strong> <?= esc((string)($voucher['voucher_date'] ?? '-')) ?></p>
<table><thead><tr><th>Bag/Item</th><th>Shape</th><th>Chalni</th><th>Color</th><th>Clarity</th><th>PCS</th><th>CTS</th></tr></thead><tbody>
<?php foreach (($lines ?? []) as $l): ?>
<tr><td><?= esc((string)($l['item_key'] ?? '-')) ?></td><td><?= esc((string)($l['shape'] ?? '-')) ?></td><td><?= esc((string)($l['chalni_size'] ?? '-')) ?></td><td><?= esc((string)($l['color'] ?? '-')) ?></td><td><?= esc((string)($l['clarity'] ?? '-')) ?></td><td><?= esc(number_format((float)($l['qty_pcs'] ?? 0),3)) ?></td><td><?= esc(number_format((float)($l['qty_cts'] ?? 0),3)) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p>Karigar Signature: ___________________</p>
</body></html>
