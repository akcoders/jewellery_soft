<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProductionDataImportService
{
    private const REQUIRED_FILES = [
        'BABU GOLD -26-27.xlsx',
        'DIA.STOCK LEDGER (26-27).xlsx',
        'issument.xls',
        'PL-2026-2027 order ready.xlsx',
    ];

    private const IMPORT_TABLES = [
        'production_ready_items',
        'production_diamond_issue_lines',
        'production_diamond_movements',
        'production_gold_movements',
        'production_purchase_documents',
        'production_source_rows',
        'production_import_batches',
    ];

    private BaseConnection $db;

    /** @var array<string,int> */
    private array $karigarIds = [];

    /** @var array<string,int> */
    private array $vendorIds = [];

    /** @var array<string,int> */
    private array $orderIds = [];

    /** @var array<string,int> */
    private array $diamondItemIds = [];

    /** @var array<string,int> */
    private array $goldPurityIds = [];

    /** @var array<string,int> */
    private array $goldItemIds = [];

    private int $adminId = 0;
    private int $locationId = 0;
    private int $warehouseId = 0;
    private int $binId = 0;
    private string $adminPasswordHash = '';

    /** @var array<string,int> */
    private array $accountIds = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Replace operational data with the supplied production archive.
     *
     * @return array<string,mixed>
     */
    public function import(string $zipPath, string $sourceName, int $adminId, string $adminPassword): array
    {
        if (! is_file($zipPath) || ! is_readable($zipPath)) {
            throw new RuntimeException('The uploaded ZIP file cannot be read.');
        }
        if (! $this->db->tableExists('production_import_batches')) {
            throw new RuntimeException('Run the pending database migration before importing production data.');
        }

        $this->adminId = $this->resolveAdminId($adminId);
        if (strlen($adminPassword) < 12) {
            throw new RuntimeException('The new administrator password must contain at least 12 characters.');
        }
        $this->adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $archiveHash = hash_file('sha256', $zipPath);
        if (! is_string($archiveHash) || $archiveHash === '') {
            throw new RuntimeException('Could not calculate the ZIP checksum.');
        }

        $importKey = date('Ymd-His') . '-' . substr($archiveHash, 0, 10);
        $importRoot = WRITEPATH . 'uploads/production-imports/' . $importKey;
        $sourceRoot = $importRoot . '/source';
        $this->ensureDirectory($sourceRoot);

        try {
            $files = $this->extractValidatedArchive($zipPath, $sourceRoot);
            $paths = $this->resolveRequiredPaths($files);
            $parsed = $this->parseSources($paths, $files, $sourceRoot, $importRoot);
            $result = $this->persistImport($sourceName, $archiveHash, $parsed);
            $result['stored_path'] = str_replace(WRITEPATH, 'writable/', $importRoot);

            return $result;
        } catch (Throwable $e) {
            $this->removeDirectory($importRoot);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function parseSources(array $paths, array $files, string $sourceRoot, string $importRoot): array
    {
        $parsed = [
            'source_rows' => [],
            'gold_movements' => [],
            'diamond_movements' => [],
            'diamond_issue_lines' => [],
            'ready_items' => [],
            'documents' => [],
            'gold_closing_24k' => 0.0,
            'diamond_closing' => [],
        ];

        $this->parseGoldWorkbook($paths['BABU GOLD -26-27.xlsx'], $parsed);
        $this->parseDiamondLedger($paths['DIA.STOCK LEDGER (26-27).xlsx'], $parsed);
        $this->parseDiamondIssuements($paths['issument.xls'], $parsed);
        $readyImages = $this->extractReadyWorkbookImages($paths['PL-2026-2027 order ready.xlsx'], $importRoot);
        $this->parseReadyWorkbook($paths['PL-2026-2027 order ready.xlsx'], $parsed, $readyImages);
        $this->parseDocuments($files, $sourceRoot, $importRoot, $parsed);

        return $parsed;
    }

    private function parseGoldWorkbook(string $path, array &$parsed): void
    {
        $spreadsheet = IOFactory::load($path);
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->collectSourceRows($sheet, basename($path), 'gold_ledger_raw', $parsed['source_rows']);

            if (strcasecmp($sheet->getTitle(), 'TOTAL') === 0) {
                $receivedTotal = 0.0;
                $issuedTotal = 0.0;
                for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
                    $date = $this->dateValue($this->cell($sheet, 3, $row));
                    if ($date === null) {
                        continue;
                    }

                    $received = $this->number($this->cell($sheet, 4, $row));
                    $issued = $this->number($this->cell($sheet, 7, $row));
                    if ($received !== 0.0) {
                        $parsed['gold_movements'][] = [
                            'movement_type' => 'stock_in',
                            'movement_date' => $date,
                            'party_name' => $this->text($this->cell($sheet, 2, $row)) ?: 'Opening / supplier',
                            'reference_no' => $this->text($this->cell($sheet, 5, $row)),
                            'description' => '24K gold received in source stock ledger',
                            'purity_label' => '24K',
                            'weight_24k_gm' => $received,
                            'received_weight_gm' => $received,
                            'source_sheet' => $sheet->getTitle(),
                            'source_row' => $row,
                        ];
                        $receivedTotal += $received;
                    }
                    if ($issued !== 0.0) {
                        $issuedTotal += $issued;
                    }
                }
                $parsed['gold_closing_24k'] = round($receivedTotal - $issuedTotal, 3);
                continue;
            }

            $karigarName = $this->canonicalKarigar($sheet->getTitle());
            for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
                $reference = $this->text($this->cell($sheet, 5, $row));
                $description = $this->text($this->cell($sheet, 6, $row));
                $party = $this->text($this->cell($sheet, 4, $row)) ?: $karigarName;
                $issueWeight = $this->number($this->cell($sheet, 2, $row));
                $issueDate = $this->dateValue($this->cell($sheet, 3, $row));
                if ($issueWeight !== 0.0 && $issueDate !== null) {
                    $parsed['gold_movements'][] = [
                        'movement_type' => 'issue',
                        'movement_date' => $issueDate,
                        'party_name' => $this->canonicalKarigar($party),
                        'reference_no' => $reference,
                        'description' => $description,
                        'purity_label' => '24K',
                        'weight_24k_gm' => $issueWeight,
                        'received_weight_gm' => 0.0,
                        'source_sheet' => $sheet->getTitle(),
                        'source_row' => $row,
                    ];
                }

                $receivedWeight = $this->number($this->cell($sheet, 9, $row));
                $equivalent24k = $this->number($this->cell($sheet, 8, $row));
                $receiveDate = $this->dateValue($this->cell($sheet, 7, $row));
                if (($receivedWeight !== 0.0 || $equivalent24k !== 0.0) && $receiveDate !== null) {
                    $parsed['gold_movements'][] = [
                        'movement_type' => 'receive',
                        'movement_date' => $receiveDate,
                        'party_name' => $karigarName,
                        'reference_no' => $reference,
                        'description' => $description,
                        'purity_label' => $this->inferPurity($receivedWeight, $equivalent24k, $description),
                        'weight_24k_gm' => $equivalent24k,
                        'received_weight_gm' => $receivedWeight,
                        'source_sheet' => $sheet->getTitle(),
                        'source_row' => $row,
                    ];
                }
            }
        }
        $spreadsheet->disconnectWorksheets();
    }

    private function parseDiamondLedger(string $path, array &$parsed): void
    {
        $spreadsheet = IOFactory::load($path);
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->collectSourceRows($sheet, basename($path), 'diamond_stock_ledger_raw', $parsed['source_rows']);
            $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            $categories = [];
            for ($column = 5; $column <= $maxColumn; $column += 2) {
                $category = $this->text($this->cell($sheet, $column, 1));
                if ($category !== '') {
                    $categories[$column] = $category;
                }
            }

            $sheetBalances = [];
            for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
                $date = $this->dateValue($this->cell($sheet, 1, $row));
                if ($date === null) {
                    continue;
                }
                $party = $this->text($this->cell($sheet, 2, $row));
                $reference = $this->text($this->cell($sheet, 3, $row));
                $description = $this->text($this->cell($sheet, 4, $row));
                $descriptionUpper = strtoupper($description);

                foreach ($categories as $receivedColumn => $category) {
                    $received = $this->number($this->cell($sheet, $receivedColumn, $row));
                    $issued = $this->number($this->cell($sheet, $receivedColumn + 1, $row));
                    if ($received === 0.0 && $issued === 0.0) {
                        continue;
                    }

                    $movementType = str_contains($descriptionUpper, 'OPENING')
                        ? 'opening'
                        : (str_contains($descriptionUpper, 'PURCHASE') ? 'purchase' : ($issued !== 0.0 ? 'issue' : 'receive'));
                    $parsed['diamond_movements'][] = [
                        'movement_date' => $date,
                        'party_name' => $party ?: ($movementType === 'opening' ? 'Opening Balance' : ''),
                        'reference_no' => $reference,
                        'description' => $description,
                        'movement_type' => $movementType,
                        'quality_bucket' => $category,
                        'received_cts' => $received,
                        'issued_cts' => $issued,
                        'source_sheet' => $sheet->getTitle(),
                        'source_row' => $row,
                    ];
                    $sheetBalances[$category] = ($sheetBalances[$category] ?? 0.0) + $received - $issued;
                }
            }

            // Each month contains its own opening balance, so the final worksheet is the current stock.
            if ($sheetBalances !== []) {
                $parsed['diamond_closing'] = array_map(static fn($value): float => round((float) $value, 3), $sheetBalances);
            }
        }
        $spreadsheet->disconnectWorksheets();
    }

    private function parseDiamondIssuements(string $path, array &$parsed): void
    {
        $spreadsheet = IOFactory::load($path);
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->collectSourceRows($sheet, basename($path), 'diamond_issuement_raw', $parsed['source_rows']);
            $karigar = $this->canonicalKarigar($sheet->getTitle());
            $issueDate = null;
            $group = 0;
            $serial = '';
            $design = '';
            $bag = '';

            for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                $rowText = strtoupper(implode(' ', array_filter($this->rowValues($sheet, $row), static fn($value): bool => $value !== null && $value !== '')));
                if (str_contains($rowText, 'DATE')) {
                    for ($column = 1; $column <= min(12, Coordinate::columnIndexFromString($sheet->getHighestDataColumn())); $column++) {
                        $candidate = $this->dateValue($this->cell($sheet, $column, $row));
                        if ($candidate !== null) {
                            $issueDate = $candidate;
                            break;
                        }
                    }
                }
                if (str_contains($rowText, 'SR.NO.') && str_contains($rowText, 'DESIGNS')) {
                    $group++;
                    $serial = '';
                    $design = '';
                    $bag = '';
                    continue;
                }
                if (str_contains($rowText, 'TOTAL')) {
                    continue;
                }

                $pcs = $this->number($this->cell($sheet, 7, $row));
                $weight = $this->number($this->cell($sheet, 8, $row));
                if ($pcs === 0.0 && $weight === 0.0) {
                    continue;
                }

                $serialValue = $this->text($this->cell($sheet, 2, $row));
                $designValue = $this->text($this->cell($sheet, 3, $row));
                $bagValue = $this->text($this->cell($sheet, 9, $row));
                if ($serialValue !== '') {
                    $serial = $serialValue;
                }
                if ($designValue !== '') {
                    $design = $designValue;
                }
                if ($bagValue !== '') {
                    $bag = $bagValue;
                }

                $parsed['diamond_issue_lines'][] = [
                    'karigar_name' => $karigar,
                    'issue_date' => $issueDate,
                    'issue_group' => $sheet->getTitle() . '-' . str_pad((string) max(1, $group), 3, '0', STR_PAD_LEFT),
                    'design_no' => $design,
                    'quality' => $this->text($this->cell($sheet, 4, $row)),
                    'shade' => $this->text($this->cell($sheet, 5, $row)),
                    'size_label' => $this->text($this->cell($sheet, 6, $row)),
                    'pcs' => $pcs,
                    'weight_cts' => $weight,
                    'bag_label' => $bag ?: $serial,
                    'source_sheet' => $sheet->getTitle(),
                    'source_row' => $row,
                ];
            }
        }
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param array<string,list<array{key:string,start_row:int,end_row:int,path:string}>> $imageMap
     */
    private function parseReadyWorkbook(string $path, array &$parsed, array $imageMap): void
    {
        $spreadsheet = IOFactory::load($path);
        $usedImages = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->collectSourceRows($sheet, basename($path), 'ready_job_raw', $parsed['source_rows']);
            $karigar = $this->canonicalKarigar($sheet->getTitle());
            $group = 0;
            $groupDate = null;
            $highestRow = $sheet->getHighestDataRow();

            for ($row = 1; $row <= $highestRow; $row++) {
                $rowText = strtolower(implode(' ', array_filter($this->rowValues($sheet, $row), static fn($value): bool => $value !== null && $value !== '')));
                if (str_contains($rowText, 'sr.no')) {
                    $group++;
                    $groupDate = null;
                    continue;
                }
                $serialValue = $this->cell($sheet, 2, $row);
                $gross = $this->number($this->cell($sheet, 5, $row));
                if (! is_numeric($serialValue) || $gross === 0.0) {
                    continue;
                }

                $endRow = $row;
                for ($candidate = $row + 1; $candidate <= $highestRow; $candidate++) {
                    $candidateText = strtolower(implode(' ', array_filter($this->rowValues($sheet, $candidate), static fn($value): bool => $value !== null && $value !== '')));
                    if (str_contains($candidateText, 'sr.no') || str_contains($candidateText, 'total')) {
                        break;
                    }
                    if (is_numeric($this->cell($sheet, 2, $candidate)) && $this->number($this->cell($sheet, 5, $candidate)) !== 0.0) {
                        break;
                    }
                    $endRow = $candidate;
                }

                $readyDate = null;
                $purity = '';
                $statusParts = [];
                $stones = [];
                for ($detailRow = $row; $detailRow <= $endRow; $detailRow++) {
                    $dateCandidate = $this->dateValue($this->cell($sheet, 3, $detailRow));
                    if ($readyDate === null && $dateCandidate !== null) {
                        $readyDate = $dateCandidate;
                    }
                    $purityCandidate = strtoupper($this->text($this->cell($sheet, 2, $detailRow)));
                    if ($purity === '' && preg_match('/\b(?:14|18|22|24)\s*K(?:T)?\b/', $purityCandidate, $match) === 1) {
                        $purity = str_replace(' ', '', $match[0]);
                    }
                    $stoneName = $this->text($this->cell($sheet, 6, $detailRow));
                    $stonePcs = $this->number($this->cell($sheet, 7, $detailRow));
                    $stoneWeight = $this->number($this->cell($sheet, 8, $detailRow));
                    $stoneRate = $this->number($this->cell($sheet, 9, $detailRow));
                    $stoneAmount = $this->number($this->cell($sheet, 10, $detailRow));
                    if ($stoneName !== '' || $stonePcs !== 0.0 || $stoneWeight !== 0.0) {
                        $stones[] = [
                            'name' => $stoneName,
                            'pcs' => $stonePcs,
                            'weight' => $stoneWeight,
                            'rate' => $stoneRate,
                            'amount' => $stoneAmount,
                        ];
                    }
                    foreach ([16, 17] as $statusColumn) {
                        $status = $this->text($this->cell($sheet, $statusColumn, $detailRow));
                        if ($status !== '') {
                            $statusParts[$status] = true;
                        }
                    }
                }
                if ($readyDate !== null) {
                    $groupDate = $readyDate;
                } else {
                    $readyDate = $groupDate;
                }

                $design = $this->text($this->cell($sheet, 3, $row));
                if ($this->dateValue($design) !== null) {
                    $design = '';
                }
                $reference = $this->text($this->cell($sheet, 4, $row));

                $statusNote = implode(', ', array_keys($statusParts));
                $paymentDate = null;
                foreach (array_keys($statusParts) as $statusPart) {
                    $candidate = $this->dateValue($statusPart);
                    if ($candidate !== null) {
                        $paymentDate = $candidate;
                    }
                }
                $imagePath = $this->matchReadyImage(
                    $imageMap[$sheet->getTitle()] ?? [],
                    $row,
                    $endRow,
                    $usedImages
                );

                $parsed['ready_items'][] = [
                    'karigar_name' => $karigar,
                    'ready_group' => $sheet->getTitle() . '-' . str_pad((string) max(1, $group), 3, '0', STR_PAD_LEFT),
                    'ready_date' => $readyDate,
                    'serial_no' => $this->text($serialValue),
                    'design_name' => $design ?: ('Production item ' . $this->text($serialValue)),
                    'reference_no' => $reference,
                    'purity_label' => $purity,
                    'gross_weight_gm' => $gross,
                    'net_weight_gm' => $this->number($this->cell($sheet, 11, $row)),
                    'pure_weight_gm' => $this->number($this->cell($sheet, 12, $row)),
                    'gold_amount' => $this->number($this->cell($sheet, 13, $row)),
                    'labour_charges' => $this->number($this->cell($sheet, 14, $row)),
                    'total_value' => $this->number($this->cell($sheet, 15, $row)),
                    'stones_json' => json_encode($stones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'image_path' => $imagePath,
                    'status_note' => $statusNote,
                    'payment_status' => str_contains(strtoupper($statusNote), 'PAID') ? 'Paid' : 'Pending',
                    'payment_date' => $paymentDate,
                    'source_sheet' => $sheet->getTitle(),
                    'source_row' => $row,
                ];
            }
        }
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * Excel stores these product photographs in drawing XML that PhpSpreadsheet does not expose.
     * Read the OOXML relationships directly and retain every image placement with its row span.
     *
     * @return array<string,list<array{key:string,start_row:int,end_row:int,path:string}>>
     */
    private function extractReadyWorkbookImages(string $path, string $importRoot): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The ready-order workbook could not be opened for image extraction.');
        }

        $imageDir = $importRoot . '/ready-images';
        $this->ensureDirectory($imageDir);
        $sheetNames = [];
        $spreadsheet = IOFactory::load($path);
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getTitle();
        }
        $spreadsheet->disconnectWorksheets();

        $result = [];
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $sheetNumber = $sheetIndex + 1;
            $sheetRelsName = 'xl/worksheets/_rels/sheet' . $sheetNumber . '.xml.rels';
            $sheetRelsRaw = $zip->getFromName($sheetRelsName);
            if (! is_string($sheetRelsRaw)) {
                continue;
            }
            $sheetRels = @simplexml_load_string($sheetRelsRaw);
            if ($sheetRels === false) {
                continue;
            }

            $drawingTarget = '';
            foreach ($sheetRels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
                $attributes = $relationship->attributes();
                if (str_ends_with((string) ($attributes['Type'] ?? ''), '/drawing')) {
                    $drawingTarget = (string) ($attributes['Target'] ?? '');
                    break;
                }
            }
            if ($drawingTarget === '') {
                continue;
            }

            $drawingName = $this->normalizeZipPath('xl/worksheets/' . $drawingTarget);
            $drawingRaw = $zip->getFromName($drawingName);
            $drawingRelsName = dirname($drawingName) . '/_rels/' . basename($drawingName) . '.rels';
            $drawingRelsRaw = $zip->getFromName($drawingRelsName);
            if (! is_string($drawingRaw) || ! is_string($drawingRelsRaw)) {
                continue;
            }

            $relationships = [];
            $drawingRels = @simplexml_load_string($drawingRelsRaw);
            if ($drawingRels !== false) {
                foreach ($drawingRels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
                    $attributes = $relationship->attributes();
                    $relationships[(string) ($attributes['Id'] ?? '')] = (string) ($attributes['Target'] ?? '');
                }
            }

            $drawing = @simplexml_load_string($drawingRaw);
            if ($drawing === false) {
                continue;
            }
            $drawing->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $drawing->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $drawing->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $anchors = $drawing->xpath('//xdr:twoCellAnchor | //xdr:oneCellAnchor') ?: [];
            foreach ($anchors as $anchorIndex => $anchor) {
                $anchor->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
                $anchor->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $rows = $anchor->xpath('./xdr:from/xdr:row') ?: [];
                $endRows = $anchor->xpath('./xdr:to/xdr:row') ?: [];
                $blips = $anchor->xpath('.//a:blip') ?: [];
                if ($rows === [] || $blips === []) {
                    continue;
                }
                $readyRow = ((int) $rows[0]) + 1;
                $endRow = $endRows === [] ? $readyRow : ((int) $endRows[0]) + 1;
                $embedAttributes = $blips[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($embedAttributes['embed'] ?? '');
                $target = $relationships[$relationshipId] ?? '';
                if ($target === '') {
                    continue;
                }

                $mediaName = $this->normalizeZipPath(dirname($drawingName) . '/' . $target);
                $contents = $zip->getFromName($mediaName);
                if (! is_string($contents) || $contents === '') {
                    continue;
                }
                $extension = strtolower(pathinfo($mediaName, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }
                $safeSheet = trim(preg_replace('/[^a-z0-9]+/i', '-', $sheetName) ?? '', '-');
                $filename = strtolower($safeSheet ?: 'sheet') . '-r' . $readyRow . '-' . ($anchorIndex + 1) . '.' . $extension;
                $destination = $imageDir . '/' . $filename;
                if (file_put_contents($destination, $contents) === false) {
                    throw new RuntimeException('A ready-order image could not be stored.');
                }
                $result[$sheetName][] = [
                    'key' => $sheetName . ':' . ($anchorIndex + 1),
                    'start_row' => $readyRow,
                    'end_row' => max($readyRow, $endRow),
                    'path' => ltrim(str_replace('\\', '/', substr($destination, strlen(WRITEPATH))), '/'),
                ];
            }
        }
        $zip->close();

        return $result;
    }

    /**
     * Match a picture to the item row it visually covers in Excel. Pictures can start on the
     * blank separator row immediately before an item, so an exact row-key lookup loses them.
     *
     * @param list<array{key:string,start_row:int,end_row:int,path:string}> $placements
     * @param array<string,bool> $usedImages
     */
    private function matchReadyImage(array $placements, int $itemStartRow, int $itemEndRow, array &$usedImages): ?string
    {
        $matches = [];
        foreach ($placements as $placement) {
            if (isset($usedImages[$placement['key']])) {
                continue;
            }
            $startsOnItem = $placement['start_row'] === $itemStartRow;
            $startsInsideItem = $placement['start_row'] >= $itemStartRow && $placement['start_row'] <= $itemEndRow;
            $overlapsItem = $placement['end_row'] >= $itemStartRow && $placement['start_row'] <= $itemEndRow;
            if (! $overlapsItem) {
                continue;
            }
            $matches[] = [
                'placement' => $placement,
                'rank' => $startsOnItem ? 0 : ($startsInsideItem ? 1 : 2),
                'distance' => abs($placement['start_row'] - $itemStartRow),
            ];
        }
        if ($matches === []) {
            return null;
        }
        usort($matches, static fn(array $left, array $right): int => [$left['rank'], $left['distance']] <=> [$right['rank'], $right['distance']]);
        $selected = $matches[0]['placement'];
        $usedImages[$selected['key']] = true;

        return $selected['path'];
    }

    private function normalizeZipPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private function parseDocuments(array $files, string $sourceRoot, string $importRoot, array &$parsed): void
    {
        foreach ($files as $path) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($sourceRoot))), '/');
            $segments = explode('/', $relative);
            $vendorName = count($segments) >= 2 ? $segments[count($segments) - 2] : 'Unknown Vendor';
            $lowerPath = strtolower($relative);
            $category = str_contains($lowerPath, '/gold/') ? 'gold' : 'diamond_and_stone';
            $filename = basename($path);
            $documentDate = $this->dateFromFilename($filename);
            $isPaid = str_contains(strtoupper($filename), 'PAID');
            $parsed['documents'][] = [
                'category' => $category,
                'vendor_name' => $this->canonicalVendor($vendorName),
                'original_name' => $filename,
                'source_path' => $relative,
                'stored_path' => ltrim(str_replace('\\', '/', substr($path, strlen(WRITEPATH))), '/'),
                'document_date' => $documentDate,
                'invoice_no' => pathinfo($filename, PATHINFO_FILENAME),
                'invoice_amount' => null,
                'payment_status' => $isPaid ? 'Paid' : 'Unverified',
                'paid_amount' => null,
                'payment_date' => $isPaid ? $documentDate : null,
                'account_payment_id' => null,
                'reconciliation_status' => $isPaid ? 'Paid in source; amount not supplied' : 'Source document recorded; amount not supplied',
                'file_size' => filesize($path) ?: 0,
                'sha256' => hash_file('sha256', $path) ?: '',
                'metadata_json' => json_encode([
                    'archive_folder' => dirname($relative),
                    'import_folder' => basename($importRoot),
                ], JSON_UNESCAPED_SLASHES),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function persistImport(string $sourceName, string $archiveHash, array $parsed): array
    {
        $this->db->transBegin();
        try {
            $this->purgeOperationalData();
            $this->seedInventoryFoundation();

            $now = date('Y-m-d H:i:s');
            $this->db->table('production_import_batches')->insert([
                'source_name' => $sourceName,
                'source_sha256' => $archiveHash,
                'imported_by' => $this->adminId,
                'status' => 'processing',
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $batchId = (int) $this->db->insertID();

            $this->insertSourceRows($batchId, $parsed['source_rows']);
            $this->insertGoldMovements($batchId, $parsed['gold_movements'], (float) $parsed['gold_closing_24k']);
            $this->insertDiamondMovements($batchId, $parsed['diamond_movements'], $parsed['diamond_closing']);
            $this->insertDiamondIssueLines($batchId, $parsed['diamond_issue_lines']);
            $readySummary = $this->insertReadyItems($batchId, $parsed['ready_items'], $parsed['gold_movements']);
            $this->insertDocuments($batchId, $parsed['documents']);
            $this->completeAllProductionOrders();

            $summary = [
                'source_rows' => count($parsed['source_rows']),
                'purchase_documents' => count($parsed['documents']),
                'gold_movements' => count($parsed['gold_movements']),
                'diamond_movements' => count($parsed['diamond_movements']),
                'diamond_issue_lines' => count($parsed['diamond_issue_lines']),
                'ready_items' => count($parsed['ready_items']),
                'ready_images' => $readySummary['images'],
                'finished_jewellery_items' => (int) $this->db->table('fg_items')->countAllResults(),
                'labour_bills' => $readySummary['labour_bills'],
                'karigar_payments' => $readySummary['karigar_payments'],
                'vendors' => count($this->vendorIds),
                'karigars' => count($this->karigarIds),
                'orders' => count($this->orderIds),
                'gold_closing_24k_gm' => round((float) $parsed['gold_closing_24k'], 3),
                'diamond_closing_cts' => round(array_sum(array_map('floatval', $parsed['diamond_closing'])), 3),
                'diamond_stock_buckets' => count($parsed['diamond_closing']),
            ];
            $this->db->table('production_import_batches')->where('id', $batchId)->update([
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('The database rejected one or more imported records.');
            }
            $this->db->transCommit();

            return ['batch_id' => $batchId, 'summary' => $summary, 'checksum' => $archiveHash];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function purgeOperationalData(): void
    {
        $preserve = array_flip([
            'migrations',
            'admin_users',
            'roles',
            'permissions',
            'role_permissions',
            'user_roles',
            'company_settings',
            ...self::IMPORT_TABLES,
        ]);

        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->db->listTables() as $table) {
            if (! isset($preserve[$table])) {
                $this->db->query('DELETE FROM `' . str_replace('`', '``', $table) . '`');
            }
        }
        foreach (self::IMPORT_TABLES as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->query('DELETE FROM `' . $table . '`');
            }
        }

        $this->db->query('DELETE FROM `user_roles`');
        $this->db->table('admin_users')->where('id !=', $this->adminId)->delete();
        $this->db->table('admin_users')->where('id', $this->adminId)->update([
            'name' => 'Shweta',
            'email' => 'shweta@aabhushan.in',
            'password_hash' => $this->adminPasswordHash,
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $role = $this->db->table('roles')->groupStart()
            ->where('role_code', 'SUPER_ADMIN')
            ->orWhere('name', 'Super Admin')
            ->groupEnd()->get()->getRowArray();
        if (! $role) {
            $this->db->table('roles')->insert([
                'role_code' => 'SUPER_ADMIN',
                'name' => 'Super Admin',
                'description' => 'Full system access',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $role = ['id' => $this->db->insertID()];
        }
        $this->db->table('user_roles')->insert([
            'user_id' => $this->adminId,
            'role_id' => (int) $role['id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedInventoryFoundation(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('warehouses')->insert([
            'warehouse_code' => 'MAIN',
            'name' => 'Main Production Store',
            'warehouse_type' => 'STORE',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $warehouseId = (int) $this->db->insertID();
        $this->warehouseId = $warehouseId;
        $this->db->table('bins')->insert([
            'warehouse_id' => $warehouseId,
            'bin_code' => 'MAIN',
            'name' => 'Main Bin',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->binId = (int) $this->db->insertID();
        $this->db->table('inventory_locations')->insert([
            'name' => 'Main Production Store',
            'location_type' => 'Store',
            'is_active' => 1,
            'code' => 'MAIN',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->locationId = (int) $this->db->insertID();

        foreach ([
            '24K' => 100.000,
            '22K' => 91.666,
            '18K' => 75.000,
            '14K' => 58.333,
        ] as $code => $percentage) {
            $this->db->table('gold_purities')->insert([
                'purity_code' => $code,
                'purity_percent' => $percentage,
                'color_name' => 'YG',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $purityId = (int) $this->db->insertID();
            $this->goldPurityIds[$code] = $purityId;
            $this->db->table('gold_inventory_items')->insert([
                'gold_purity_id' => $purityId,
                'purity_code' => $code,
                'purity_percent' => $percentage,
                'color_name' => 'YG',
                'form_type' => 'Production Ledger',
                'remarks' => 'Imported from 2026-27 production records',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->goldItemIds[$code] = (int) $this->db->insertID();
        }
    }

    private function insertSourceRows(int $batchId, array $rows): void
    {
        $now = date('Y-m-d H:i:s');
        $records = [];
        foreach ($rows as $row) {
            $records[] = $row + ['batch_id' => $batchId, 'created_at' => $now];
            if (count($records) >= 250) {
                $this->db->table('production_source_rows')->insertBatch($records);
                $records = [];
            }
        }
        if ($records !== []) {
            $this->db->table('production_source_rows')->insertBatch($records);
        }
    }

    private function insertGoldMovements(int $batchId, array $movements, float $closing24k): void
    {
        usort($movements, static fn(array $a, array $b): int => strcmp((string) $a['movement_date'], (string) $b['movement_date']));
        $now = date('Y-m-d H:i:s');
        $ledgerBalances = [];
        foreach ($movements as $movement) {
            $karigarId = null;
            if (in_array($movement['movement_type'], ['issue', 'receive'], true)) {
                $karigarId = $this->ensureKarigar((string) $movement['party_name']);
            }
            $this->db->table('production_gold_movements')->insert([
                'batch_id' => $batchId,
                'karigar_id' => $karigarId,
                'movement_type' => $movement['movement_type'],
                'movement_date' => $movement['movement_date'],
                'party_name' => $movement['party_name'],
                'reference_no' => $movement['reference_no'] ?: null,
                'description' => $movement['description'] ?: null,
                'purity_label' => $movement['purity_label'] ?: null,
                'weight_24k_gm' => $movement['weight_24k_gm'],
                'received_weight_gm' => $movement['received_weight_gm'],
                'source_sheet' => $movement['source_sheet'],
                'source_row' => $movement['source_row'],
                'created_at' => $now,
            ]);
            $productionMovementId = (int) $this->db->insertID();

            if ($movement['movement_type'] === 'stock_in') {
                if (strtoupper((string) $movement['reference_no']) !== 'OPENING') {
                    $this->db->table('gold_inventory_purchase_headers')->insert([
                        'purchase_date' => $movement['movement_date'],
                        'supplier_name' => $movement['party_name'],
                        'invoice_no' => $movement['reference_no'] ?: null,
                        'location_id' => $this->locationId,
                        'notes' => 'Imported from BABU GOLD -26-27.xlsx',
                        'created_by' => $this->adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $purchaseId = (int) $this->db->insertID();
                    $this->db->table('gold_inventory_purchase_lines')->insert([
                        'purchase_id' => $purchaseId,
                        'item_id' => $this->goldItemIds['24K'],
                        'weight_gm' => $movement['weight_24k_gm'],
                        'fine_weight_gm' => $movement['weight_24k_gm'],
                        'rate_per_gm' => 0,
                        'line_value' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $itemId = $this->goldItemIds['24K'];
                $ledgerBalances[$itemId] = round(($ledgerBalances[$itemId] ?? 0) + (float) $movement['weight_24k_gm'], 3);
                $this->insertGoldInventoryLedger(
                    $movement,
                    $productionMovementId,
                    $itemId,
                    null,
                    (float) $movement['weight_24k_gm'],
                    0,
                    $ledgerBalances[$itemId]
                );
                $this->postMaterialAccountEntry(
                    'receive',
                    'GOLD_STOCK_IN',
                    (string) $movement['movement_date'],
                    0,
                    null,
                    'GOLD-' . $this->goldPurityIds['24K'] . '-YG-BAR',
                    'GOLD',
                    0,
                    (float) $movement['weight_24k_gm'],
                    (float) $movement['weight_24k_gm'],
                    $this->goldPurityIds['24K'],
                    'Imported gold stock receipt: ' . (string) $movement['party_name'],
                    'IMP-GSTK-' . $productionMovementId
                );
                continue;
            }

            $orderReference = (string) $movement['reference_no'];
            if ($this->normalizeReference($orderReference) === '') {
                $orderReference = sprintf(
                    'GOLD/%s/%s/%d',
                    strtoupper((string) $movement['movement_type']),
                    strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '-', (string) $movement['source_sheet'])),
                    (int) $movement['source_row']
                );
            }
            $orderId = $this->ensureProductionOrder(
                $orderReference,
                (string) $movement['movement_date'],
                (string) $movement['description'],
                $karigarId
            );
            if ($orderId <= 0) {
                continue;
            }
            $purityCode = $this->normalizePurity((string) $movement['purity_label']);
            $purityId = $this->goldPurityIds[$purityCode] ?? $this->goldPurityIds['24K'];

            if ($movement['movement_type'] === 'issue') {
                $this->db->table('gold_inventory_issue_headers')->insert([
                    'issue_date' => $movement['movement_date'],
                    'voucher_no' => $movement['reference_no'] ?: ('GOLD-ISSUE-' . $orderId),
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $this->locationId,
                    'issue_to' => $movement['party_name'],
                    'purpose' => 'Production',
                    'notes' => $movement['description'],
                    'created_by' => $this->adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $issueId = (int) $this->db->insertID();
                $this->db->table('gold_inventory_issue_lines')->insert([
                    'issue_id' => $issueId,
                    'item_id' => $this->goldItemIds['24K'],
                    'weight_gm' => $movement['weight_24k_gm'],
                    'fine_weight_gm' => $movement['weight_24k_gm'],
                    'rate_per_gm' => 0,
                    'line_value' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $itemId = $this->goldItemIds['24K'];
                $ledgerBalances[$itemId] = round(($ledgerBalances[$itemId] ?? 0) - (float) $movement['weight_24k_gm'], 3);
                $this->insertGoldInventoryLedger($movement, $issueId, $itemId, $orderId, 0, (float) $movement['weight_24k_gm'], $ledgerBalances[$itemId], 'gold_inventory_issue_headers');
                $this->postMaterialAccountEntry(
                    'issue',
                    'GOLD_ISSUE',
                    (string) $movement['movement_date'],
                    $karigarId ?: 0,
                    $orderId,
                    'GOLD-' . $this->goldPurityIds['24K'] . '-YG-BAR',
                    'GOLD',
                    0,
                    (float) $movement['weight_24k_gm'],
                    (float) $movement['weight_24k_gm'],
                    $this->goldPurityIds['24K'],
                    (string) $movement['description'],
                    'IMP-GISS-' . $productionMovementId
                );
            } else {
                $this->db->table('gold_inventory_return_headers')->insert([
                    'return_date' => $movement['movement_date'],
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $this->locationId,
                    'return_from' => $movement['party_name'],
                    'purpose' => 'Finished jewellery receipt',
                    'notes' => $movement['description'],
                    'created_by' => $this->adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'voucher_no' => $movement['reference_no'] ?: ('GOLD-RECEIVE-' . $orderId),
                ]);
                $returnId = (int) $this->db->insertID();
                $returnItemId = $this->goldItemIds[$purityCode] ?? $this->goldItemIds['18K'];
                $this->db->table('gold_inventory_return_lines')->insert([
                    'return_id' => $returnId,
                    'item_id' => $returnItemId,
                    'weight_gm' => $movement['received_weight_gm'],
                    'fine_weight_gm' => $movement['weight_24k_gm'],
                    'rate_per_gm' => 0,
                    'line_value' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $ledgerBalances[$returnItemId] = round(($ledgerBalances[$returnItemId] ?? 0) + (float) $movement['received_weight_gm'], 3);
                $this->insertGoldInventoryLedger($movement, $returnId, $returnItemId, $orderId, (float) $movement['received_weight_gm'], 0, $ledgerBalances[$returnItemId], 'gold_inventory_return_headers');
                $this->postMaterialAccountEntry(
                    'return',
                    'GOLD_RECEIVE',
                    (string) $movement['movement_date'],
                    $karigarId ?: 0,
                    $orderId,
                    'GOLD-' . $purityId . '-YG-BAR',
                    'GOLD',
                    0,
                    (float) $movement['received_weight_gm'],
                    (float) $movement['weight_24k_gm'],
                    $purityId,
                    (string) $movement['description'],
                    'IMP-GREC-' . $productionMovementId
                );
                $this->db->table('orders')->where('id', $orderId)->update(['status' => 'Ready', 'updated_at' => $now]);
            }

            $this->db->table('order_material_movements')->insert([
                'order_id' => $orderId,
                'movement_type' => $movement['movement_type'],
                'gold_gm' => $movement['received_weight_gm'] ?: $movement['weight_24k_gm'],
                'diamond_cts' => 0,
                'gold_purity_id' => $purityId,
                'karigar_id' => $karigarId,
                'location_id' => $this->locationId,
                'net_gold_weight_gm' => $movement['received_weight_gm'] ?: null,
                'pure_gold_weight_gm' => $movement['weight_24k_gm'] ?: null,
                'notes' => $movement['description'],
                'created_by' => $this->adminId,
                'created_at' => $movement['movement_date'] . ' 12:00:00',
                'updated_at' => $now,
            ]);
        }

        $closing24k = max(0, $closing24k);
        foreach ($this->goldItemIds as $code => $itemId) {
            $balance = $code === '24K' ? $closing24k : 0.0;
            $this->db->table('gold_inventory_stock')->insert([
                'item_id' => $itemId,
                'weight_balance_gm' => $balance,
                'fine_balance_gm' => $balance,
                'avg_cost_per_gm' => 0,
                'stock_value' => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertDiamondMovements(int $batchId, array $movements, array $closing): void
    {
        $now = date('Y-m-d H:i:s');
        $purchases = [];
        $issues = [];
        foreach ($movements as $movement) {
            $itemId = $this->ensureDiamondItem((string) $movement['quality_bucket']);
            $this->db->table('production_diamond_movements')->insert([
                'batch_id' => $batchId,
                'movement_date' => $movement['movement_date'],
                'party_name' => $movement['party_name'] ?: null,
                'reference_no' => $movement['reference_no'] ?: null,
                'description' => $movement['description'] ?: null,
                'movement_type' => $movement['movement_type'],
                'quality_bucket' => $movement['quality_bucket'],
                'received_cts' => $movement['received_cts'],
                'issued_cts' => $movement['issued_cts'],
                'source_sheet' => $movement['source_sheet'],
                'source_row' => $movement['source_row'],
                'created_at' => $now,
            ]);
            $productionMovementId = (int) $this->db->insertID();

            $key = $movement['source_sheet'] . ':' . $movement['source_row'];
            if ($movement['movement_type'] === 'purchase') {
                $purchases[$key]['header'] = $movement;
                $purchases[$key]['lines'][] = ['item_id' => $itemId, 'carat' => $movement['received_cts'], 'bucket' => $movement['quality_bucket'], 'production_id' => $productionMovementId];
            } elseif ($movement['movement_type'] === 'issue' && (float) $movement['issued_cts'] !== 0.0) {
                $issues[$key]['header'] = $movement;
                $issues[$key]['lines'][] = ['item_id' => $itemId, 'carat' => $movement['issued_cts'], 'bucket' => $movement['quality_bucket'], 'production_id' => $productionMovementId];
            } elseif (in_array($movement['movement_type'], ['opening', 'receive'], true) && (float) $movement['received_cts'] !== 0.0) {
                $this->postMaterialAccountEntry(
                    'receive',
                    strtoupper((string) $movement['movement_type']) . '_DIAMOND',
                    (string) $movement['movement_date'],
                    0,
                    null,
                    'DIAMOND-' . strtoupper((string) preg_replace('/[^A-Z0-9]+/', '-', (string) $movement['quality_bucket'])),
                    'DIAMOND',
                    (float) $movement['received_cts'],
                    0,
                    0,
                    null,
                    (string) $movement['description'],
                    'IMP-DREC-' . $productionMovementId
                );
            }
        }

        foreach ($purchases as $purchase) {
            $header = $purchase['header'];
            $vendorId = $this->ensureVendor((string) $header['party_name']);
            $this->db->table('purchase_headers')->insert([
                'purchase_date' => $header['movement_date'],
                'vendor_id' => $vendorId,
                'supplier_name' => $header['party_name'],
                'invoice_no' => $header['reference_no'] ?: null,
                'tax_percentage' => 0,
                'invoice_total' => 0,
                'notes' => 'Imported from DIA.STOCK LEDGER (26-27).xlsx',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $purchaseId = (int) $this->db->insertID();
            foreach ($purchase['lines'] as $line) {
                $this->db->table('purchase_lines')->insert([
                    'purchase_id' => $purchaseId,
                    'item_id' => $line['item_id'],
                    'pcs' => 0,
                    'carat' => $line['carat'],
                    'rate_per_carat' => 0,
                    'line_value' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->postMaterialAccountEntry(
                    'receive',
                    'DIAMOND_PURCHASE',
                    (string) $header['movement_date'],
                    0,
                    null,
                    'DIAMOND-' . strtoupper((string) preg_replace('/[^A-Z0-9]+/', '-', (string) $line['bucket'])),
                    'DIAMOND',
                    (float) $line['carat'],
                    0,
                    0,
                    null,
                    'Imported diamond purchase from ' . (string) $header['party_name'],
                    'IMP-DPUR-' . (int) $line['production_id']
                );
            }
        }

        foreach ($issues as $issue) {
            $header = $issue['header'];
            $karigarId = $this->ensureKarigar((string) $header['party_name']);
            $orderId = $this->ensureProductionOrder(
                (string) $header['reference_no'],
                (string) $header['movement_date'],
                (string) $header['description'],
                $karigarId
            );
            if ($orderId <= 0) {
                continue;
            }
            $this->db->table('issue_headers')->insert([
                'issue_date' => $header['movement_date'],
                'voucher_no' => $header['reference_no'] ?: ('DIA-ISSUE-' . $orderId),
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $this->locationId,
                'issue_to' => $header['party_name'],
                'purpose' => $header['description'] ?: 'Production',
                'notes' => 'Imported diamond stock issue',
                'created_by' => $this->adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $issueId = (int) $this->db->insertID();
            $totalCarat = 0.0;
            foreach ($issue['lines'] as $line) {
                $totalCarat += (float) $line['carat'];
                $this->db->table('issue_lines')->insert([
                    'issue_id' => $issueId,
                    'item_id' => $line['item_id'],
                    'pcs' => 0,
                    'carat' => $line['carat'],
                    'rate_per_carat' => 0,
                    'line_value' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->postMaterialAccountEntry(
                    'issue',
                    'DIAMOND_ISSUE',
                    (string) $header['movement_date'],
                    $karigarId,
                    $orderId,
                    'DIAMOND-' . strtoupper((string) preg_replace('/[^A-Z0-9]+/', '-', (string) $line['bucket'])),
                    'DIAMOND',
                    (float) $line['carat'],
                    0,
                    0,
                    null,
                    (string) $header['description'],
                    'IMP-DISS-' . (int) $line['production_id']
                );
            }
            $this->db->table('order_material_movements')->insert([
                'order_id' => $orderId,
                'movement_type' => 'issue',
                'gold_gm' => 0,
                'diamond_cts' => $totalCarat,
                'karigar_id' => $karigarId,
                'location_id' => $this->locationId,
                'notes' => $header['description'],
                'created_by' => $this->adminId,
                'created_at' => $header['movement_date'] . ' 12:00:00',
                'updated_at' => $now,
            ]);
        }

        foreach ($this->diamondItemIds as $bucket => $itemId) {
            $balance = max(0, (float) ($closing[$bucket] ?? 0));
            $this->db->table('stock')->insert([
                'item_id' => $itemId,
                'pcs_balance' => 0,
                'carat_balance' => $balance,
                'avg_cost_per_carat' => 0,
                'stock_value' => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertDiamondIssueLines(int $batchId, array $lines): void
    {
        $now = date('Y-m-d H:i:s');
        $records = [];
        foreach ($lines as $line) {
            $records[] = [
                'batch_id' => $batchId,
                'karigar_id' => $this->ensureKarigar((string) $line['karigar_name']),
                'issue_date' => $line['issue_date'],
                'issue_group' => $line['issue_group'],
                'design_no' => $line['design_no'] ?: null,
                'quality' => $line['quality'] ?: null,
                'shade' => $line['shade'] ?: null,
                'size_label' => $line['size_label'] ?: null,
                'pcs' => $line['pcs'],
                'weight_cts' => $line['weight_cts'],
                'bag_label' => $line['bag_label'] ?: null,
                'source_sheet' => $line['source_sheet'],
                'source_row' => $line['source_row'],
                'created_at' => $now,
            ];
            if (count($records) >= 250) {
                $this->db->table('production_diamond_issue_lines')->insertBatch($records);
                $records = [];
            }
        }
        if ($records !== []) {
            $this->db->table('production_diamond_issue_lines')->insertBatch($records);
        }
    }

    /**
     * Turn every ready-workbook group into a labour bill and every row into finished jewellery.
     * Ready groups are matched to gold receipts by karigar and exact returned net weight; unmatched
     * groups receive a traceable synthetic production order rather than being left orphaned.
     *
     * @return array{images:int,fg_items:int,labour_bills:int,karigar_payments:int}
     */
    private function insertReadyItems(int $batchId, array $items, array $goldMovements): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[(string) $item['ready_group']][] = $item;
        }

        $receives = array_values(array_filter(
            $goldMovements,
            static fn(array $movement): bool => (string) ($movement['movement_type'] ?? '') === 'receive'
        ));
        $usedReceives = [];
        $now = date('Y-m-d H:i:s');
        $summary = ['images' => 0, 'fg_items' => 0, 'labour_bills' => 0, 'karigar_payments' => 0];

        foreach ($groups as $groupKey => $groupItems) {
            $first = $groupItems[0];
            $karigarName = (string) $first['karigar_name'];
            $karigarId = $this->ensureKarigar($karigarName);
            $readyDate = $this->firstNonEmptyColumn($groupItems, 'ready_date') ?: date('Y-m-d');
            $groupNetWeight = array_sum(array_map(static fn(array $row): float => (float) $row['net_weight_gm'], $groupItems));
            $groupPureWeight = array_sum(array_map(static fn(array $row): float => (float) $row['pure_weight_gm'], $groupItems));
            $matchedIndexes = $this->matchReadyReceipts($karigarName, $groupNetWeight, $groupPureWeight, $readyDate, $receives, $usedReceives);
            $references = [];
            foreach ($matchedIndexes as $index) {
                $usedReceives[$index] = true;
                $reference = $this->normalizeReference((string) ($receives[$index]['reference_no'] ?? ''));
                if ($reference !== '') {
                    $references[$reference] = true;
                }
            }

            $reference = (string) array_key_first($references);
            if ($reference === '') {
                $reference = 'READY/' . strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '-', $groupKey));
            }
            $orderId = $this->ensureProductionOrder($reference, $readyDate, 'Ready production group ' . $groupKey, $karigarId);
            if ($orderId <= 0) {
                throw new RuntimeException('Could not create an order for ready group ' . $groupKey . '.');
            }
            if ($references !== []) {
                $this->db->table('orders')->where('id', $orderId)->update([
                    'order_notes' => 'Ready group ' . $groupKey . '; source receipt references: ' . implode(', ', array_keys($references)),
                    'updated_at' => $now,
                ]);
            }

            $labourAmount = round(array_sum(array_map(static fn(array $row): float => (float) $row['labour_charges'], $groupItems)), 2);
            $paymentStatus = $this->groupIsPaid($groupItems) ? 'Paid' : 'Pending';
            $paymentDate = $this->firstNonEmptyColumn($groupItems, 'payment_date');
            $billNo = 'IMP-LAB-' . strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '-', $groupKey));
            $this->db->table('labour_bills')->insert([
                'bill_no' => substr($billNo, 0, 40),
                'bill_date' => $readyDate,
                'order_id' => $orderId,
                'receive_movement_id' => null,
                'karigar_id' => $karigarId,
                'gold_weight_gm' => round($groupNetWeight, 3),
                'rate_per_gm' => $groupNetWeight > 0 ? round($labourAmount / $groupNetWeight, 2) : 0,
                'labour_amount' => $labourAmount,
                'other_amount' => 0,
                'total_amount' => $labourAmount,
                'due_date' => null,
                'payment_status' => $paymentStatus,
                'notes' => 'Imported from ready workbook group ' . $groupKey,
                'created_by' => $this->adminId,
                'created_at' => $readyDate . ' 18:00:00',
                'updated_at' => $now,
            ]);
            $labourBillId = (int) $this->db->insertID();
            $summary['labour_bills']++;

            if ($labourAmount > 0) {
                $this->insertKarigarPaymentLedger($karigarId, $orderId, 'charge', $labourAmount, $billNo, 'Imported labour bill ' . $groupKey, $readyDate);
            }
            if ($paymentStatus === 'Paid' && $labourAmount > 0) {
                $actualPaymentDate = $paymentDate ?: $readyDate;
                $paymentNote = 'Imported paid marker for ready group ' . $groupKey;
                if ($paymentDate === null) {
                    $paymentNote .= '; source did not provide a payment date, so the ready date is used';
                }
                $this->insertKarigarPayment($labourBillId, $karigarId, $orderId, $labourAmount, $actualPaymentDate, $billNo, $paymentNote);
                $summary['karigar_payments']++;
            }

            foreach ($groupItems as $item) {
                $imagePath = $item['image_path'] ?: null;
                $this->db->table('production_ready_items')->insert([
                    'batch_id' => $batchId,
                    'karigar_id' => $karigarId,
                    'order_id' => $orderId,
                    'fg_item_id' => null,
                    'ready_group' => $item['ready_group'],
                    'ready_date' => $item['ready_date'],
                    'serial_no' => $item['serial_no'] ?: null,
                    'design_name' => $item['design_name'] ?: null,
                    'reference_no' => $item['reference_no'] ?: ($references !== [] ? implode(', ', array_keys($references)) : $reference),
                    'purity_label' => $item['purity_label'] ?: null,
                    'gross_weight_gm' => $item['gross_weight_gm'],
                    'net_weight_gm' => $item['net_weight_gm'],
                    'pure_weight_gm' => $item['pure_weight_gm'],
                    'gold_amount' => $item['gold_amount'],
                    'labour_charges' => $item['labour_charges'],
                    'total_value' => $item['total_value'],
                    'stones_json' => $item['stones_json'],
                    'image_path' => $imagePath,
                    'status_note' => $item['status_note'] ?: null,
                    'payment_status' => $paymentStatus,
                    'payment_date' => $paymentDate,
                    'source_sheet' => $item['source_sheet'],
                    'source_row' => $item['source_row'],
                    'created_at' => $now,
                ]);
                $readyItemId = (int) $this->db->insertID();
                $fgItemId = $this->createFinishedJewelleryItem($readyItemId, $orderId, $item, $readyDate);
                $this->db->table('production_ready_items')->where('id', $readyItemId)->update(['fg_item_id' => $fgItemId]);
                $summary['fg_items']++;
                if ($imagePath !== null) {
                    $summary['images']++;
                }
            }
        }

        return $summary;
    }

    /** @return list<int> */
    private function matchReadyReceipts(string $karigarName, float $netWeight, float $pureWeight, string $readyDate, array $receives, array $used): array
    {
        $candidateIndexes = [];
        foreach ($receives as $index => $receive) {
            if (isset($used[$index]) || $this->canonicalKarigar((string) ($receive['party_name'] ?? '')) !== $this->canonicalKarigar($karigarName)) {
                continue;
            }
            $candidateIndexes[] = $index;
        }

        $best = [];
        $bestDateDistance = PHP_INT_MAX;
        $candidateCount = count($candidateIndexes);
        for ($size = 1; $size <= min(3, $candidateCount); $size++) {
            foreach ($this->combinations($candidateIndexes, $size) as $combination) {
                $net = 0.0;
                $pure = 0.0;
                $dateDistance = 0;
                foreach ($combination as $index) {
                    $net += (float) ($receives[$index]['received_weight_gm'] ?? 0);
                    $pure += (float) ($receives[$index]['weight_24k_gm'] ?? 0);
                    $dateDistance += abs((int) ((strtotime($readyDate) - strtotime((string) $receives[$index]['movement_date'])) / 86400));
                }
                $netMatches = abs($net - $netWeight) <= 0.012;
                $pureMatches = $pureWeight <= 0 || abs($pure - $pureWeight) <= 0.012;
                if ($netMatches && $pureMatches && $dateDistance < $bestDateDistance) {
                    $best = $combination;
                    $bestDateDistance = $dateDistance;
                }
            }
            if ($best !== []) {
                break;
            }
        }
        return $best;
    }

    /** @return list<list<int>> */
    private function combinations(array $values, int $size): array
    {
        if ($size === 0) {
            return [[]];
        }
        if (count($values) < $size) {
            return [];
        }
        $result = [];
        foreach ($values as $offset => $value) {
            $tail = array_slice($values, $offset + 1);
            foreach ($this->combinations($tail, $size - 1) as $combination) {
                $result[] = array_merge([$value], $combination);
            }
        }
        return $result;
    }

    private function groupIsPaid(array $items): bool
    {
        foreach ($items as $item) {
            if (strcasecmp((string) ($item['payment_status'] ?? ''), 'Paid') === 0) {
                return true;
            }
        }
        return false;
    }

    private function firstNonEmptyColumn(array $rows, string $column): ?string
    {
        foreach ($rows as $row) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    private function createFinishedJewelleryItem(int $readyItemId, int $orderId, array $item, string $readyDate): int
    {
        $stones = json_decode((string) ($item['stones_json'] ?? '[]'), true);
        $diamondCts = 0.0;
        $stoneWeight = 0.0;
        foreach (is_array($stones) ? $stones : [] as $stone) {
            $weight = (float) ($stone['weight'] ?? 0);
            $name = strtoupper((string) ($stone['name'] ?? ''));
            if (preg_match('/DIA|DIAMOND|CVD|POLKI|EMD|BRD|ROUND/', $name) === 1) {
                $diamondCts += $weight;
            } else {
                $stoneWeight += $weight;
            }
        }
        $jobCard = $this->db->table('job_cards')->select('id')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getRowArray();
        $tagNo = 'PROD-' . str_pad((string) $readyItemId, 6, '0', STR_PAD_LEFT);
        $this->db->table('fg_items')->insert([
            'tag_no' => $tagNo,
            'order_id' => $orderId,
            'job_card_id' => $jobCard ? (int) $jobCard['id'] : null,
            'production_ready_item_id' => $readyItemId,
            'product_id' => null,
            'variant_id' => null,
            'design_name' => $item['design_name'] ?: null,
            'purity_label' => $item['purity_label'] ?: null,
            'qty' => 1,
            'gross_wt' => $item['gross_weight_gm'],
            'net_gold_wt' => $item['net_weight_gm'],
            'diamond_cts' => round($diamondCts, 3),
            'stone_wt' => round($stoneWeight, 3),
            'studded_details_json' => $item['stones_json'],
            'source_image_path' => $item['image_path'] ?: null,
            'status' => 'AVAILABLE',
            'warehouse_id' => $this->warehouseId,
            'bin_id' => $this->binId,
            'showroom_id' => null,
            'showroom_counter_id' => null,
            'showroom_stock_status' => 'FG_STORE',
            'inventory_remarks' => 'Created from completed imported order; ready group ' . $item['ready_group'],
            'terminal_at' => null,
            'created_by' => $this->adminId,
            'created_at' => $readyDate . ' 18:00:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $fgItemId = (int) $this->db->insertID();
        $this->db->table('showroom_fg_movements')->insert([
            'fg_item_id' => $fgItemId,
            'movement_type' => 'ORDER_COMPLETED_TO_FG',
            'reference_type' => 'production_ready_items',
            'reference_id' => $readyItemId,
            'remarks' => 'Finished jewellery created from ready workbook row ' . $item['source_sheet'] . ':' . $item['source_row'],
            'created_by' => $this->adminId,
            'created_at' => $readyDate . ' 18:00:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $fgItemId;
    }

    private function insertKarigarPaymentLedger(int $karigarId, int $orderId, string $entryType, float $amount, string $reference, string $notes, string $date): void
    {
        $this->db->table('karigar_payment_ledgers')->insert([
            'karigar_id' => $karigarId,
            'order_id' => $orderId,
            'entry_type' => $entryType,
            'amount' => $amount,
            'reference_no' => substr($reference, 0, 80),
            'notes' => $notes,
            'created_by' => $this->adminId,
            'created_at' => $date . ' 18:00:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertKarigarPayment(int $billId, int $karigarId, int $orderId, float $amount, string $date, string $reference, string $notes): void
    {
        $paymentNo = 'IMP-KPAY-' . str_pad((string) $billId, 6, '0', STR_PAD_LEFT);
        $this->db->table('labour_bill_payments')->insert([
            'labour_bill_id' => $billId,
            'payment_date' => $date,
            'amount' => $amount,
            'reference_no' => substr($reference, 0, 80),
            'notes' => $notes,
            'created_by' => $this->adminId,
            'created_at' => $date . ' 18:05:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('account_payments')->insert([
            'payment_no' => $paymentNo,
            'payment_date' => $date,
            'party_type' => 'karigar',
            'karigar_id' => $karigarId,
            'vendor_id' => null,
            'amount' => $amount,
            'payment_mode' => 'Source Record',
            'reference_no' => substr($reference, 0, 80),
            'bill_type' => 'labour',
            'labour_bill_id' => $billId,
            'notes' => $notes,
            'created_by' => $this->adminId,
            'created_at' => $date . ' 18:05:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insertKarigarPaymentLedger($karigarId, $orderId, 'payment', $amount, $reference, $notes, $date);
    }

    private function insertDocuments(int $batchId, array $documents): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($documents as $document) {
            $vendorId = $this->ensureVendor((string) $document['vendor_name']);
            $this->db->table('production_purchase_documents')->insert($document + [
                'batch_id' => $batchId,
                'vendor_id' => $vendorId,
                'created_at' => $now,
            ]);
        }
    }

    private function insertGoldInventoryLedger(
        array $movement,
        int $referenceId,
        int $itemId,
        ?int $orderId,
        float $debitWeight,
        float $creditWeight,
        float $balanceWeight,
        string $referenceTable = 'production_gold_movements'
    ): void {
        $isDebit = $debitWeight > 0;
        $fine = (float) ($movement['weight_24k_gm'] ?? 0);
        $this->db->table('gold_inventory_ledger_entries')->insert([
            'txn_date' => $movement['movement_date'],
            'txn_type' => strtoupper((string) $movement['movement_type']),
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId,
            'order_id' => $orderId,
            'karigar_id' => in_array($movement['movement_type'], ['issue', 'receive'], true) ? $this->ensureKarigar((string) $movement['party_name']) : null,
            'location_id' => $this->locationId,
            'item_id' => $itemId,
            'debit_weight_gm' => round($debitWeight, 3),
            'credit_weight_gm' => round($creditWeight, 3),
            'debit_fine_gm' => $isDebit ? round($fine, 3) : 0,
            'credit_fine_gm' => $isDebit ? 0 : round($fine, 3),
            'balance_weight_gm' => round($balanceWeight, 3),
            'balance_fine_gm' => 0,
            'rate_per_gm' => 0,
            'line_value' => 0,
            'notes' => trim((string) ($movement['description'] ?? '')) ?: 'Imported production gold movement',
            'created_by' => $this->adminId,
            'created_at' => (string) $movement['movement_date'] . ' 12:00:00',
        ]);
    }

    private function postMaterialAccountEntry(
        string $direction,
        string $voucherType,
        string $date,
        int $karigarId,
        ?int $orderId,
        string $itemKey,
        string $itemType,
        float $qtyCts,
        float $qtyWeight,
        float $fineGold,
        ?int $goldPurityId,
        string $remarks,
        string $voucherNo
    ): void {
        $warehouseAccountId = $this->ensureImportAccount('WAREHOUSE', 'WH-' . $this->warehouseId, 'Main Production Store Warehouse', 'warehouses', $this->warehouseId);
        if ($karigarId > 0) {
            $karigar = $this->db->table('karigars')->select('name')->where('id', $karigarId)->get()->getRowArray();
            $partyAccountId = $this->ensureImportAccount('KARIGAR', 'KARIGAR-' . $karigarId, 'Karigar - ' . (string) ($karigar['name'] ?? $karigarId), 'karigars', $karigarId);
        } else {
            $partyAccountId = $this->ensureImportAccount('SOURCE', 'PRODUCTION-SOURCE', 'Imported Production Source', null, null);
        }

        $isIssue = $direction === 'issue';
        $debitAccountId = $isIssue ? $partyAccountId : $warehouseAccountId;
        $creditAccountId = $isIssue ? $warehouseAccountId : $partyAccountId;
        $this->db->table('vouchers')->insert([
            'voucher_no' => substr($voucherNo, 0, 50),
            'voucher_type' => substr(strtoupper($voucherType), 0, 40),
            'voucher_date' => $date,
            'voucher_datetime' => $date . ' 12:00:00',
            'from_warehouse_id' => $isIssue ? $this->warehouseId : null,
            'from_bin_id' => $isIssue ? $this->binId : null,
            'to_warehouse_id' => $isIssue ? null : $this->warehouseId,
            'to_bin_id' => $isIssue ? null : $this->binId,
            'order_id' => $orderId,
            'party_id' => $karigarId > 0 ? $karigarId : null,
            'debit_account_id' => $debitAccountId,
            'credit_account_id' => $creditAccountId,
            'status' => 'Posted',
            'remarks' => $remarks,
            'created_by' => $this->adminId,
            'created_at' => $date . ' 12:00:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $voucherId = (int) $this->db->insertID();
        $line = [
            'voucher_id' => $voucherId,
            'line_no' => 1,
            'item_type' => $itemType,
            'item_key' => substr($itemKey, 0, 160),
            'material_name' => $itemType === 'GOLD' ? 'Gold' : 'Diamond',
            'gold_purity_id' => $goldPurityId,
            'qty_pcs' => 0,
            'qty_cts' => round($qtyCts, 3),
            'qty_weight' => round($qtyWeight, 3),
            'fine_gold' => round($fineGold, 3),
            'rate' => 0,
            'amount' => 0,
            'remarks' => $remarks,
            'created_at' => $date . ' 12:00:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->table('voucher_lines')->insert($line);
        $this->db->table('ledger_entries')->insert([
            'voucher_id' => $voucherId,
            'line_no' => 1,
            'debit_account_id' => $debitAccountId,
            'credit_account_id' => $creditAccountId,
            'item_type' => $itemType,
            'item_key' => substr($itemKey, 0, 160),
            'qty_pcs' => 0,
            'qty_cts' => round($qtyCts, 3),
            'qty_weight' => round($qtyWeight, 3),
            'fine_gold_qty' => round($fineGold, 3),
            'order_id' => $orderId,
            'created_at' => $date . ' 12:00:00',
        ]);
        $this->adjustAccountMaterialBalance($debitAccountId, $itemType, $itemKey, $qtyCts, $qtyWeight, $fineGold, 1);
        $this->adjustAccountMaterialBalance($creditAccountId, $itemType, $itemKey, $qtyCts, $qtyWeight, $fineGold, -1);
    }

    private function ensureImportAccount(string $type, string $code, string $name, ?string $referenceTable, ?int $referenceId): int
    {
        if (isset($this->accountIds[$code])) {
            return $this->accountIds[$code];
        }
        $row = $this->db->table('accounts')->select('id')->where('account_code', $code)->get()->getRowArray();
        if ($row) {
            return $this->accountIds[$code] = (int) $row['id'];
        }
        $this->db->table('accounts')->insert([
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => $type,
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->accountIds[$code] = (int) $this->db->insertID();
    }

    private function adjustAccountMaterialBalance(int $accountId, string $itemType, string $itemKey, float $qtyCts, float $qtyWeight, float $fineGold, int $direction): void
    {
        $row = $this->db->table('account_balances')
            ->where('account_id', $accountId)
            ->where('item_type', $itemType)
            ->where('item_key', substr($itemKey, 0, 160))
            ->get()->getRowArray();
        $values = [
            'qty_pcs' => round((float) ($row['qty_pcs'] ?? 0), 3),
            'qty_cts' => round((float) ($row['qty_cts'] ?? 0) + ($direction * $qtyCts), 3),
            'qty_weight' => round((float) ($row['qty_weight'] ?? 0) + ($direction * $qtyWeight), 3),
            'fine_gold_qty' => round((float) ($row['fine_gold_qty'] ?? 0) + ($direction * $fineGold), 3),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($row) {
            $this->db->table('account_balances')->where('id', (int) $row['id'])->update($values);
            return;
        }
        $this->db->table('account_balances')->insert($values + [
            'account_id' => $accountId,
            'item_type' => $itemType,
            'item_key' => substr($itemKey, 0, 160),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function completeAllProductionOrders(): void
    {
        $orders = $this->db->table('orders')->select('id, status')->get()->getResultArray();
        $now = date('Y-m-d H:i:s');
        foreach ($orders as $order) {
            $orderId = (int) $order['id'];
            $fromStatus = (string) $order['status'];
            $this->db->table('orders')->where('id', $orderId)->update(['status' => 'Completed', 'updated_at' => $now]);
            $this->db->table('order_items')->where('order_id', $orderId)->update(['item_status' => 'Completed', 'updated_at' => $now]);
            $this->db->table('job_cards')->where('order_id', $orderId)->update(['status' => 'Completed', 'qc_status' => 'Passed', 'updated_at' => $now]);
            if ($fromStatus !== 'Completed') {
                $this->db->table('order_status_history')->insert([
                    'order_id' => $orderId,
                    'from_status' => $fromStatus,
                    'to_status' => 'Completed',
                    'remarks' => 'Completed during reconciled production import',
                    'changed_by' => $this->adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            (new FinishedJewelleryService($this->db))->createForCompletedOrder($orderId, $this->adminId);
        }
    }

    private function ensureProductionOrder(string $reference, string $date, string $description, ?int $karigarId): int
    {
        $reference = $this->normalizeReference($reference);
        if ($reference === '') {
            return 0;
        }
        if (isset($this->orderIds[$reference])) {
            if ($karigarId) {
                $this->db->table('orders')->where('id', $this->orderIds[$reference])->update([
                    'assigned_karigar_id' => $karigarId,
                    'assigned_at' => $date . ' 09:00:00',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return $this->orderIds[$reference];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('orders')->insert([
            'order_no' => $reference,
            'order_type' => 'Manufacturing',
            'order_from' => 'Imported Production Ledger',
            'customer_id' => null,
            'assigned_karigar_id' => $karigarId,
            'assigned_at' => $karigarId ? $date . ' 09:00:00' : null,
            'status' => 'In Production',
            'priority' => 'Medium',
            'order_notes' => $description ?: 'Imported production job',
            'created_by' => $this->adminId,
            'created_at' => $date . ' 09:00:00',
            'updated_at' => $now,
        ]);
        $orderId = (int) $this->db->insertID();
        $this->db->table('order_items')->insert([
            'order_id' => $orderId,
            'item_description' => $description ?: $reference,
            'qty' => 1,
            'gold_required_gm' => 0,
            'diamond_required_cts' => 0,
            'item_status' => 'In Production',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $orderItemId = (int) $this->db->insertID();
        $this->db->table('job_cards')->insert([
            'job_card_no' => 'JC-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper($reference)),
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'status' => 'Assigned',
            'priority' => 'Medium',
            'qc_status' => 'Pending',
            'created_by' => $this->adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->orderIds[$reference] = $orderId;

        return $orderId;
    }

    private function ensureKarigar(string $name): int
    {
        $name = $this->canonicalKarigar($name);
        if ($name === '') {
            $name = 'Unspecified Production Karigar';
        }
        $key = strtoupper($name);
        if (isset($this->karigarIds[$key])) {
            return $this->karigarIds[$key];
        }
        $this->db->table('karigars')->insert([
            'name' => $name,
            'department' => 'Production',
            'notes' => 'Imported from the 2026-27 production archive',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->karigarIds[$key] = (int) $this->db->insertID();
    }

    private function ensureVendor(string $name): int
    {
        $name = $this->canonicalVendor($name);
        if ($name === '') {
            $name = 'Unknown Supplier';
        }
        $key = strtoupper($name);
        if (isset($this->vendorIds[$key])) {
            return $this->vendorIds[$key];
        }
        $row = $this->db->table('vendors')->where('name', $name)->get()->getRowArray();
        if ($row) {
            return $this->vendorIds[$key] = (int) $row['id'];
        }
        $this->db->table('vendors')->insert([
            'name' => $name,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->vendorIds[$key] = (int) $this->db->insertID();
    }

    private function ensureDiamondItem(string $bucket): int
    {
        $bucket = trim($bucket) ?: 'UNSPECIFIED';
        $key = strtoupper($bucket);
        if (isset($this->diamondItemIds[$key])) {
            return $this->diamondItemIds[$key];
        }
        $type = str_contains($key, 'LAB') || str_contains($key, 'CVD') ? 'Lab Grown' : 'Natural';
        $shape = str_contains($key, 'FANCY') ? 'Fancy' : 'Round';
        $clarity = substr($bucket, 0, 20);
        $this->db->table('items')->insert([
            'diamond_type' => $type,
            'shape' => $shape,
            'chalni_from' => 'Imported',
            'chalni_to' => 'Imported',
            'color' => null,
            'clarity' => $clarity,
            'cut' => null,
            'remarks' => 'Source quality bucket: ' . $bucket,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->diamondItemIds[$key] = (int) $this->db->insertID();
    }

    private function resolveAdminId(int $requestedId): int
    {
        if ($requestedId > 0) {
            $row = $this->db->table('admin_users')->select('id')->where('id', $requestedId)->get()->getRowArray();
            if ($row) {
                return (int) $row['id'];
            }
        }
        $row = $this->db->table('admin_users')->select('id')->where('email', 'admin@demo.com')->get()->getRowArray()
            ?? $this->db->table('admin_users')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $row) {
            throw new RuntimeException('No administrator account exists to preserve.');
        }
        return (int) $row['id'];
    }

    private function collectSourceRows(Worksheet $sheet, string $sourceFile, string $recordType, array &$target): void
    {
        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $values = $this->rowValues($sheet, $row);
            if ($values === [] || ! array_filter($values, static fn($value): bool => $value !== null && $value !== '')) {
                continue;
            }
            $target[] = [
                'source_file' => $sourceFile,
                'sheet_name' => $sheet->getTitle(),
                'row_number' => $row,
                'record_type' => $recordType,
                'record_key' => $sheet->getTitle() . ':' . $row,
                'data_json' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            ];
        }
    }

    /** @return list<mixed> */
    private function rowValues(Worksheet $sheet, int $row): array
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $values = [];
        for ($column = 1; $column <= $maxColumn; $column++) {
            $value = $this->cell($sheet, $column, $row);
            if ($value instanceof DateTimeInterface) {
                $value = $value->format(DateTimeInterface::ATOM);
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            $values[] = $value;
        }
        while ($values !== [] && end($values) === null) {
            array_pop($values);
        }
        return $values;
    }

    private function cell(Worksheet $sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);
        try {
            $calculated = $cell->getCalculatedValue();
            return $calculated !== null ? $calculated : $cell->getValue();
        } catch (Throwable) {
            return $cell->getValue();
        }
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }
        return trim((string) $value);
    }

    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 6);
        }
        if (! is_string($value)) {
            return 0.0;
        }
        $normalized = str_replace([',', '₹', ' '], '', trim($value));
        return is_numeric($normalized) ? round((float) $normalized, 6) : 0.0;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})\b/', $value, $matches) !== 1) {
            return null;
        }
        $year = (int) $matches[3];
        if ($year < 100) {
            $year += 2000;
        }
        if (! checkdate((int) $matches[2], (int) $matches[1], $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[1]);
    }

    private function dateFromFilename(string $filename): ?string
    {
        return $this->dateValue(pathinfo($filename, PATHINFO_FILENAME));
    }

    private function inferPurity(float $received, float $equivalent24k, string $description): string
    {
        $upper = strtoupper($description);
        foreach (['24K', '22K', '18K', '14K'] as $code) {
            if (str_contains(str_replace(' ', '', $upper), $code)) {
                return $code;
            }
        }
        if ($received > 0 && $equivalent24k > 0) {
            $ratio = $equivalent24k / $received;
            if ($ratio >= 0.94) {
                return '22K';
            }
            if ($ratio >= 0.70 && $ratio <= 0.88) {
                return '18K';
            }
        }
        return '18K';
    }

    private function normalizePurity(string $purity): string
    {
        $purity = strtoupper(str_replace([' ', 'T'], '', $purity));
        return in_array($purity, ['14K', '18K', '22K', '24K'], true) ? $purity : '24K';
    }

    private function normalizeReference(string $reference): string
    {
        $reference = strtoupper(trim($reference));
        $reference = str_replace('SMGAJ-J/', 'SMGAJ/', $reference);
        $reference = preg_replace('/\s+/', '', $reference) ?? $reference;
        return substr($reference, 0, 40);
    }

    private function canonicalKarigar(string $name): string
    {
        $key = strtoupper(trim($name));
        $key = preg_replace('/[^A-Z0-9]+/', '', $key) ?? $key;
        return match ($key) {
            'RHEAA', 'RHEEA', 'RHEAAJEWELS' => 'Rheea Jewels',
            'SATAAR', 'SATTA', 'SATTAR', 'SAFWAN' => 'Sattar',
            'JGD', 'GR', 'JGDDIAMONDS' => 'JGD Diamonds',
            'UM', 'UTTAMMAL' => 'Uttam Mal',
            'BAMAL', 'BEMAL', 'SB', 'SHREEGOURANGO', 'SHREEGOURANGOGOLDWORKSHOP' => 'Shree Gourango',
            'RANJAN' => 'Ranjan',
            'OM' => 'OM',
            default => ucwords(strtolower(trim($name))),
        };
    }

    private function canonicalVendor(string $name): string
    {
        return ucwords(strtolower(trim($name)));
    }

    /** @return list<string> */
    private function extractValidatedArchive(string $zipPath, string $destination): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new RuntimeException('The uploaded file is not a readable ZIP archive.');
        }

        $fileCount = 0;
        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                $zip->close();
                throw new RuntimeException('The ZIP contains an unsafe file path.');
            }
            if (str_ends_with($name, '/')) {
                continue;
            }
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, ['xlsx', 'xls', 'pdf'], true)) {
                $zip->close();
                throw new RuntimeException('Unsupported file in ZIP: ' . basename($name));
            }
            $fileCount++;
            $totalSize += (int) ($stat['size'] ?? 0);
            if ($fileCount > 500 || $totalSize > 250 * 1024 * 1024) {
                $zip->close();
                throw new RuntimeException('The ZIP expands beyond the allowed import limit.');
            }
        }

        if (! $zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('The production archive could not be extracted.');
        }
        $zip->close();

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destination, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /** @return array<string,string> */
    private function resolveRequiredPaths(array $files): array
    {
        $byBasename = [];
        foreach ($files as $file) {
            $byBasename[basename($file)] = $file;
        }
        $resolved = [];
        foreach (self::REQUIRED_FILES as $required) {
            if (! isset($byBasename[$required])) {
                throw new RuntimeException('Required workbook is missing: ' . $required);
            }
            $resolved[$required] = $byBasename[$required];
        }
        return $resolved;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create the secure production import directory.');
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
