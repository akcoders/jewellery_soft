<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/** Reads historical size breakdowns as evidence, never as new stock movements. */
class HistoricalDiamondSizeWorkbook
{
    public function parse(string $path): array
    {
        $book = IOFactory::load($path);
        $blocks = [];
        foreach ($book->getAllSheets() as $sheet) {
            $block = null;
            for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                $serial = $sheet->getCell('B' . $row)->getValue();
                $name = trim((string) $sheet->getCell('C' . $row)->getFormattedValue());
                if (is_numeric($serial) && $name !== '') {
                    if ($block !== null) {
                        throw new RuntimeException('Missing block total: ' . $block['key']);
                    }
                    $block = [
                        'key' => $sheet->getTitle() . ':' . $row,
                        'sheet' => $sheet->getTitle(), 'row' => $row,
                        'serial' => (string) $serial, 'design_name' => $name,
                        'header_date' => (string) $sheet->getCell('H1')->getFormattedValue(),
                        'lines' => [],
                    ];
                }
                if ($block === null) {
                    continue;
                }
                if (strtoupper($name) === 'TOTAL') {
                    $block['pcs'] = round(array_sum(array_column($block['lines'], 'pcs')), 3);
                    $block['weight_cts'] = round(array_sum(array_column($block['lines'], 'weight_cts')), 3);
                    foreach (['G' => 'pcs', 'H' => 'weight_cts'] as $column => $field) {
                        $total = $sheet->getCell($column . $row)->getCalculatedValue();
                        if (! is_numeric($total) || abs((float) $total - $block[$field]) > 0.0005) {
                            throw new RuntimeException('Size rows do not reconcile to total: ' . $block['key']);
                        }
                    }
                    $blocks[] = $block;
                    $block = null;
                    continue;
                }
                $pcs = $sheet->getCell('G' . $row)->getCalculatedValue();
                $cts = $sheet->getCell('H' . $row)->getCalculatedValue();
                if ((! is_numeric($pcs) || (float) $pcs === 0.0) && (! is_numeric($cts) || (float) $cts === 0.0)) {
                    continue;
                }
                if (! is_numeric($pcs) || ! is_numeric($cts)) {
                    throw new RuntimeException('Invalid PCS/CTS at ' . $sheet->getTitle() . ':' . $row);
                }
                $quality = trim((string) $sheet->getCell('D' . $row)->getFormattedValue());
                $shade = trim((string) $sheet->getCell('E' . $row)->getFormattedValue());
                $size = trim((string) $sheet->getCell('F' . $row)->getFormattedValue());
                $raw = [];
                foreach (range('B', 'I') as $column) {
                    $raw[$column] = $sheet->getCell($column . $row)->getValue();
                }
                $block['lines'][] = [
                    'source_row' => $row, 'quality' => $quality, 'shade' => $shade,
                    // Keep punctuation and leading zeroes exactly as supplied. No guessed mm conversion.
                    'size_label' => $size, 'shape_name' => $this->explicitShape($quality . ' ' . $shade),
                    'pcs' => round((float) $pcs, 3), 'weight_cts' => round((float) $cts, 3),
                    'raw_cells' => $raw,
                    'notes' => $pcs < 0 || $cts < 0 ? 'Return adjustment; size allocation is not known unless recorded on this row.' : null,
                ];
            }
            if ($block !== null) {
                throw new RuntimeException('Unclosed block: ' . $block['key']);
            }
        }
        $book->disconnectWorksheets();
        return $blocks;
    }

    /** Broad product families prevent a same-weight natural/lab/Polki mix-up. */
    public function family(string $label): string
    {
        $label = strtoupper($label);
        if (preg_match('/CVD|LAB[ -]?GROWN/', $label)) {
            return 'LAB_GROWN';
        }
        foreach (['POLKI', 'BUG', 'MQ', 'PRS', 'PAN', 'RC'] as $family) {
            if (preg_match('/\b' . $family . '(?:G)?\b/', $label)) {
                return $family;
            }
        }
        if (preg_match('/VVS|\bVS\b/', $label)) {
            return 'NATURAL_VVS_VS';
        }
        if (preg_match('/\bSI\b|\bIJ\b/', $label)) {
            return 'NATURAL_SI';
        }
        return 'UNKNOWN';
    }

    public function signature(array $lines, bool $source = false): array
    {
        $result = [];
        $unknown = [];
        foreach ($lines as $line) {
            if (! $source && ($line['type'] ?? '') !== 'diamond') {
                continue;
            }
            $family = $this->family($source ? $line['quality'] . ' ' . $line['shade'] : $line['name']);
            if ($family === 'UNKNOWN') {
                $unknown[] = $line;
                continue;
            }
            $result[$family]['pcs'] = round(($result[$family]['pcs'] ?? 0) + (float) $line['pcs'], 3);
            $result[$family]['weight_cts'] = round(($result[$family]['weight_cts'] ?? 0) + (float) $line['weight_cts'], 3);
        }
        // An unlabeled return can be assigned to a product family only if the entire
        // block contains one family. It still never gets assigned to an invented size.
        foreach ($unknown as $line) {
            $family = count($result) === 1 && $line['pcs'] <= 0 && $line['weight_cts'] <= 0 ? array_key_first($result) : 'UNKNOWN';
            $result[$family]['pcs'] = round(($result[$family]['pcs'] ?? 0) + (float) $line['pcs'], 3);
            $result[$family]['weight_cts'] = round(($result[$family]['weight_cts'] ?? 0) + (float) $line['weight_cts'], 3);
        }
        ksort($result);
        return $result;
    }

    /** Unique full PCS/CTS and product-family equality; never guess a near match. */
    public function match(array $blocks, array $readyItems): array
    {
        $ready = [];
        foreach ($readyItems as $item) {
            $sheetCode = preg_replace('/[^A-Z0-9]+/', '-', strtoupper($item['source_sheet']));
            $orderNo = substr(sprintf('PL26-%s-G%02d-R%d', $sheetCode, (int) substr($item['ready_group'], -3), $item['source_row']), 0, 40);
            $ready[$orderNo] = [
                'order_no' => $orderNo, 'sheet' => $item['source_sheet'], 'row' => $item['source_row'],
                'design_name' => $item['design_name'], 'karigar_name' => $item['karigar_name'],
                'pcs' => $item['diamond_pcs'], 'weight_cts' => $item['diamond_weight_cts'],
                'signature' => $this->signature($item['components']),
                'color_grades' => $this->colorGrades(array_column(array_filter($item['components'], static fn ($line) => $line['type'] === 'diamond'), 'name')),
            ];
        }
        $uses = [];
        foreach ($blocks as &$block) {
            $block['signature'] = $this->signature($block['lines'], true);
            $block['candidates'] = [];
            $block['total_matches'] = [];
            $block['partial_candidates'] = [];
            foreach ($ready as $orderNo => $item) {
                if (abs($item['pcs'] - $block['pcs']) > 0.0005 || abs($item['weight_cts'] - $block['weight_cts']) > 0.0005) {
                    // A whole source block may cover just one product family in a
                    // finished order (e.g. SI/IJ, while Polki has no size record).
                    $subset = $block['signature'] !== [] && ! isset($block['signature']['UNKNOWN']) && count($block['signature']) < count($item['signature']);
                    foreach ($block['signature'] as $family => $values) {
                        if (($item['signature'][$family] ?? null) != $values) {
                            $subset = false;
                        }
                    }
                    if ($subset) {
                        $block['partial_candidates'][] = $orderNo;
                    }
                    continue;
                }
                $block['total_matches'][] = $orderNo;
                if ($item['signature'] == $block['signature'] && ! isset($block['signature']['UNKNOWN'])) {
                    $block['candidates'][] = $orderNo;
                    $uses[$orderNo][] = $block['key'];
                }
            }
        }
        unset($block);
        foreach ($blocks as &$block) {
            $candidate = count($block['candidates']) === 1 ? $block['candidates'][0] : null;
            $block['status'] = $candidate !== null && count($uses[$candidate]) === 1 ? 'matched' : ($block['total_matches'] ? 'review' : 'unmatched');
            $block['target'] = $block['status'] === 'matched' ? $ready[$candidate] : null;
            $block['coverage'] = 'full';
            $block['reason'] = match ($block['status']) {
                'matched' => 'Unique PCS, carats and product-family totals reconcile in both directions. Source labels retained separately.',
                'review' => 'Duplicate candidates or product-family mismatch; no automatic order link.',
                default => 'No completed order with identical diamond PCS and carats; no automatic order link.',
            };
        }
        unset($block);
        // Partial matches are also unique on both sides and cannot displace a full match.
        $partialUses = [];
        foreach ($blocks as $block) {
            if ($block['status'] === 'unmatched') {
                foreach ($block['partial_candidates'] as $orderNo) {
                    $partialUses[$orderNo][] = $block['key'];
                }
            }
        }
        foreach ($blocks as &$block) {
            if ($block['status'] === 'unmatched' && count($block['partial_candidates']) === 1) {
                $candidate = $block['partial_candidates'][0];
                if (! isset($uses[$candidate]) && count($partialUses[$candidate]) === 1) {
                    $block['status'] = 'matched';
                    $block['coverage'] = 'partial';
                    $block['target'] = $ready[$candidate];
                    $block['reason'] = 'Every source product-family PCS/CTS matches exactly; additional finished-order diamonds have no size evidence in this block.';
                }
            }
            if ($block['target']) {
                $sourceColors = $this->colorGrades(array_map(static fn ($line) => $line['quality'] . ' ' . $line['shade'], $block['lines']));
                $targetColors = $block['target']['color_grades'];
                if ($sourceColors && $targetColors && ! array_intersect($sourceColors, $targetColors)) {
                    $block['status'] = 'review';
                    $block['reason'] = 'PCS/CTS match, but color grades differ (' . implode('/', $sourceColors) . ' versus ' . implode('/', $targetColors) . '). Confirm before linking.';
                    $block['target'] = null;
                }
            }
        }
        unset($block);
        return ['blocks' => $blocks, 'ready_orders' => array_values($ready)];
    }

    private function colorGrades(array $labels): array
    {
        $grades = [];
        foreach ($labels as $label) {
            preg_match_all('/\b(?:EF|GH|IJ)\b/', strtoupper($label), $matches);
            array_push($grades, ...$matches[0]);
        }
        $grades = array_values(array_unique($grades));
        sort($grades);
        return $grades;
    }

    private function explicitShape(string $label): ?string
    {
        $label = strtoupper($label);
        foreach (['POLKI' => 'Polki', 'BUGG?' => 'Baguette', 'MQ' => 'Marquise', 'PRS' => 'Princess', 'RC' => 'Rose Cut', 'EMD' => 'Emerald', 'RD' => 'Round'] as $pattern => $shape) {
            if (preg_match('/\b' . $pattern . '\b/', $label)) {
                return $shape;
            }
        }
        return null;
    }
}
