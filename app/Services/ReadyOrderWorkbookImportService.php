<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Imports the verified 2026-27 packing-list workbook as completed production orders.
 *
 * The workbook is treated only as source data. Every serial row becomes its own order,
 * while the sheet/group references are retained for audit and payment reconciliation.
 */
class ReadyOrderWorkbookImportService
{
    private const SOURCE_FILE = 'PL-2026-2027 order ready.xlsx';
    private const SOURCE_SHA256 = 'dfc98c19ab15b213cb3c5f2ca30ff2059555a126d5de98214c957625fa043138';

    private const EXPECTED = [
        'items' => 78,
        'image_placements' => 76,
        'gross_weight_gm' => 2089.262,
        'net_weight_gm' => 1853.994,
        // Each item is stored at the application's 0.001 gm precision before totals are added.
        'pure_weight_gm' => 1529.944,
        // PAN is a fancy diamond shape in this production workbook, not a colour stone.
        'diamond_weight_cts' => 273.150,
        'stone_weight_cts' => 903.190,
        'gold_amount' => 22473042.85,
        'labour_charges' => 780250.92,
        'total_value' => 40943147.83,
        'paid_labour' => 633386.44,
    ];

    /** @var array<string,string> */
    private const KARIGAR_BY_SHEET = [
        'GR' => 'JGD DIAMONDS',
        'RHEEA' => 'RHEEA JEWELS',
        'UTTAM MAL' => 'UTTAM MAL',
        'SHREE GOURANGO' => 'SHREE GOURANGO',
        'SAFWAN JEWELLERY' => 'SAFWAN JEWELLERY',
    ];

    /** @var array<string,array<int,string>> */
    private const SUBCATEGORY_BY_ROW = [
        'GR' => [
            4 => 'Jhumki', 10 => 'Ring', 19 => 'Jhumki', 25 => 'Haaram', 34 => 'Ring',
            41 => 'Ring', 48 => 'Ring', 52 => 'Ring', 56 => 'Stud Earrings', 66 => 'Earrings',
            76 => 'Stud Earrings',
        ],
        'RHEEA' => [
            4 => 'Bangle', 16 => 'Bangle', 24 => 'Bangle', 32 => 'Bracelet',
            40 => 'Bangle Service', 44 => 'Pendant Set', 54 => 'Bangle',
        ],
        'UTTAM MAL' => [
            4 => 'Haaram', 10 => 'Bangle', 18 => 'Bangle', 23 => 'Jhumki', 32 => 'Ring',
            36 => 'Ring', 40 => 'Ring', 49 => 'Bracelet', 52 => 'Jhumki', 59 => 'Chain',
            67 => 'Necklace', 73 => 'Jhumki', 83 => 'Ring', 86 => 'Stud Earrings', 94 => 'Jhumki',
            98 => 'Jhumki', 109 => 'Necklace', 118 => 'Waist Belt', 123 => 'Maang Tikka',
            128 => 'Jhumki', 133 => 'Choker', 138 => 'Haaram', 148 => 'Stud Earrings',
            152 => 'Bangle', 156 => 'Bracelet', 159 => 'Pendant', 168 => 'Ring',
            176 => 'Earrings', 181 => 'Maang Tikka', 186 => 'Waist Belt',
        ],
        'SHREE GOURANGO' => [
            4 => 'Ring', 7 => 'Ring', 11 => 'Ring', 15 => 'Bangle', 24 => 'Bangle', 32 => 'Chain',
            37 => 'Pendant', 40 => 'Haaram', 44 => 'Chain', 53 => 'Ring', 57 => 'Chain',
            66 => 'Pendant', 72 => 'Earrings', 77 => 'Necklace', 87 => 'Necklace', 96 => 'Chain',
            101 => 'Bangle', 109 => 'Jhumki', 120 => 'Ring', 124 => 'Bracelet', 128 => 'Chain',
            131 => 'Necklace', 140 => 'Stud Earrings',
        ],
        'SAFWAN JEWELLERY' => [
            4 => 'Earrings', 8 => 'Bracelet', 11 => 'Ring', 14 => 'Pendant Chain',
            21 => 'Bracelet', 26 => 'Necklace', 34 => 'Pendant',
        ],
    ];

    private PostingService $postingService;
    private KarigarMaterialAccountingService $karigarAccounting;
    private int $adminId = 0;
    private int $locationId = 0;
    private int $warehouseId = 0;
    private ?int $binId = null;

    /** @var array<string,int> */
    private array $karigarIds = [];

    /** @var array<string,int> */
    private array $purityIds = [];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->postingService = new PostingService($this->db);
        $this->karigarAccounting = new KarigarMaterialAccountingService($this->db);
    }

    /** @return array<string,mixed> */
    public function import(string $path): array
    {
        $this->assertSourceFile($path);
        $existing = $this->db->table('production_import_batches')
            ->where('source_sha256', self::SOURCE_SHA256)
            ->where('status', 'completed')
            ->get()->getRowArray();
        if ($existing && str_contains((string) ($existing['source_name'] ?? ''), 'completed packing list')) {
            return json_decode((string) ($existing['summary_json'] ?? '{}'), true) ?: [];
        }

        $this->resolveFoundation();
        $placements = $this->extractImages($path);
        $items = $this->parseWorkbook($path, $placements);
        $this->assertParsedData($items, $placements);

        $this->db->transException(true)->transStart();
        $now = date('Y-m-d H:i:s');
        $this->db->table('production_import_batches')->insert([
            'source_name' => self::SOURCE_FILE . ' - completed packing list orders',
            'source_sha256' => self::SOURCE_SHA256,
            'imported_by' => $this->adminId ?: null,
            'status' => 'processing',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $batchId = (int) $this->db->insertID();

        $adjustments = $this->postHistoricalMaterialAdjustments($items);
        $summary = [
            'orders' => 0,
            'ready_items' => 0,
            'photos' => 0,
            'designs_created' => 0,
            'repeat_design_orders' => 0,
            'receipts' => 0,
            'material_vouchers' => 0,
            'finished_inventory_items' => 0,
            'labour_bills' => 0,
            'labour_payments' => 0,
            'labour_total' => 0.0,
            'paid_total' => 0.0,
            'historical_material_adjustments' => $adjustments,
        ];

        foreach ($items as $item) {
            $result = $this->insertCompletedOrder($batchId, $item);
            foreach ($result as $key => $value) {
                if (isset($summary[$key]) && is_numeric($summary[$key])) {
                    $summary[$key] += $value;
                }
            }
        }

        $summary['labour_total'] = round((float) $summary['labour_total'], 2);
        $summary['paid_total'] = round((float) $summary['paid_total'], 2);
        $summary['workbook_sha256'] = self::SOURCE_SHA256;
        $summary['sheet_names'] = array_keys(self::KARIGAR_BY_SHEET);
        $summary['source_totals'] = self::EXPECTED;

        $this->assertPersistedData($batchId, $summary);
        $this->db->table('production_import_batches')->where('id', $batchId)->update([
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->transComplete();

        return $summary;
    }

    private function assertSourceFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Required packing-list workbook is missing: ' . self::SOURCE_FILE);
        }
        $hash = hash_file('sha256', $path);
        if ($hash !== self::SOURCE_SHA256) {
            throw new RuntimeException('Packing-list workbook checksum changed; verify the revised source before importing.');
        }
        foreach (['production_import_batches', 'production_ready_items', 'orders', 'order_receive_summaries', 'fg_items', 'design_masters'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Required table is missing: ' . $table);
            }
        }
    }

    private function resolveFoundation(): void
    {
        $admin = $this->db->table('admin_users')->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray()
            ?? $this->db->table('admin_users')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        $this->adminId = (int) ($admin['id'] ?? 0);

        $location = $this->db->table('inventory_locations')->select('id, name')->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $location) {
            throw new RuntimeException('An inventory location is required for completed-order receipts.');
        }
        $this->locationId = (int) $location['id'];
        $warehouse = $this->db->table('warehouses')
            ->select('id')->where('warehouse_code', 'MAIN')->get()->getRowArray()
            ?? $this->db->table('warehouses')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $warehouse) {
            throw new RuntimeException('A finished-jewellery warehouse is required.');
        }
        $this->warehouseId = (int) $warehouse['id'];
        $bin = $this->db->table('bins')->select('id')->where('warehouse_id', $this->warehouseId)->orderBy('id', 'ASC')->get()->getRowArray();
        $this->binId = $bin ? (int) $bin['id'] : null;

        foreach (self::KARIGAR_BY_SHEET as $sheet => $name) {
            $row = $this->db->table('karigars')->select('id, name')->where('UPPER(name)', strtoupper($name))->get()->getRowArray();
            if (! $row) {
                throw new RuntimeException('Updated packing-list sheet ' . $sheet . ' could not be mapped to karigar ' . $name . '.');
            }
            $this->karigarIds[$sheet] = (int) $row['id'];
        }
        foreach ($this->db->table('gold_purities')->select('id, purity_code')->get()->getResultArray() as $purity) {
            $this->purityIds[strtoupper((string) $purity['purity_code'])] = (int) $purity['id'];
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $placements
     * @return list<array<string,mixed>>
     */
    private function parseWorkbook(string $path, array $placements): array
    {
        $book = IOFactory::load($path);
        $items = [];
        $usedImages = [];

        foreach ($book->getWorksheetIterator() as $sheet) {
            $sheetName = trim($sheet->getTitle());
            if (! isset(self::KARIGAR_BY_SHEET[$sheetName])) {
                throw new RuntimeException('Unexpected packing-list sheet: ' . $sheetName);
            }
            $headerRows = [];
            for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                if (strtolower($this->text($sheet->getCell('B' . $row)->getFormattedValue())) === 'sr.no') {
                    $headerRows[] = $row;
                }
            }
            $headerRows[] = $sheet->getHighestDataRow() + 1;
            $previousGroupDate = null;

            for ($groupIndex = 0; $groupIndex < count($headerRows) - 1; $groupIndex++) {
                $headerRow = $headerRows[$groupIndex];
                $groupEnd = $headerRows[$groupIndex + 1] - 1;
                $totalRow = $groupEnd + 1;
                for ($row = $headerRow + 1; $row <= $groupEnd; $row++) {
                    if ($this->text($sheet->getCell('B' . $row)->getFormattedValue()) === ''
                        && $this->number($sheet->getCell('E' . $row)->getCalculatedValue()) !== 0.0) {
                        $totalRow = $row;
                    }
                }

                $starts = [];
                for ($row = $headerRow + 1; $row < $totalRow; $row++) {
                    $serial = $sheet->getCell('B' . $row)->getValue();
                    $meaningful = 0.0;
                    foreach (['E', 'K', 'L', 'M', 'N', 'O'] as $column) {
                        $meaningful += abs($this->number($sheet->getCell($column . $row)->getCalculatedValue()));
                    }
                    if (is_numeric($serial) && $meaningful > 0) {
                        $starts[] = $row;
                    }
                }

                $paid = false;
                $paymentDate = null;
                for ($row = $headerRow; $row <= $groupEnd; $row++) {
                    for ($column = 16; $column <= 19; $column++) {
                        $cell = $sheet->getCell([$column, $row]);
                        if (str_contains(strtoupper($this->text($cell->getFormattedValue())), 'PAID')) {
                            $paid = true;
                        }
                        $candidate = $this->dateValue($cell->getValue(), $cell->getFormattedValue());
                        if ($candidate !== null) {
                            $paymentDate = $candidate;
                        }
                    }
                }

                $groupDate = null;
                for ($row = $headerRow + 1; $row < $totalRow; $row++) {
                    $candidate = $this->dateValue(
                        $sheet->getCell('C' . $row)->getValue(),
                        $sheet->getCell('C' . $row)->getFormattedValue()
                    );
                    if ($candidate !== null) {
                        $groupDate = $candidate;
                        break;
                    }
                }
                $groupDate ??= $previousGroupDate;
                if ($groupDate === null) {
                    throw new RuntimeException('Ready date is missing for ' . $sheetName . ' group ' . ($groupIndex + 1) . '.');
                }
                $previousGroupDate = $groupDate;
                $groupKey = $sheetName . '-' . str_pad((string) ($groupIndex + 1), 3, '0', STR_PAD_LEFT);

                foreach ($starts as $index => $startRow) {
                    $endRow = min(($starts[$index + 1] ?? $totalRow) - 1, $totalRow - 1);
                    $item = $this->parseItem($sheet, $sheetName, $groupKey, $groupDate, $startRow, $endRow);
                    $image = $this->matchImage($placements[$sheetName] ?? [], $startRow, $endRow, $usedImages);
                    $item['image_path'] = $image['path'] ?? null;
                    $item['image_sha256'] = $image['sha256'] ?? null;
                    $item['payment_status'] = $paid ? 'Paid' : 'Pending';
                    $item['payment_date'] = $paid ? $paymentDate : null;
                    $items[] = $item;
                }
            }
        }
        $book->disconnectWorksheets();

        return $items;
    }

    /** @return array<string,mixed> */
    private function parseItem(Worksheet $sheet, string $sheetName, string $groupKey, string $groupDate, int $startRow, int $endRow): array
    {
        $purity = '';
        $components = [];
        $net = $pure = $goldAmount = $labour = $total = 0.0;
        $diamondCts = $diamondPcs = $stoneCts = 0.0;
        $itemDate = null;

        for ($row = $startRow; $row <= $endRow; $row++) {
            $candidate = $this->dateValue($sheet->getCell('C' . $row)->getValue(), $sheet->getCell('C' . $row)->getFormattedValue());
            $itemDate ??= $candidate;
            $purityCandidate = strtoupper($this->text($sheet->getCell('B' . $row)->getFormattedValue()));
            if ($purity === '' && preg_match('/(?:14|18|22|24)\s*K(?:T)?|SILVER/', $purityCandidate, $match) === 1) {
                $purity = strtoupper(str_replace([' ', 'KT'], ['', 'K'], $match[0]));
            }
            $net += $this->number($sheet->getCell('K' . $row)->getCalculatedValue());
            $pure += $this->number($sheet->getCell('L' . $row)->getCalculatedValue());
            $goldAmount += $this->number($sheet->getCell('M' . $row)->getCalculatedValue());
            $labour += $this->number($sheet->getCell('N' . $row)->getCalculatedValue());
            $total += $this->number($sheet->getCell('O' . $row)->getCalculatedValue());

            $name = $this->text($sheet->getCell('F' . $row)->getFormattedValue());
            $pcs = $this->number($sheet->getCell('G' . $row)->getCalculatedValue());
            $weight = $this->number($sheet->getCell('H' . $row)->getCalculatedValue());
            $rate = $this->number($sheet->getCell('I' . $row)->getCalculatedValue());
            $amount = $this->number($sheet->getCell('J' . $row)->getCalculatedValue());
            if ($name === '' && $pcs === 0.0 && $weight === 0.0 && $amount === 0.0) {
                continue;
            }
            $type = $this->isDiamond($name) ? 'diamond' : 'stone';
            if ($type === 'diamond') {
                $diamondCts += $weight;
                $diamondPcs += $pcs;
            } else {
                $stoneCts += $weight;
            }
            $components[] = [
                'type' => $type,
                'name' => $name ?: ucfirst($type),
                'pcs' => round($pcs, 3),
                'weight_cts' => round($weight, 3),
                'weight_gm' => round($weight * 0.2, 3),
                'rate' => round($rate, 2),
                'amount' => round($amount, 2),
            ];
        }

        $description = $this->text($sheet->getCell('C' . $startRow)->getFormattedValue());
        if ($this->dateValue($sheet->getCell('C' . $startRow)->getValue(), $description) !== null) {
            $description = '';
        }
        $reference = $this->text($sheet->getCell('D' . $startRow)->getFormattedValue());
        $subcategory = self::SUBCATEGORY_BY_ROW[$sheetName][$startRow] ?? ($description ?: 'Jewellery');
        $category = $purity === 'SILVER' ? 'Silver' : ($diamondCts > 0 ? 'Diamond' : 'Gold');

        return [
            'karigar_id' => $this->karigarIds[$sheetName],
            'karigar_name' => self::KARIGAR_BY_SHEET[$sheetName],
            'ready_group' => $groupKey,
            'ready_date' => $itemDate ?? $groupDate,
            'serial_no' => $this->text($sheet->getCell('B' . $startRow)->getFormattedValue()),
            'design_name' => $description ?: $subcategory,
            'category' => $category,
            'subcategory' => $subcategory,
            'reference_no' => $reference,
            'purity_label' => $purity,
            'gross_weight_gm' => round($this->number($sheet->getCell('E' . $startRow)->getCalculatedValue()), 3),
            'net_weight_gm' => round($net, 3),
            'pure_weight_gm' => round($pure, 3),
            'diamond_pcs' => round($diamondPcs, 3),
            'diamond_weight_cts' => round($diamondCts, 3),
            'stone_weight_cts' => round($stoneCts, 3),
            'gold_amount' => round($goldAmount, 2),
            'labour_charges' => round($labour, 2),
            'total_value' => round($total, 2),
            'components' => $components,
            'source_sheet' => $sheetName,
            'source_row' => $startRow,
        ];
    }

    private function isDiamond(string $name): bool
    {
        return preg_match('/(?:VVS|VS|SI|IJ|EF|GH|CVD|LAB[ -]?GROWN|DIAMOND|DIA|POLKI|BUG|\bRC\b|\bMQ\b|OVAL|\bPAN\b)/i', $name) === 1;
    }

    /** @param list<array<string,mixed>> $items @param array<string,list<array<string,mixed>>> $placements */
    private function assertParsedData(array $items, array $placements): void
    {
        $actual = [
            'items' => count($items),
            'image_placements' => array_sum(array_map('count', $placements)),
            'gross_weight_gm' => 0.0,
            'net_weight_gm' => 0.0,
            'pure_weight_gm' => 0.0,
            'diamond_weight_cts' => 0.0,
            'stone_weight_cts' => 0.0,
            'gold_amount' => 0.0,
            'labour_charges' => 0.0,
            'total_value' => 0.0,
            'paid_labour' => 0.0,
        ];
        foreach ($items as $item) {
            foreach (['gross_weight_gm', 'net_weight_gm', 'pure_weight_gm', 'diamond_weight_cts', 'stone_weight_cts', 'gold_amount', 'labour_charges', 'total_value'] as $field) {
                $actual[$field] += (float) $item[$field];
            }
            if ($item['payment_status'] === 'Paid') {
                $actual['paid_labour'] += (float) $item['labour_charges'];
            }
        }
        foreach ($actual as $key => $value) {
            $precision = in_array($key, ['gold_amount', 'labour_charges', 'total_value', 'paid_labour'], true) ? 2 : 3;
            $actual[$key] = is_float($value) ? round($value, $precision) : $value;
            if (abs((float) $actual[$key] - (float) self::EXPECTED[$key]) > ($precision === 2 ? 0.01 : 0.001)) {
                throw new RuntimeException(sprintf('Packing-list reconciliation failed for %s: expected %s, found %s.', $key, self::EXPECTED[$key], $actual[$key]));
            }
        }
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function postHistoricalMaterialAdjustments(array $items): array
    {
        $required = [];
        foreach ($items as $item) {
            $karigarId = (int) $item['karigar_id'];
            $required[$karigarId]['gold'] = ($required[$karigarId]['gold'] ?? 0.0) + (float) $item['pure_weight_gm'];
            $required[$karigarId]['diamond'] = ($required[$karigarId]['diamond'] ?? 0.0) + (float) $item['diamond_weight_cts'];
            $date = (string) $item['ready_date'];
            if (! isset($required[$karigarId]['date']) || $date < $required[$karigarId]['date']) {
                $required[$karigarId]['date'] = $date;
            }
        }

        $sourceAccountId = $this->postingService->ensureAccount(
            'SOURCE',
            'READY-ORDER-HISTORICAL-SOURCE',
            'Historical Ready Order Material Source'
        );
        $posted = [];
        foreach ($required as $karigarId => $totals) {
            $karigar = $this->db->table('karigars')->select('name')->where('id', $karigarId)->get()->getRowArray();
            $karigarAccountId = $this->postingService->ensureAccount(
                'KARIGAR',
                'KARIGAR-' . $karigarId,
                'Karigar - ' . (string) ($karigar['name'] ?? $karigarId),
                'karigars',
                (int) $karigarId
            );
            $goldBalance = $this->materialBalance($karigarAccountId, 'GOLD', 'GOLD-FINE', 'fine_gold_qty');
            $diamondBalance = $this->materialBalance($karigarAccountId, 'DIAMOND', 'DIAMOND-POOL', 'qty_cts');
            $goldShort = max(0.0, round((float) $totals['gold'] - $goldBalance, 3));
            $diamondShort = max(0.0, round((float) $totals['diamond'] - $diamondBalance, 3));
            $lines = [];
            if ($goldShort > 0) {
                $lines[] = [
                    'item_type' => 'GOLD', 'item_key' => 'GOLD-FINE', 'material_name' => 'Pure Gold',
                    'qty_pcs' => 0, 'qty_cts' => 0, 'qty_weight' => $goldShort, 'fine_gold' => $goldShort,
                    'remarks' => 'Pre-system gold issue required to settle verified completed packing-list items',
                ];
            }
            if ($diamondShort > 0) {
                $lines[] = [
                    'item_type' => 'DIAMOND', 'item_key' => 'DIAMOND-POOL', 'material_name' => 'Diamond',
                    'qty_pcs' => 0, 'qty_cts' => $diamondShort, 'qty_weight' => 0, 'fine_gold' => 0,
                    'remarks' => 'Pre-system diamond issue required to settle verified completed packing-list items',
                ];
            }
            if ($lines === []) {
                continue;
            }
            $voucherNo = 'PL-HIST-' . $karigarId;
            $result = $this->postingService->postVoucher([
                'voucher_no' => $voucherNo,
                'voucher_type' => 'HISTORICAL_MATERIAL_ISSUE',
                'voucher_date' => (string) $totals['date'],
                'party_id' => (int) $karigarId,
                'debit_account_id' => $karigarAccountId,
                'credit_account_id' => $sourceAccountId,
                'skip_inventory_movement' => true,
                'remarks' => 'Audited first-time adjustment for completed rows in ' . self::SOURCE_FILE . '; future receipts require normal issuement.',
                'created_by' => $this->adminId,
                'created_ip' => '127.0.0.1',
            ], $lines);
            $posted[] = [
                'karigar_id' => (int) $karigarId,
                'karigar_name' => (string) ($karigar['name'] ?? ''),
                'gold_gm' => $goldShort,
                'diamond_cts' => $diamondShort,
                'voucher_id' => (int) $result['voucher_id'],
                'voucher_no' => $voucherNo,
            ];
        }

        return $posted;
    }

    private function materialBalance(int $accountId, string $type, string $key, string $field): float
    {
        $row = $this->db->table('account_balances')->select($field)
            ->where('account_id', $accountId)->where('item_type', $type)->where('item_key', $key)
            ->get()->getRowArray();
        return round((float) ($row[$field] ?? 0), 3);
    }

    /** @param array<string,mixed> $item @return array<string,float|int> */
    private function insertCompletedOrder(int $batchId, array $item): array
    {
        $date = (string) $item['ready_date'];
        $createdAt = $date . ' 18:00:00';
        $now = date('Y-m-d H:i:s');
        $sheetCode = preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $item['source_sheet'])) ?: 'PL';
        $orderNo = substr(sprintf('PL26-%s-G%02d-R%d', $sheetCode, (int) substr((string) $item['ready_group'], -3), (int) $item['source_row']), 0, 40);
        if ($this->db->table('orders')->where('order_no', $orderNo)->countAllResults() > 0) {
            throw new RuntimeException('Packing-list order already exists without its completed import marker: ' . $orderNo);
        }

        $this->db->table('orders')->insert([
            'order_no' => $orderNo,
            'order_type' => (float) $item['gross_weight_gm'] > 0 ? 'Manufacturing' : 'Service',
            'order_from' => 'Packing List 2026-27',
            'customer_id' => null,
            'assigned_karigar_id' => (int) $item['karigar_id'],
            'assigned_at' => $date . ' 09:00:00',
            'status' => 'Completed',
            'priority' => 'Medium',
            'due_date' => $date,
            'order_notes' => sprintf(
                'Imported completed ready order from %s, sheet %s row %d, group %s.%s',
                self::SOURCE_FILE,
                $item['source_sheet'],
                $item['source_row'],
                $item['ready_group'],
                $item['reference_no'] !== '' ? ' Source reference: ' . $item['reference_no'] . '.' : ''
            ),
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => $now,
        ]);
        $orderId = (int) $this->db->insertID();

        $purityId = $this->purityIds[strtoupper((string) $item['purity_label'])] ?? null;
        $this->db->table('order_items')->insert([
            'order_id' => $orderId,
            'design_id' => null,
            'gold_purity_id' => $purityId,
            'item_description' => $item['subcategory'] . ' - ' . $item['design_name'],
            'qty' => 1,
            'gold_required_gm' => (float) $item['net_weight_gm'],
            'diamond_required_cts' => (float) $item['diamond_weight_cts'],
            'item_status' => 'Completed',
            'created_at' => $createdAt,
            'updated_at' => $now,
        ]);
        $orderItemId = (int) $this->db->insertID();

        $this->db->table('job_cards')->insert([
            'job_card_no' => substr('JC-' . $orderNo, 0, 40),
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'status' => 'Completed',
            'priority' => 'Medium',
            'due_date' => $date,
            'qc_status' => 'Passed',
            'rework_count' => 0,
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => $now,
        ]);
        $jobCardId = (int) $this->db->insertID();

        $movementId = $this->insertReceipt($orderId, $item, $purityId, $createdAt);
        $accountVoucherId = null;
        if ((float) $item['pure_weight_gm'] > 0 || (float) $item['diamond_weight_cts'] > 0) {
            $accountVoucherId = $this->karigarAccounting->postFinishedJewelleryReceipt(
                $orderId,
                (int) $item['karigar_id'],
                $this->locationId,
                (float) $item['pure_weight_gm'],
                0,
                (float) $item['diamond_weight_cts'],
                'Completed jewellery receipt from ' . self::SOURCE_FILE . ' ' . $item['source_sheet'] . ':' . $item['source_row'],
                $this->adminId,
                $date
            );
        }

        $this->insertReceiveSummaryAndDetails($movementId, $accountVoucherId, $orderId, $item, $createdAt);
        $labourResult = $this->insertLabourAccounting($movementId, $orderId, $item);

        $this->db->table('production_ready_items')->insert([
            'batch_id' => $batchId,
            'karigar_id' => (int) $item['karigar_id'],
            'order_id' => $orderId,
            'fg_item_id' => null,
            'ready_group' => $item['ready_group'],
            'ready_date' => $date,
            'serial_no' => $item['serial_no'],
            'design_name' => $item['design_name'],
            'reference_no' => $item['reference_no'] ?: $orderNo,
            'purity_label' => $item['purity_label'] ?: null,
            'gross_weight_gm' => (float) $item['gross_weight_gm'],
            'net_weight_gm' => (float) $item['net_weight_gm'],
            'pure_weight_gm' => (float) $item['pure_weight_gm'],
            'gold_amount' => (float) $item['gold_amount'],
            'labour_charges' => (float) $item['labour_charges'],
            'total_value' => (float) $item['total_value'],
            'stones_json' => json_encode($item['components'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'image_path' => $item['image_path'],
            'status_note' => $item['payment_status'] === 'Paid' ? 'PAID' : 'Ready and completed',
            'payment_status' => $item['payment_status'],
            'payment_date' => $item['payment_date'],
            'source_sheet' => $item['source_sheet'],
            'source_row' => (int) $item['source_row'],
            'created_at' => $createdAt,
        ]);
        $readyItemId = (int) $this->db->insertID();

        if ($item['image_path']) {
            $this->db->table('order_attachments')->insert([
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'file_type' => 'finish_photo',
                'file_name' => basename((string) $item['image_path']),
                'file_path' => $item['image_path'],
                'uploaded_by' => $this->adminId ?: null,
                'created_at' => $createdAt,
                'updated_at' => $now,
            ]);
        }

        $design = $this->createOrReuseDesign($orderId, $orderItemId, $item);
        $this->db->table('order_items')->where('id', $orderItemId)->update([
            'design_id' => $design['id'],
            'updated_at' => $now,
        ]);

        $fgItemId = $this->insertFinishedInventory($readyItemId, $orderId, $jobCardId, $item, $createdAt);
        $this->db->table('production_ready_items')->where('id', $readyItemId)->update(['fg_item_id' => $fgItemId]);
        $this->db->table('order_status_history')->insert([
            'order_id' => $orderId,
            'from_status' => 'In Production',
            'to_status' => 'Completed',
            'remarks' => 'Ready item received, karigar material settled and moved to jewellery inventory from verified packing list.',
            'changed_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => $now,
        ]);
        $this->db->table('production_source_rows')->insert([
            'batch_id' => $batchId,
            'source_file' => self::SOURCE_FILE,
            'sheet_name' => $item['source_sheet'],
            'row_number' => (int) $item['source_row'],
            'record_type' => 'completed_ready_order',
            'record_key' => $orderNo,
            'data_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $createdAt,
        ]);

        return [
            'orders' => 1,
            'ready_items' => 1,
            'photos' => $item['image_path'] ? 1 : 0,
            'designs_created' => $design['created'] ? 1 : 0,
            'repeat_design_orders' => $design['created'] ? 0 : 1,
            'receipts' => 1,
            'material_vouchers' => $accountVoucherId ? 1 : 0,
            'finished_inventory_items' => 1,
            'labour_bills' => 1,
            'labour_payments' => $labourResult['paid'] ? 1 : 0,
            'labour_total' => (float) $item['labour_charges'],
            'paid_total' => $labourResult['paid'] ? (float) $item['labour_charges'] : 0.0,
        ];
    }

    private function insertReceipt(int $orderId, array $item, ?int $purityId, string $createdAt): int
    {
        $diamondGm = round((float) $item['diamond_weight_cts'] * 0.2, 3);
        $stoneGm = round((float) $item['stone_weight_cts'] * 0.2, 3);
        $otherWeight = max(0.0, round((float) $item['gross_weight_gm'] - (float) $item['net_weight_gm'] - $diamondGm - $stoneGm, 3));
        $this->db->table('order_material_movements')->insert([
            'order_id' => $orderId,
            'movement_type' => 'receive',
            'gold_gm' => (float) $item['net_weight_gm'],
            'diamond_cts' => (float) $item['diamond_weight_cts'],
            'gold_purity_id' => $purityId,
            'karigar_id' => (int) $item['karigar_id'],
            'location_id' => $this->locationId,
            'gross_weight_gm' => (float) $item['gross_weight_gm'],
            'other_weight_gm' => $otherWeight,
            'diamond_weight_gm' => $diamondGm,
            'net_gold_weight_gm' => (float) $item['net_weight_gm'],
            'pure_gold_weight_gm' => (float) $item['pure_weight_gm'],
            'notes' => 'Full ready-item receipt from ' . self::SOURCE_FILE . ' ' . $item['source_sheet'] . ':' . $item['source_row'],
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function insertReceiveSummaryAndDetails(int $movementId, ?int $voucherId, int $orderId, array $item, string $createdAt): void
    {
        $diamondAmount = $stoneAmount = 0.0;
        foreach ($item['components'] as $component) {
            if ($component['type'] === 'diamond') {
                $diamondAmount += (float) $component['amount'];
            } else {
                $stoneAmount += (float) $component['amount'];
            }
            $this->db->table('order_receive_details')->insert([
                'movement_id' => $movementId,
                'order_id' => $orderId,
                'component_type' => $component['type'],
                'component_name' => $component['name'],
                'pcs' => $component['pcs'],
                'weight_cts' => $component['weight_cts'],
                'weight_gm' => $component['weight_gm'],
                'rate' => $component['rate'],
                'line_total' => $component['amount'],
                'created_by' => $this->adminId ?: null,
                'created_at' => $createdAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $labourRate = (float) $item['net_weight_gm'] > 0
            ? round((float) $item['labour_charges'] / (float) $item['net_weight_gm'], 2)
            : 0.0;
        $this->db->table('order_receive_summaries')->insert([
            'movement_id' => $movementId,
            'account_voucher_id' => $voucherId,
            'order_id' => $orderId,
            'gross_weight_gm' => (float) $item['gross_weight_gm'],
            'net_gold_weight_gm' => (float) $item['net_weight_gm'],
            'pure_gold_weight_gm' => (float) $item['pure_weight_gm'],
            'diamond_weight_cts' => (float) $item['diamond_weight_cts'],
            'diamond_weight_gm' => round((float) $item['diamond_weight_cts'] * 0.2, 3),
            'stone_weight_cts' => (float) $item['stone_weight_cts'],
            'stone_weight_gm' => round((float) $item['stone_weight_cts'] * 0.2, 3),
            'other_weight_gm' => 0,
            'diamond_amount' => round($diamondAmount, 2),
            'stone_amount' => round($stoneAmount, 2),
            'other_amount' => 0,
            'gold_amount' => (float) $item['gold_amount'],
            'labour_rate_per_gm' => $labourRate,
            'labour_amount' => (float) $item['labour_charges'],
            'total_valuation' => (float) $item['total_value'],
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{paid:bool} */
    private function insertLabourAccounting(int $movementId, int $orderId, array $item): array
    {
        $date = (string) $item['ready_date'];
        $amount = round((float) $item['labour_charges'], 2);
        $billNo = substr(sprintf('PL-LAB-%s-R%d', preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $item['source_sheet'])), $item['source_row']), 0, 40);
        $paid = $item['payment_status'] === 'Paid' && $amount > 0;
        $this->db->table('labour_bills')->insert([
            'bill_no' => $billNo,
            'bill_date' => $date,
            'order_id' => $orderId,
            'receive_movement_id' => $movementId,
            'karigar_id' => (int) $item['karigar_id'],
            'gold_weight_gm' => (float) $item['net_weight_gm'],
            'rate_per_gm' => (float) $item['net_weight_gm'] > 0 ? round($amount / (float) $item['net_weight_gm'], 2) : 0,
            'labour_amount' => $amount,
            'other_amount' => 0,
            'total_amount' => $amount,
            'payment_status' => $paid ? 'Paid' : 'Pending',
            'notes' => 'Item-wise labour from ' . self::SOURCE_FILE . ' ' . $item['source_sheet'] . ':' . $item['source_row'],
            'created_by' => $this->adminId ?: null,
            'created_at' => $date . ' 18:05:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $billId = (int) $this->db->insertID();
        if ($amount > 0) {
            $this->insertKarigarLedger((int) $item['karigar_id'], $orderId, 'charge', $amount, $billNo, 'Packing-list item labour charge', $date);
        }
        if (! $paid) {
            return ['paid' => false];
        }

        $paymentDate = (string) (($item['payment_date'] ?? '') ?: $date);
        $note = $item['payment_date']
            ? 'PAID marker and adjacent payment date imported from packing list.'
            : 'PAID marker imported from packing list; no adjacent date was supplied, so ready date is used.';
        $this->db->table('labour_bill_payments')->insert([
            'labour_bill_id' => $billId,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'reference_no' => $billNo,
            'notes' => $note,
            'created_by' => $this->adminId ?: null,
            'created_at' => $paymentDate . ' 18:10:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('account_payments')->insert([
            'payment_no' => substr('PL-PAY-' . $billId, 0, 60),
            'payment_date' => $paymentDate,
            'party_type' => 'karigar',
            'karigar_id' => (int) $item['karigar_id'],
            'amount' => $amount,
            'payment_mode' => 'Source Record',
            'reference_no' => $billNo,
            'bill_type' => 'labour',
            'bill_source_type' => 'labour_bill',
            'bill_source_id' => $billId,
            'labour_bill_id' => $billId,
            'notes' => $note,
            'created_by' => $this->adminId ?: null,
            'created_at' => $paymentDate . ' 18:10:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insertKarigarLedger((int) $item['karigar_id'], $orderId, 'payment', $amount, $billNo, $note, $paymentDate);
        return ['paid' => true];
    }

    private function insertKarigarLedger(int $karigarId, int $orderId, string $type, float $amount, string $reference, string $notes, string $date): void
    {
        $this->db->table('karigar_payment_ledgers')->insert([
            'karigar_id' => $karigarId,
            'order_id' => $orderId,
            'entry_type' => $type,
            'amount' => $amount,
            'reference_no' => $reference,
            'notes' => $notes,
            'created_by' => $this->adminId ?: null,
            'created_at' => $date . ' 18:10:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{id:int,created:bool} */
    private function createOrReuseDesign(int $orderId, int $orderItemId, array $item): array
    {
        $hash = trim((string) ($item['image_sha256'] ?? ''));
        if ($hash !== '') {
            $existing = $this->db->table('design_masters')->select('id')->where('source_image_sha256', $hash)->get()->getRowArray();
            if ($existing) {
                return ['id' => (int) $existing['id'], 'created' => false];
            }
        }
        $sheetCode = preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $item['source_sheet'])) ?: 'PL';
        $code = substr(sprintf('DS26-%s-R%d', $sheetCode, $item['source_row']), 0, 40);
        $this->db->table('design_masters')->insert([
            'design_code' => $code,
            'name' => $item['subcategory'] . ' - ' . $item['design_name'],
            'category' => $item['category'],
            'subcategory' => $item['subcategory'],
            'image_path' => $item['image_path'],
            'source_order_id' => $orderId,
            'source_order_item_id' => $orderItemId,
            'source_karigar_id' => (int) $item['karigar_id'],
            'purity_label' => $item['purity_label'] ?: null,
            'gross_weight_gm' => (float) $item['gross_weight_gm'],
            'net_gold_weight_gm' => (float) $item['net_weight_gm'],
            'pure_gold_weight_gm' => (float) $item['pure_weight_gm'],
            'diamond_weight_cts' => (float) $item['diamond_weight_cts'],
            'stone_weight_cts' => (float) $item['stone_weight_cts'],
            'studded_details_json' => json_encode($item['components'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_image_sha256' => $hash ?: null,
            'source_type' => 'completed_order',
            'is_active' => 1,
            'created_at' => $item['ready_date'] . ' 18:15:00',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['id' => (int) $this->db->insertID(), 'created' => true];
    }

    private function insertFinishedInventory(int $readyItemId, int $orderId, int $jobCardId, array $item, string $createdAt): int
    {
        $this->db->table('fg_items')->insert([
            'tag_no' => substr(sprintf('FG-PL26-%s-R%d', preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $item['source_sheet'])), $item['source_row']), 0, 80),
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
            'production_ready_item_id' => $readyItemId,
            'design_name' => $item['subcategory'] . ' - ' . $item['design_name'],
            'purity_label' => $item['purity_label'] ?: null,
            'qty' => 1,
            'gross_wt' => (float) $item['gross_weight_gm'],
            'net_gold_wt' => (float) $item['net_weight_gm'],
            'diamond_cts' => (float) $item['diamond_weight_cts'],
            'stone_wt' => (float) $item['stone_weight_cts'],
            'studded_details_json' => json_encode($item['components'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_image_path' => $item['image_path'],
            'status' => 'AVAILABLE',
            'warehouse_id' => $this->warehouseId,
            'bin_id' => $this->binId,
            'showroom_stock_status' => 'FG_STORE',
            'inventory_remarks' => 'Completed ready order imported with full studded, karigar, labour and source-row details.',
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $fgId = (int) $this->db->insertID();
        $this->db->table('showroom_fg_movements')->insert([
            'fg_item_id' => $fgId,
            'movement_type' => 'ORDER_COMPLETED_TO_FG',
            'reference_type' => 'orders',
            'reference_id' => $orderId,
            'remarks' => 'Ready item received from karigar and added to jewellery inventory.',
            'created_by' => $this->adminId ?: null,
            'created_at' => $createdAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $fgId;
    }

    private function assertPersistedData(int $batchId, array $summary): void
    {
        foreach (['orders', 'ready_items', 'receipts', 'finished_inventory_items', 'labour_bills'] as $field) {
            if ((int) $summary[$field] !== self::EXPECTED['items']) {
                throw new RuntimeException('Completed packing-list import did not create all ' . $field . '.');
            }
        }
        if ((int) $summary['photos'] !== self::EXPECTED['image_placements']) {
            throw new RuntimeException('Completed packing-list import did not link all 76 photo placements.');
        }
        if (abs((float) $summary['labour_total'] - self::EXPECTED['labour_charges']) > 0.01
            || abs((float) $summary['paid_total'] - self::EXPECTED['paid_labour']) > 0.01) {
            throw new RuntimeException('Completed packing-list labour/payment totals do not reconcile.');
        }
        $readyCount = $this->db->table('production_ready_items')->where('batch_id', $batchId)->countAllResults();
        if ($readyCount !== self::EXPECTED['items']) {
            throw new RuntimeException('Production ready-item batch count does not reconcile.');
        }
        $negative = $this->db->table('account_balances ab')
            ->select('ab.id')
            ->join('accounts a', 'a.id = ab.account_id')
            ->where('a.account_type', 'KARIGAR')
            ->groupStart()
                ->where('ab.qty_cts <', -0.001)
                ->orWhere('ab.fine_gold_qty <', -0.001)
            ->groupEnd()
            ->countAllResults();
        if ($negative > 0) {
            throw new RuntimeException('A karigar material account became negative during completed-order import.');
        }
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function extractImages(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Packing-list workbook could not be opened for photo extraction.');
        }
        $directory = FCPATH . 'uploads/ready-orders/2026-27';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Ready-order photo directory could not be created.');
        }
        $book = IOFactory::load($path);
        $sheetNames = array_map(static fn(Worksheet $sheet): string => $sheet->getTitle(), $book->getAllSheets());
        $book->disconnectWorksheets();
        $result = [];

        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $sheetRelsRaw = $zip->getFromName('xl/worksheets/_rels/sheet' . ($sheetIndex + 1) . '.xml.rels');
            if (! is_string($sheetRelsRaw)) {
                continue;
            }
            $sheetRels = @simplexml_load_string($sheetRelsRaw);
            $drawingTarget = '';
            if ($sheetRels !== false) {
                foreach ($sheetRels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
                    $attributes = $relationship->attributes();
                    if (str_ends_with((string) ($attributes['Type'] ?? ''), '/drawing')) {
                        $drawingTarget = (string) ($attributes['Target'] ?? '');
                        break;
                    }
                }
            }
            if ($drawingTarget === '') {
                continue;
            }
            $drawingName = $this->normalizeZipPath('xl/worksheets/' . $drawingTarget);
            $drawingRaw = $zip->getFromName($drawingName);
            $drawingRelsRaw = $zip->getFromName(dirname($drawingName) . '/_rels/' . basename($drawingName) . '.rels');
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
            foreach ($drawing->xpath('//xdr:twoCellAnchor | //xdr:oneCellAnchor') ?: [] as $anchorIndex => $anchor) {
                $anchor->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
                $anchor->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $rows = $anchor->xpath('./xdr:from/xdr:row') ?: [];
                $endRows = $anchor->xpath('./xdr:to/xdr:row') ?: [];
                $blips = $anchor->xpath('.//a:blip') ?: [];
                if ($rows === [] || $blips === []) {
                    continue;
                }
                $attributes = $blips[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $target = $relationships[(string) ($attributes['embed'] ?? '')] ?? '';
                $mediaName = $target !== '' ? $this->normalizeZipPath(dirname($drawingName) . '/' . $target) : '';
                $contents = $mediaName !== '' ? $zip->getFromName($mediaName) : false;
                $extension = strtolower(pathinfo($mediaName, PATHINFO_EXTENSION));
                if (! is_string($contents) || $contents === '' || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }
                $startRow = (int) $rows[0] + 1;
                $endRow = $endRows === [] ? $startRow : ((int) $endRows[0] + 1);
                $safeSheet = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $sheetName) ?? '', '-'));
                $filename = $safeSheet . '-r' . $startRow . '-p' . ($anchorIndex + 1) . '.' . $extension;
                $destination = $directory . '/' . $filename;
                if (file_put_contents($destination, $contents) === false) {
                    throw new RuntimeException('Ready-order photo could not be saved: ' . $filename);
                }
                $result[$sheetName][] = [
                    'key' => $sheetName . ':' . ($anchorIndex + 1),
                    'start_row' => $startRow,
                    'end_row' => max($startRow, $endRow),
                    'path' => 'uploads/ready-orders/2026-27/' . $filename,
                    'sha256' => hash('sha256', $contents),
                ];
            }
        }
        $zip->close();
        return $result;
    }

    /** @param list<array<string,mixed>> $placements @param array<string,bool> $used */
    private function matchImage(array $placements, int $startRow, int $endRow, array &$used): ?array
    {
        $matches = [];
        foreach ($placements as $placement) {
            if (isset($used[$placement['key']]) || $placement['end_row'] < $startRow || $placement['start_row'] > $endRow) {
                continue;
            }
            $inside = $placement['start_row'] >= $startRow && $placement['start_row'] <= $endRow;
            $matches[] = [
                'row' => $placement,
                'rank' => $placement['start_row'] === $startRow ? 0 : ($inside ? 1 : 2),
                'distance' => abs($placement['start_row'] - $startRow),
            ];
        }
        if ($matches === []) {
            return null;
        }
        usort($matches, static fn(array $left, array $right): int => [$left['rank'], $left['distance']] <=> [$right['rank'], $right['distance']]);
        $selected = $matches[0]['row'];
        $used[$selected['key']] = true;
        return $selected;
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

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = str_replace([',', '₹', ' '], '', trim((string) $value));
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }

    private function dateValue(mixed $raw, mixed $formatted): ?string
    {
        if (is_numeric($raw) && (float) $raw > 20000) {
            try {
                return SpreadsheetDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (Throwable) {
                // Try source text formats below.
            }
        }
        $value = $this->text($formatted);
        foreach (['d.m.y', 'd.m.Y', 'd-m-y', 'd-m-Y', 'd/m/y', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }
}
