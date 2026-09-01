<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .diamond-ledger-hero { background:linear-gradient(135deg,#fff 0%,#fff9ec 100%); border:1px solid #eadfca; border-left:4px solid var(--erp-gold); border-radius:14px; padding:18px 20px; }
    .diamond-ledger-kpis { display:grid; gap:12px; grid-template-columns:repeat(6,minmax(130px,1fr)); }
    .diamond-ledger-kpi { background:#fff; border:1px solid #e4e9ef; border-radius:12px; padding:13px 14px; }
    .diamond-ledger-kpi small { color:#748094; display:block; font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .diamond-ledger-kpi strong { color:#172235; display:block; font-size:1.15rem; margin-top:3px; }
    .diamond-ledger-shell { background:#fff; border:1px solid #dfe5ec; border-radius:12px; overflow:hidden; }
    .diamond-ledger-scroll { max-width:100%; overflow-x:auto; }
    .diamond-matrix { font-size:.76rem; min-width:max-content !important; width:100% !important; }
    .diamond-matrix th,.diamond-matrix td { border-color:#cfd6df !important; padding:.48rem .52rem !important; white-space:nowrap; }
    .diamond-matrix thead tr:first-child th { background:#283548 !important; color:#fff; text-align:center; }
    .diamond-matrix thead tr:nth-child(2) th { background:#eef2f6 !important; color:#263449; font-size:.7rem; text-align:center; text-transform:uppercase; }
    .diamond-matrix .product-head { border-left:2px solid #aeb8c5 !important; min-width:142px; }
    .diamond-matrix .product-in { background:#f1fbf5; color:#187542; text-align:right; }
    .diamond-matrix .product-out { background:#fff5f5; color:#ae2632; text-align:right; }
    .diamond-matrix tbody tr:hover td { box-shadow:inset 0 0 0 9999px rgba(179,18,31,.035); }
    .diamond-matrix .txn-description { min-width:190px; white-space:normal; }
    .diamond-matrix tfoot tr:last-child th { background:#dbeafe !important; color:#153862; font-weight:800; }
    .diamond-type-badge { border-radius:999px; display:inline-flex; font-size:.68rem; font-weight:800; padding:.25rem .52rem; text-transform:uppercase; }
    @media (max-width:1199.98px) { .diamond-ledger-kpis { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:575.98px) { .diamond-ledger-kpis { grid-template-columns:repeat(2,1fr); } .diamond-ledger-hero { padding:14px; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$productRows = array_values($products ?? []);
$typeClass = static fn(string $type): string => match ($type) {
    'Opening' => 'bg-primary-subtle text-primary',
    'Purchase', 'Return', 'Adjustment In' => 'bg-success-subtle text-success',
    'Issue', 'Adjustment Out' => 'bg-danger-subtle text-danger',
    default => 'bg-secondary-subtle text-secondary',
};
?>

<div class="diamond-ledger-hero mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><div class="text-uppercase small fw-bold text-warning mb-1">Diamond inventory</div><h4 class="mb-1">Product-wise Diamond Ledger</h4><div class="text-muted small">PAN, MQ, RC and every other product/grade are loaded dynamically from Diamond Product Master.</div></div>
        <div class="d-flex gap-2"><a href="<?= site_url('admin/diamond-inventory/stock') ?>" class="btn btn-outline-primary"><i class="fe fe-box me-1"></i>Stock</a><a href="<?= site_url('admin/diamond-inventory/items') ?>" class="btn btn-outline-primary"><i class="fe fe-tag me-1"></i>Product Master</a></div>
    </div>
</div>

<div class="diamond-ledger-kpis mb-3">
    <?php foreach ([
        ['Opening', number_format((float) ($summary['opening_cts'] ?? 0), 3) . ' cts'],
        ['Received', number_format((float) ($summary['received_cts'] ?? 0), 3) . ' cts'],
        ['Issued', number_format((float) ($summary['issued_cts'] ?? 0), 3) . ' cts'],
        ['Closing', number_format((float) ($summary['closing_cts'] ?? 0), 3) . ' cts'],
        ['Transactions', (string) (int) ($summary['transactions'] ?? 0)],
        ['Products', (string) (int) ($summary['products'] ?? 0)],
    ] as [$label,$value]): ?><div class="diamond-ledger-kpi"><small><?= esc($label) ?></small><strong><?= esc($value) ?></strong></div><?php endforeach; ?>
</div>

<form method="get" action="<?= esc((string) ($ledgerBaseUrl ?? ''), 'attr') ?>" class="card mb-3">
    <div class="card-body"><div class="row g-2 align-items-end">
        <div class="col-sm-6 col-xl-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= esc((string) ($filters['from'] ?? '')) ?>"></div>
        <div class="col-sm-6 col-xl-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= esc((string) ($filters['to'] ?? '')) ?>"></div>
        <div class="col-sm-6 col-xl-2"><label class="form-label">Product</label><select name="product" class="form-select js-searchable-select"><option value="">All products</option><?php foreach (($productOptions ?? []) as $product): ?><option value="<?= esc((string) $product['label'], 'attr') ?>" <?= (string) ($filters['product'] ?? '') === (string) $product['label'] ? 'selected' : '' ?>><?= esc((string) $product['label']) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-xl-2"><label class="form-label">Transaction</label><select name="txn_type" class="form-select"><option value="">All transactions</option><?php foreach (($transactionTypes ?? []) as $type): ?><option value="<?= esc((string) $type, 'attr') ?>" <?= (string) ($filters['txn_type'] ?? '') === (string) $type ? 'selected' : '' ?>><?= esc((string) $type) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-xl-2"><label class="form-label">Karigar</label><select name="karigar_id" class="form-select js-searchable-select"><option value="0">All karigars</option><?php foreach (($karigars ?? []) as $karigar): ?><option value="<?= (int) $karigar['id'] ?>" <?= (int) ($filters['karigar_id'] ?? 0) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-xl-2"><label class="form-label">Order</label><input type="text" name="order_no" class="form-control" value="<?= esc((string) ($filters['order_no'] ?? '')) ?>" placeholder="Order number"></div>
        <div class="col-12"><button class="btn btn-primary"><i class="fe fe-filter me-1"></i>Apply</button> <a href="<?= esc((string) ($ledgerBaseUrl ?? ''), 'attr') ?>" class="btn btn-light">Reset</a></div>
    </div></div>
</form>

<div class="diamond-ledger-shell">
    <div class="p-3 border-bottom"><strong>Movement Matrix</strong><div class="text-muted small">Hover values for PCS. Scroll horizontally to see all product columns.</div></div>
    <div class="diamond-ledger-scroll">
        <table id="diamond-product-ledger" class="table datatable diamond-matrix mb-0" data-ledger-table="true" data-ledger-matrix="true" data-dt-page-length="25" data-dt-ordering="false">
            <thead>
                <tr><th colspan="4">Transaction details</th><?php foreach ($productRows as $product): ?><th colspan="2" class="product-head"><?= esc((string) $product['label']) ?></th><?php endforeach; ?></tr>
                <tr><th>Date</th><th>Description</th><th>Reference / Order</th><th>Transaction</th><?php foreach ($productRows as $product): ?><th>Received</th><th>Issued</th><?php endforeach; ?></tr>
                <tr class="erp-column-filters"><?php foreach (array_merge(['Date','Description','Reference','Transaction'], array_fill(0,count($productRows)*2,'Value')) as $label): ?><th><input type="search" class="erp-column-filter" placeholder="Filter <?= esc($label, 'attr') ?>"></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php if (($filters['from'] ?? '') !== '' && array_sum(array_map(static fn(array $v): float => (float) ($v['cts'] ?? 0), $opening ?? [])) != 0.0): ?>
                    <tr><td><?= esc((string) $filters['from']) ?></td><td class="txn-description"><strong>Brought Forward</strong><small class="d-block text-muted">Balance before selected From date</small></td><td>OPENING</td><td><span class="diamond-type-badge bg-primary-subtle text-primary">Opening</span></td><?php foreach ($productRows as $product): ?><?php $value=(float)($opening[$product['label']]['cts']??0); $pcs=(float)($opening[$product['label']]['pcs']??0); ?><td class="product-in" title="<?= number_format($pcs,3) ?> pcs"><?= $value != 0.0 ? number_format($value,3) : '-' ?></td><td class="product-out">-</td><?php endforeach; ?></tr>
                <?php endif; ?>
                <?php foreach (($rows ?? []) as $row): ?><tr>
                    <td><?= esc((string) ($row['txn_date'] ?? '-')) ?></td>
                    <td class="txn-description"><strong><?= esc((string) ($row['description'] ?? '-')) ?></strong><?php if (!empty($row['notes'])): ?><small class="d-block text-muted"><?= esc((string) $row['notes']) ?></small><?php endif; ?></td>
                    <td><strong><?= esc((string) ($row['reference_no'] ?? '-')) ?></strong><?php if (!empty($row['order_no'])): ?><small class="d-block text-muted"><?= esc((string) $row['order_no']) ?></small><?php endif; ?></td>
                    <td><span class="diamond-type-badge <?= esc($typeClass((string) ($row['txn_type'] ?? ''))) ?>"><?= esc((string) ($row['txn_type'] ?? '-')) ?></span></td>
                    <?php foreach ($productRows as $product): ?><?php $movement=$row['products'][$product['label']]??[]; $received=(float)($movement['received_cts']??0); $issued=(float)($movement['issued_cts']??0); ?><td class="product-in" title="<?= number_format((float)($movement['received_pcs']??0),3) ?> pcs"><?= $received > 0 ? number_format($received,3) : '-' ?></td><td class="product-out" title="<?= number_format((float)($movement['issued_pcs']??0),3) ?> pcs"><?= $issued > 0 ? number_format($issued,3) : '-' ?></td><?php endforeach; ?>
                </tr><?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th>Filtered total</th><th></th><th></th><th></th><?php foreach ($productRows as $product): ?><th></th><th></th><?php endforeach; ?></tr>
                <tr><th>Closing balance</th><th></th><th></th><th></th><?php foreach ($productRows as $product): ?><th title="Closing carats"><?= number_format((float)($totals[$product['label']]['closing_cts']??0),3) ?></th><th></th><?php endforeach; ?></tr>
            </tfoot>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
