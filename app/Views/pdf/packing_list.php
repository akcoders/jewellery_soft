<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing List</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        body {
            margin: 0;
            padding: 8px;
            background: #ffffff;
            font-family: "Times New Roman", serif;
        }
        table {
            width: 100%;
            table-layout: auto;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #666;
            padding: 6px;
            text-align: center;
            vertical-align: top;
            font-size: 11px;
            font-weight: normal;
            white-space: nowrap;
        }
        th {
            background: #f7f7f7;
            font-weight: bold;
        }
        .left { text-align: left; }
        .middle { vertical-align: middle; }
        .bold { font-weight: bold; }
        .img-cell {
            padding: 6px;
            vertical-align: middle;
            width: 150px;
            min-width: 150px;
            max-width: 150px;
        }
        .img-cell img {
            width: 150px;
            max-width: 150px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .nowrap { white-space: nowrap; }
        .empty { height: 24px; }

        col.ref   { width: 150px; }
    </style>
</head>
<body>
<?php
    $orderNo = (string) (($order['order_no'] ?? '') !== '' ? $order['order_no'] : '-');
    $packingNo = (string) ($packing['packing_no'] ?? '-');
    $designRef = (string) (($packing['packing_date'] ?? '') !== '' ? date('d.m.y', strtotime((string) $packing['packing_date'])) : date('d.m.y'));

    $grWtFromItems = 0.0;
    foreach (($items ?? []) as $i) {
        $grWtFromItems += (float) ($i['gross_wt'] ?? 0);
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

    $rows = array_values((array) ($detailRows ?? []));
    $lineCount = max(8, count($rows));
    $photoUrl = '';
    if (($photo ?? '') !== '') {
        $photoUrl = base_url((string) $photo);
    }

    $sumPcs = 0.0;
    $sumWt = 0.0;
    $sumAmt = 0.0;

    $fmt = static function (float $n, int $d = 3): string {
        return number_format($n, $d, '.', '');
    };
    $fmtAmt = static function (float $n): string {
        return number_format($n, 2, '.', '');
    };
?>

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
        <th>STUDDED</th>
        <th>PCS</th>
        <th>WT.</th>
        <th>RATE</th>
        <th>AMT.</th>
    </tr>

    <?php for ($i = 0; $i < $lineCount; $i++): ?>
        <?php
            $r = $rows[$i] ?? null;
            $studded = '';
            $pcs = '';
            $wt = '';
            $rate = '';
            $amt = '';
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
                $sumPcs += $pcsVal;
                $sumWt += $wtVal;
                $sumAmt += $amtVal;
            }
        ?>
        <tr>
            <td class="<?= $i === 0 ? 'middle' : 'empty' ?>"><?= $i === 0 ? '1' : '' ?></td>
            <td class="<?= $i === 0 ? 'left' : 'empty' ?>"><?= $i === 0 ? esc($designRef) : '' ?></td>

            <?php if ($i === 0): ?>
                <td class="img-cell" rowspan="<?= esc((string) $lineCount) ?>">
                    <?php if ($photoUrl !== ''): ?>
                        <img src="<?= esc($photoUrl) ?>" alt="Jewellery Reference Image">
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
        <td class="left">
            Order: <?= esc($orderNo) ?><br>
            Packing: <?= esc($packingNo) ?>
        </td>
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

</body>
</html>
