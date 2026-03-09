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
    .orn-wrap .table-wrap { width: 100%; overflow-x: auto; }
    .orn-wrap table { width: 100%; min-width: 1580px; border-collapse: collapse; table-layout: fixed; }
    .orn-wrap th, .orn-wrap td { border: 1px solid #666; padding: 6px; text-align: center; vertical-align: top; font-size: 12px; white-space: nowrap; }
    .orn-wrap th { background: #f7f7f7; font-weight: 700; }
    .orn-wrap .left { text-align: left; }
    .orn-wrap .middle { vertical-align: middle; }
    .orn-wrap .bold { font-weight: 700; }
    .orn-wrap .img-cell img { width: 100%; max-width: 170px; height: auto; display: block; margin: 0 auto; }
    .orn-wrap .empty { height: 24px; }
    .orn-wrap col.sr { width: 60px; } .orn-wrap col.des { width: 100px; } .orn-wrap col.ref { width: 220px; } .orn-wrap col.gr { width: 90px; }
    .orn-wrap col.std { width: 140px; } .orn-wrap col.pcs { width: 70px; } .orn-wrap col.wt { width: 90px; } .orn-wrap col.rate { width: 90px; }
    .orn-wrap col.amt { width: 110px; } .orn-wrap col.net { width: 100px; } .orn-wrap col.pure { width: 100px; } .orn-wrap col.gold { width: 130px; }
    .orn-wrap col.lab { width: 130px; } .orn-wrap col.total { width: 150px; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h4 class="mb-0">Ornament Details - <?= esc($orderNo) ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>" class="btn btn-outline-primary"><i class="fe fe-eye me-1"></i>Order Details</a>
        <a href="<?= site_url('admin/orders/' . (int) $order['id'] . '/packing-list/generate?print=1&download=1') ?>" class="btn btn-outline-success"><i class="fe fe-download me-1"></i>Download Packing List</a>
        <a href="<?= site_url('admin/orders/' . (int) $order['id'] . '/delivery-challan?download=1') ?>" target="_blank" class="btn btn-outline-dark"><i class="fe fe-file-text me-1"></i>Download Delivery Challan</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form action="<?= site_url('admin/orders/' . (int) $order['id'] . '/finish-photo') ?>" method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-5">
                <label class="form-label">Update Finish Photo</label>
                <input type="file" name="finish_photo" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fe fe-upload me-1"></i>Upload</button>
            </div>
            <div class="col-md-2">
                <div class="small text-muted">Packing No</div>
                <div class="fw-semibold"><?= esc($packingNo) ?></div>
            </div>
            <div class="col-md-3 d-flex gap-3">
                <div>
                    <div class="small text-muted">Order Photo</div>
                    <?php if (($orderPhoto ?? '') !== ''): ?>
                        <img src="<?= base_url((string) $orderPhoto) ?>" alt="Order Photo" style="width:80px;height:60px;object-fit:cover;border:1px solid #ddd;">
                    <?php else: ?>
                        <div class="small text-muted">No photo</div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="small text-muted">Finish Photo</div>
                    <?php if (($finishPhoto ?? '') !== ''): ?>
                        <img src="<?= base_url((string) $finishPhoto) ?>" alt="Finish Photo" style="width:80px;height:60px;object-fit:cover;border:1px solid #ddd;">
                    <?php else: ?>
                        <div class="small text-muted">No finish photo</div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body orn-wrap">
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
                                <?= esc($orderNo) ?>
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
                <td class="left">Order: <?= esc($orderNo) ?><br>Packing: <?= esc($packingNo) ?></td>
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
