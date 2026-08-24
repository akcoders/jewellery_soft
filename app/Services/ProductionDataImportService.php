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
    private string $adminPasswordHash = '';

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
        $this->parseReadyWorkbook($paths['PL-2026-2027 order ready.xlsx'], $parsed);
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

    private function parseReadyWorkbook(string $path, array &$parsed): void
    {
        $spreadsheet = IOFactory::load($path);
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
                    'status_note' => implode(', ', array_keys($statusParts)),
                    'source_sheet' => $sheet->getTitle(),
                    'source_row' => $row,
                ];
            }
        }
        $spreadsheet->disconnectWorksheets();
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
            $parsed['documents'][] = [
                'category' => $category,
                'vendor_name' => $this->canonicalVendor($vendorName),
                'original_name' => $filename,
                'source_path' => $relative,
                'stored_path' => ltrim(str_replace('\\', '/', substr($path, strlen(WRITEPATH))), '/'),
                'document_date' => $this->dateFromFilename($filename),
                'invoice_no' => null,
                'payment_status' => str_contains(strtoupper($filename), 'PAID') ? 'Paid' : 'Unknown',
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
            $this->insertReadyItems($batchId, $parsed['ready_items']);
            $this->insertDocuments($batchId, $parsed['documents']);

            $summary = [
                'source_rows' => count($parsed['source_rows']),
                'purchase_documents' => count($parsed['documents']),
                'gold_movements' => count($parsed['gold_movements']),
                'diamond_movements' => count($parsed['diamond_movements']),
                'diamond_issue_lines' => count($parsed['diamond_issue_lines']),
                'ready_items' => count($parsed['ready_items']),
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
        $this->db->table('bins')->insert([
            'warehouse_id' => $warehouseId,
            'bin_code' => 'MAIN',
            'name' => 'Main Bin',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
                continue;
            }

            $orderId = $this->ensureProductionOrder(
                (string) $movement['reference_no'],
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
            } else {
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

            $key = $movement['source_sheet'] . ':' . $movement['source_row'];
            if ($movement['movement_type'] === 'purchase') {
                $purchases[$key]['header'] = $movement;
                $purchases[$key]['lines'][] = ['item_id' => $itemId, 'carat' => $movement['received_cts']];
            } elseif ($movement['movement_type'] === 'issue' && (float) $movement['issued_cts'] !== 0.0) {
                $issues[$key]['header'] = $movement;
                $issues[$key]['lines'][] = ['item_id' => $itemId, 'carat' => $movement['issued_cts']];
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

    private function insertReadyItems(int $batchId, array $items): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($items as $item) {
            $this->db->table('production_ready_items')->insert([
                'batch_id' => $batchId,
                'karigar_id' => $this->ensureKarigar((string) $item['karigar_name']),
                'ready_group' => $item['ready_group'],
                'ready_date' => $item['ready_date'],
                'serial_no' => $item['serial_no'] ?: null,
                'design_name' => $item['design_name'] ?: null,
                'reference_no' => $item['reference_no'] ?: null,
                'purity_label' => $item['purity_label'] ?: null,
                'gross_weight_gm' => $item['gross_weight_gm'],
                'net_weight_gm' => $item['net_weight_gm'],
                'pure_weight_gm' => $item['pure_weight_gm'],
                'gold_amount' => $item['gold_amount'],
                'labour_charges' => $item['labour_charges'],
                'total_value' => $item['total_value'],
                'stones_json' => $item['stones_json'],
                'status_note' => $item['status_note'] ?: null,
                'source_sheet' => $item['source_sheet'],
                'source_row' => $item['source_row'],
                'created_at' => $now,
            ]);
        }
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
