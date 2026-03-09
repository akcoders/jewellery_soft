<!doctype html>
<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px;text-align:left}.h{font-size:16px;font-weight:bold;margin-bottom:10px}</style></head><body>
<div class="h">Job Card</div>
<p><strong>Job Card No:</strong> <?= esc((string)($job_card['job_card_no'] ?? '-')) ?> | <strong>Date:</strong> <?= esc((string)($job_card['created_at'] ?? date('Y-m-d'))) ?></p>
<p><strong>Order:</strong> <?= esc((string)($order['order_no'] ?? '-')) ?> | <strong>Karigar:</strong> <?= esc((string)($karigar_name ?? '-')) ?></p>
<table><thead><tr><th>Stage</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
<?php foreach (($stages ?? []) as $s): ?>
<tr><td><?= esc((string)$s['stage_name']) ?></td><td><?= esc((string)$s['status']) ?></td><td><?= esc((string)($s['remarks'] ?? '')) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</body></html>
