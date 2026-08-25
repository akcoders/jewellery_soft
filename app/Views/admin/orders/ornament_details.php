<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $rows = array_values((array) ($detailRows ?? []));
    $lineCount = max(8, count($rows));
    $grWtFromItems = 0.0;
    foreach (($items ?? []) as $it) {
        $grWtFromItems += (float) ($it['gross_wt'] ?? 0);
    }
    $grWt = (float) ($receive['gross'] ?? 0);
    if ($grWt <= 0) {
        $grWt = $grWtFromItems;
    }
    $netWt = (float) ($receive['net'] ?? 0);
    $pureWt = (float) ($receive['pure'] ?? 0);
    $goldAmount = (float) ($pricing['gold'] ?? 0);
    $labourAmount = (float) ($pricing['labour'] ?? 0);
    $totalValue = (float) ($pricing['total'] ?? 0);
    $orderNo = (string) ($order['order_no'] ?? '-');
    $packingNo = (string) ($packing['packing_no'] ?? '-');
    $designRef = (string) (($packing['packing_date'] ?? '') !== '' ? date('d.m.y', strtotime((string) $packing['packing_date'])) : date('d.m.y'));
    $fmt = static fn(float $n, int $d = 3): string => number_format($n, $d, '.', '');
    $fmtAmt = static fn(float $n): string => number_format($n, 2, '.', '');
?>

<style>
    .ornament-toolbar { border-left: 4px solid var(--erp-red); }
    .ornament-toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .ornament-media-card { border-top: 4px solid var(--erp-gold); }
    .ornament-photo-strip { display: flex; gap: 12px; }
    .ornament-photo { min-width: 86px; }
    .ornament-photo img {
        background: #f8fafc;
        border: 1px solid #dfe5ee;
        border-radius: 10px;
        display: block;
        height: 64px;
        margin-top: 5px;
        object-fit: cover;
        width: 86px;
    }
    .ornament-empty-photo {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cfd7e4;
        border-radius: 10px;
        color: #98a2b3;
        display: flex;
        font-size: 10px;
        height: 64px;
        justify-content: center;
        margin-top: 5px;
        text-align: center;
        width: 86px;
    }
    .orn-wrap { padding: 0 !important; }
    .orn-wrap .table-wrap {
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        width: 100%;
    }
    .orn-wrap table { width: 100%; min-width: 1400px; border-collapse: collapse; table-layout: fixed; }
    .orn-wrap th, .orn-wrap td { border: 1px solid #666; padding: 6px; text-align: center; vertical-align: top; font-size: 12px; white-space: nowrap; }
    .orn-wrap th { background: #f7f7f7; font-weight: 700; }
    .orn-wrap .left { text-align: left; }
    .orn-wrap .middle { vertical-align: middle; }
    .orn-wrap .bold { font-weight: 700; }
    .orn-wrap .img-cell img { width: 100%; max-width: 170px; height: auto; display: block; margin: 0 auto; }
    .orn-wrap .empty { height: 24px; }
    .orn-wrap col.sr { width: 60px; } .orn-wrap col.des { width: 90px; } .orn-wrap col.ref { width: 190px; } .orn-wrap col.gr { width: 80px; }
    .orn-wrap col.std { width: 120px; } .orn-wrap col.pcs { width: 65px; } .orn-wrap col.wt { width: 80px; } .orn-wrap col.rate { width: 85px; }
    .orn-wrap col.amt { width: 95px; } .orn-wrap col.net { width: 90px; } .orn-wrap col.pure { width: 90px; } .orn-wrap col.gold { width: 115px; }
    .orn-wrap col.lab { width: 110px; } .orn-wrap col.total { width: 130px; }
    .ornament-scroll-note { color: #667085; display: none; font-size: 11px; padding: 10px 14px; }
    @media (max-width: 991.98px) {
        .ornament-toolbar-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
        .ornament-toolbar-actions .btn { align-items: center; display: inline-flex; justify-content: center; white-space: normal; }
        .ornament-scroll-note { align-items: center; display: flex; gap: 6px; }
    }
    @media (max-width: 575.98px) {
        .ornament-toolbar-actions { grid-template-columns: 1fr; }
        .ornament-media-card .card-body { padding: 15px; }
        .ornament-photo-strip { justify-content: space-between; width: 100%; }
        .ornament-photo { flex: 1 1 0; min-width: 0; }
        .ornament-photo img,
        .ornament-empty-photo { height: 82px; width: 100%; }
    }
</style>

<div class="erp-page-toolbar ornament-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Ready jewellery</span>
        <h4 class="mb-1">Ornament Details</h4>
        <p class="mb-0">Finished jewellery, studded components and packing values.</p>
    </div>
    <div class="ornament-toolbar-actions">
        <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>" class="btn btn-outline-primary"><i class="fe fe-eye me-1"></i>Order Details</a>
        <a href="<?= site_url('admin/orders/' . (int) $order['id'] . '/packing-list/generate?print=1&download=1') ?>" class="btn btn-outline-success"><i class="fe fe-download me-1"></i>Download Packing List</a>
        <a href="<?= site_url('admin/orders/' . (int) $order['id'] . '/delivery-challan?download=1') ?>" target="_blank" class="btn btn-outline-dark"><i class="fe fe-file-text me-1"></i>Download Delivery Challan</a>
    </div>
</div>

<div class="card ornament-media-card mb-3">
    <div class="card-body">
        <form action="<?= site_url('admin/orders/' . (int) $order['id'] . '/finish-photo') ?>" method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-lg-5 col-md-7">
                <label class="form-label">Update Finish Photo</label>
                <input type="file" name="finish_photo" class="form-control" accept="image/*" required>
            </div>
            <div class="col-lg-2 col-md-5">
                <button type="submit" class="btn btn-primary w-100"><i class="fe fe-upload me-1"></i>Upload</button>
            </div>
            <div class="col-lg-2 col-md-4">
                <div class="small text-muted">Packing No</div>
                <div class="fw-semibold"><?= esc($packingNo) ?></div>
            </div>
            <div class="col-lg-3 col-md-8">
                <div class="ornament-photo-strip">
                <div class="ornament-photo">
                    <div class="small text-muted">Order Photo</div>
                    <?php if (($orderPhoto ?? '') !== ''): ?>
                        <img src="<?= base_url((string) $orderPhoto) ?>" alt="Order Photo">
                    <?php else: ?>
                        <div class="ornament-empty-photo">No photo</div>
                    <?php endif; ?>
                </div>
                <div class="ornament-photo">
                    <div class="small text-muted">Finish Photo</div>
                    <?php if (($finishPhoto ?? '') !== ''): ?>
                        <img src="<?= base_url((string) $finishPhoto) ?>" alt="Finish Photo">
                    <?php else: ?>
                        <div class="ornament-empty-photo">No finish photo</div>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body orn-wrap">
        <div class="ornament-scroll-note"><i class="fe fe-move"></i>Swipe horizontally to view the complete packing table.</div>
        <div class="table-wrap">
        <table>
            <colgroup>
                <col class="sr"><col class="des"><col class="ref"><col class="gr">
                <col class="std"><col class="pcs"><col class="wt"><col class="rate"><col class="amt">
                <col class="net"><col class="pure"><col class="gold"><col class="lab"><col class="total">
            </colgroup>
            <tr>
                <th rowspan="2">sr.no</th>
                <th rowspan="2">degine</th>
                <th rowspan="2">ref</th>
                <th rowspan="2">GR WT</th>
                <th colspan="5">STUDDED DIA.,CS.,EXTRA DETAILS</th>
                <th rowspan="2">NET WT.</th>
                <th rowspan="2">PURE WT.</th>
                <th rowspan="2">GOLD<br>AMM</th>
                <th rowspan="2">LABOUR<br>CHGS.</th>
                <th rowspan="2">TOTAL<br>VALUE</th>
            </tr>
            <tr>
                <th>STUDDED</th><th>PCS</th><th>WT.</th><th>RATE</th><th>AMT.</th>
            </tr>

            <?php
                $sumPcs = 0.0; $sumWt = 0.0; $sumAmt = 0.0;
            ?>
            <?php for ($i = 0; $i < $lineCount; $i++): ?>
                <?php
                    $r = $rows[$i] ?? null;
                    $studded = '';
                    $pcs = ''; $wt = ''; $rate = ''; $amt = '';
                    if ($r) {
                        $studded = trim((string) (($r['grade'] ?? '') !== '' && (string) ($r['grade'] ?? '') !== '-' ? $r['grade'] : ($r['name'] ?? '')));
                        $pcsVal = (float) ($r['pcs'] ?? 0);
                        $wtVal = (float) ($r['wt'] ?? 0);
                        $rateVal = (float) ($r['rate'] ?? 0);
                        $amtVal = (float) ($r['amt'] ?? 0);
                        $pcs = $fmt($pcsVal, 3);
                        $wt = $fmt($wtVal, 3);
                        $rate = $fmtAmt($rateVal);
                        $amt = $fmtAmt($amtVal);
                        $sumPcs += $pcsVal; $sumWt += $wtVal; $sumAmt += $amtVal;
                    }
                ?>
                <tr>
                    <td class="<?= $i === 0 ? 'middle' : 'empty' ?>"><?= $i === 0 ? '1' : '' ?></td>
                    <td class="<?= $i === 0 ? 'left' : 'empty' ?>"><?= $i === 0 ? esc($designRef) : '' ?></td>
                    <?php if ($i === 0): ?>
                        <td class="img-cell" rowspan="<?= esc((string) $lineCount) ?>">
                            <?php if (($finishPhoto ?? '') !== ''): ?>
                                <img src="<?= base_url((string) $finishPhoto) ?>" alt="Finish Photo">
                            <?php elseif (($orderPhoto ?? '') !== ''): ?>
                                <img src="<?= base_url((string) $orderPhoto) ?>" alt="Order Photo">
                            <?php else: ?>
                                <span class="text-muted">No jewellery photo</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="<?= $i === 0 ? 'middle nowrap' : 'empty' ?>"><?= $i === 0 ? esc($fmt($grWt, 3)) : '' ?></td>
                    <td class="left"><?= esc($studded) ?></td>
                    <td class="nowrap"><?= esc($pcs) ?></td>
                    <td class="nowrap"><?= esc($wt) ?></td>
                    <td class="nowrap"><?= esc($rate) ?></td>
                    <td class="nowrap"><?= esc($amt) ?></td>
                    <td class="<?= $i === 0 ? 'middle nowrap' : '' ?>"><?= $i === 0 ? esc($fmt($netWt, 3)) : '' ?></td>
                    <td class="<?= $i === 0 ? 'middle nowrap' : '' ?>"><?= $i === 0 ? esc($fmt($pureWt, 3)) : '' ?></td>
                    <td class="<?= $i === 0 ? 'middle nowrap' : '' ?>"><?= $i === 0 ? esc($fmtAmt($goldAmount)) : '' ?></td>
                    <td class="<?= $i === 0 ? 'middle nowrap' : '' ?>"><?= $i === 0 ? esc($fmtAmt($labourAmount)) : '' ?></td>
                    <td class="<?= $i === 0 ? 'middle bold nowrap' : '' ?>"><?= $i === 0 ? esc($fmtAmt($totalValue)) : '' ?></td>
                </tr>
            <?php endfor; ?>
            <tr>
                <td class="empty"></td>
                <td class="empty"></td>
                <td class="left">Packing: <?= esc($packingNo) ?></td>
                <td class="nowrap"><?= esc($fmt($grWt, 3)) ?></td>
                <td></td>
                <td class="nowrap"><?= esc($fmt($sumPcs, 3)) ?></td>
                <td class="nowrap"><?= esc($fmt($sumWt, 3)) ?></td>
                <td></td>
                <td class="nowrap"><?= esc($fmtAmt($sumAmt)) ?></td>
                <td class="nowrap"><?= esc($fmt($netWt, 3)) ?></td>
                <td class="nowrap"><?= esc($fmt($pureWt, 3)) ?></td>
                <td class="nowrap"><?= esc($fmtAmt($goldAmount)) ?></td>
                <td class="nowrap"><?= esc($fmtAmt($labourAmount)) ?></td>
                <td class="bold nowrap"><?= esc($fmtAmt($totalValue)) ?></td>
            </tr>
        </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
