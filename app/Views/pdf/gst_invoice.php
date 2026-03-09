<!doctype html>
<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px}.h{font-size:16px;font-weight:bold;margin-bottom:10px}</style></head><body>
<div class="h">GST Invoice</div>
<p><strong>Invoice No:</strong> <?= esc((string)($invoice['invoice_no'] ?? '-')) ?> | <strong>Date:</strong> <?= esc((string)($invoice['invoice_date'] ?? '-')) ?></p>
<p><strong>Customer:</strong> <?= esc((string)($customer_name ?? '-')) ?></p>
<table><thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th><th>GST%</th><th>GST Amt</th></tr></thead><tbody>
<?php foreach (($items ?? []) as $i): ?>
<tr><td><?= esc((string)($i['description'] ?? '-')) ?></td><td><?= esc(number_format((float)($i['qty'] ?? 0),3)) ?></td><td><?= esc(number_format((float)($i['rate'] ?? 0),2)) ?></td><td><?= esc(number_format((float)($i['amount'] ?? 0),2)) ?></td><td><?= esc(number_format((float)($i['gst_percent'] ?? 0),2)) ?></td><td><?= esc(number_format((float)($i['gst_amount'] ?? 0),2)) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><strong>Total:</strong> <?= esc(number_format((float)($invoice['total_amount'] ?? 0),2)) ?></p>
</body></html>
