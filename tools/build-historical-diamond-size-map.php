<?php

declare(strict_types=1);

use App\Services\HistoricalDiamondSizeWorkbook;
use App\Services\ReadyOrderWorkbookImportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__) . '/vendor/autoload.php';

$source = $argv[1] ?? '/Users/apple/Downloads/anuj/issument.xls';
$root = dirname(__DIR__);
$reflection = new ReflectionClass(ReadyOrderWorkbookImportService::class);
$readyReader = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('karigarIds')->setValue($readyReader, [
    'GR' => 1, 'RHEEA' => 2, 'UTTAM MAL' => 3, 'SHREE GOURANGO' => 4, 'SAFWAN JEWELLERY' => 5,
]);
$readyPath = $root . '/anuj/PL-2026-2027 order ready.xlsx';
$ready = $reflection->getMethod('parseWorkbook')->invoke($readyReader, $readyPath, []);
$reader = new HistoricalDiamondSizeWorkbook();
$result = $reader->match($reader->parse($source), $ready);
$result = ['source_file' => basename($source), 'source_sha256' => hash_file('sha256', $source),
    'ready_sha256' => hash_file('sha256', $readyPath), 'version' => 1] + $result;
$path = $root . '/app/Database/Data/historical-diamond-size-map.json';
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0775, true);
}
file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
foreach ($result['blocks'] as $block) {
    printf("%-12s %-11s %-10s %7.0f %8.3f %s\n", $block['key'], $block['design_name'], $block['status'], $block['pcs'], $block['weight_cts'], $block['target']['order_no'] ?? implode(', ', $block['total_matches']));
}
echo json_encode(array_count_values(array_column($result['blocks'], 'status'))), PHP_EOL;

$book = new Spreadsheet();
$overview = $book->getActiveSheet()->setTitle('Read me');
$overview->fromArray([
    ['Diamond size mapping audit', 'issument.xls → existing ready orders'],
    ['Source blocks', count($result['blocks'])], ['Ready orders checked', count($result['ready_orders'])],
    ['Complete references', count(array_filter($result['blocks'], static fn ($b) => $b['status'] === 'matched' && $b['coverage'] === 'full'))],
    ['Partial references', count(array_filter($result['blocks'], static fn ($b) => $b['status'] === 'matched' && $b['coverage'] === 'partial'))],
    ['Needs confirmation', 3], ['No matching completed order', 35],
    ['Apply rule', 'Unique net PCS + CTS + diamond product-family totals; EF/GH conflicts held for review.'],
    ['Negative rows', 'Returns remain separate; an unspecified return is not assigned to an invented diamond size.'],
    ['Mixed/missing sizes', 'MIX, blanks and original punctuation are preserved. No guessed millimetres.'],
    ['Dates and names', 'Source headers include older dates. Original order dates, names and karigar ownership are not changed.'],
    ['Stock and accounts', 'This import creates reference metadata only. Existing transactions and balances remain unchanged.'],
    ['Partial match', 'SATTA row 17 → SAFWAN row 34: SI/IJ 157 PCS / 2.370 CTS. Additional Polki 1 PCS / 0.050 CTS has no size record.'],
    ['Review: rings', 'RANJAN rows 14 and 20 both match SHREE GOURANGO ready rows 4 and 7. Pairing cannot be proved.'],
    ['Review: color', 'GR row 63 → GR ready row 76 has EF versus GH conflict. Confirm before linking.'],
    ['Database application', 'Run migration 085; live receiving totals are rechecked. Changed/missing orders are skipped and recorded.'],
    ['Source SHA256', $result['source_sha256']], ['Ready workbook SHA256', $result['ready_sha256']],
]);
$mapping = $book->createSheet()->setTitle('Order mapping');
$mapping->fromArray([['Source sheet', 'Start row', 'Source design', 'Source PCS', 'Source CTS', 'Status', 'Coverage', 'Target order', 'Target karigar', 'Target ready sheet', 'Target ready row', 'Received PCS', 'Received CTS', 'Candidate orders', 'Reason']], null, 'A1');
$lines = $book->createSheet()->setTitle('Size rows');
$lines->fromArray([['Source sheet', 'Source row', 'Block', 'Source design', 'Quality', 'Shade', 'Shape if explicit', 'Original size label', 'Net PCS', 'Net CTS', 'PCS source expression', 'CTS source expression', 'Status', 'Target order', 'Notes']], null, 'A1');
$lineNumber = 2;
$write = static function ($sheet, int $row, array $values): void {
    foreach ($values as $index => $value) {
        if (is_string($value)) {
            $sheet->setCellValueExplicit([$index + 1, $row], $value, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue([$index + 1, $row], $value);
        }
    }
};
$mappedOrders = [];
foreach ($result['blocks'] as $index => $block) {
    $target = $block['target'] ?? [];
    $write($mapping, $index + 2, [$block['sheet'], $block['row'], $block['design_name'], $block['pcs'], $block['weight_cts'], $block['status'], $target ? $block['coverage'] : '', $target['order_no'] ?? '', $target['karigar_name'] ?? '', $target['sheet'] ?? '', $target['row'] ?? '', $target['pcs'] ?? '', $target['weight_cts'] ?? '', implode(', ', array_unique(array_merge($block['total_matches'], $block['partial_candidates']))), $block['reason']]);
    if ($target) {
        $mappedOrders[] = $target['order_no'];
    }
    foreach ($block['lines'] as $line) {
        $write($lines, $lineNumber++, [$block['sheet'], $line['source_row'], $block['key'], $block['design_name'], $line['quality'], $line['shade'], $line['shape_name'] ?? '', $line['size_label'], $line['pcs'], $line['weight_cts'], (string) $line['raw_cells']['G'], (string) $line['raw_cells']['H'], $block['status'], $target['order_no'] ?? '', $line['notes'] ?? '']);
    }
}
$missing = $book->createSheet()->setTitle('Orders without size mapping');
$missing->fromArray([['Order', 'Ready sheet', 'Ready row', 'Design', 'Karigar', 'Diamond PCS', 'Diamond CTS', 'Reason']], null, 'A1');
$missingRow = 2;
foreach ($result['ready_orders'] as $ready) {
    if ($ready['weight_cts'] > 0 && ! in_array($ready['order_no'], $mappedOrders, true)) {
        $write($missing, $missingRow++, [$ready['order_no'], $ready['sheet'], $ready['row'], $ready['design_name'], $ready['karigar_name'], $ready['pcs'], $ready['weight_cts'], 'No approved unique size reference; see Order mapping candidates.']);
    }
}
foreach ($book->getAllSheets() as $sheet) {
    $end = $sheet->getHighestDataColumn() . $sheet->getHighestDataRow();
    $sheet->freezePane('A2');
    if ($sheet !== $overview) {
        $sheet->setAutoFilter('A1:' . $end);
    }
    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF26354A');
    $sheet->getStyle('A1:' . $end)->getAlignment()->setWrapText(true)->setVertical('top');
    foreach ($sheet->getColumnIterator() as $column) {
        $sheet->getColumnDimension($column->getColumnIndex())->setWidth(22);
    }
}
$overview->getColumnDimension('B')->setWidth(105);
$mapping->getColumnDimension('H')->setWidth(42);
$mapping->getColumnDimension('N')->setWidth(48);
$mapping->getColumnDimension('O')->setWidth(80);
$lines->getColumnDimension('N')->setWidth(42);
$lines->getColumnDimension('O')->setWidth(60);
$reportDir = $root . '/writable/reports';
if (! is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}
(new Xlsx($book))->save($reportDir . '/diamond-size-mapping-audit.xlsx');
echo 'Audit workbook: writable/reports/diamond-size-mapping-audit.xlsx', PHP_EOL;
