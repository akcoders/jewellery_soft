<!doctype html>
<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:6px}.h{font-size:16px;font-weight:bold;margin-bottom:10px}</style></head><body>
<div class="h">Ledger Statement</div>
<p><strong>Account:</strong> <?= esc((string)($account['account_name'] ?? '-')) ?> (<?= esc((string)($account['account_code'] ?? '-')) ?>)</p>
<table><thead><tr><th>Date</th><th>Voucher</th><th>Item</th><th>Debit(+)</th><th>Credit(-)</th><th>Balance</th></tr></thead><tbody>
<?php $bal = 0.0; foreach (($entries ?? []) as $e): ?>
<?php $delta = (float)($e['delta_weight'] ?? 0); $bal += $delta; ?>
<tr><td><?= esc((string)($e['voucher_date'] ?? '-')) ?></td><td><?= esc((string)($e['voucher_no'] ?? '-')) ?></td><td><?= esc((string)($e['item_key'] ?? '-')) ?></td><td><?= esc($delta > 0 ? number_format($delta,3) : '-') ?></td><td><?= esc($delta < 0 ? number_format(abs($delta),3) : '-') ?></td><td><?= esc(number_format($bal,3)) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</body></html>
