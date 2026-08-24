<?php

namespace App\Database\Migrations;

use App\Services\PostingService;
use CodeIgniter\Database\Migration;
use RuntimeException;

class ImportBabuGoldIssuements extends Migration
{
    private const SOURCE_FILE = 'BABU GOLD -26-27.xlsx';
    private const EXPECTED_COUNT = 31;
    private const EXPECTED_WEIGHT = 1570.459;
    private const EXPECTED_PURCHASES = 18;
    private const EXPECTED_PURCHASE_LINES = 19;
    private const EXPECTED_PURCHASE_WEIGHT = 1669.942;
    private const FIRST_OPENING_WEIGHT = 215.721;
    private const OPENING_VOUCHER = 'OPENING';

    /**
     * Complete, referenced rows from columns G:J of the Issuement section.
     *
     * SMGAJ/26-27/007 is not present in the workbook and is intentionally not
     * synthesized. The incomplete SANTOSH HYD row has neither a date nor a
     * reference number and is intentionally excluded. Only the first April
     * opening (215.721 gm) is posted; May, June and July carry-forward openings
     * are deliberately ignored.
     *
     * @var list<array{row:int,date:string,weight:float,karigar:string,source_name:string,voucher:string}>
     */
    private const ISSUEMENTS = [
        ['row' => 4, 'date' => '2026-04-01', 'weight' => 130.278, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/001'],
        ['row' => 5, 'date' => '2026-04-01', 'weight' => 12.758, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/002'],
        ['row' => 6, 'date' => '2026-04-02', 'weight' => 18.423, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/003'],
        ['row' => 7, 'date' => '2026-04-03', 'weight' => 59.214, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/004'],
        ['row' => 8, 'date' => '2026-04-04', 'weight' => 59.694, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/005'],
        ['row' => 9, 'date' => '2026-04-06', 'weight' => 18.250, 'karigar' => 'Rheea Jewels', 'source_name' => 'RHEEA JEWELS', 'voucher' => 'SMGAJ/26-27/006'],
        ['row' => 10, 'date' => '2026-04-14', 'weight' => 40.854, 'karigar' => 'Rheea Jewels', 'source_name' => 'RHEEA JEWELS', 'voucher' => 'SMGAJ/26-27/008'],
        ['row' => 11, 'date' => '2026-04-16', 'weight' => 11.950, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/009'],
        ['row' => 12, 'date' => '2026-04-17', 'weight' => 55.489, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/010'],
        ['row' => 13, 'date' => '2026-04-24', 'weight' => 22.051, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/011'],
        ['row' => 20, 'date' => '2026-05-01', 'weight' => 47.110, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/012'],
        ['row' => 21, 'date' => '2026-05-05', 'weight' => 110.259, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/013'],
        ['row' => 22, 'date' => '2026-05-08', 'weight' => 4.674, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/014'],
        ['row' => 23, 'date' => '2026-05-12', 'weight' => 24.792, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/015'],
        ['row' => 30, 'date' => '2026-06-01', 'weight' => 22.971, 'karigar' => 'Safwan Jewellery Workshop', 'source_name' => 'SAFWAN', 'voucher' => 'SMGAJ/26-27/016'],
        ['row' => 31, 'date' => '2026-06-01', 'weight' => 20.373, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/017'],
        ['row' => 32, 'date' => '2026-06-04', 'weight' => 22.806, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/018'],
        ['row' => 33, 'date' => '2026-06-06', 'weight' => 9.867, 'karigar' => 'Rheea Jewels', 'source_name' => 'RHEEA JEWELS', 'voucher' => 'SMGAJ/26-27/019'],
        ['row' => 34, 'date' => '2026-06-06', 'weight' => 21.275, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/020'],
        ['row' => 35, 'date' => '2026-06-08', 'weight' => 32.798, 'karigar' => 'Rheea Jewels', 'source_name' => 'RHEEA JEWELS', 'voucher' => 'SMGAJ/26-27/021'],
        ['row' => 36, 'date' => '2026-06-10', 'weight' => 331.812, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/022'],
        ['row' => 37, 'date' => '2026-06-15', 'weight' => 10.910, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/023'],
        ['row' => 44, 'date' => '2026-07-01', 'weight' => 18.317, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/024'],
        ['row' => 45, 'date' => '2026-07-01', 'weight' => 18.818, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/025'],
        ['row' => 46, 'date' => '2026-07-02', 'weight' => 19.252, 'karigar' => 'Safwan Jewellery Workshop', 'source_name' => 'SAFWAN JEWELLERY', 'voucher' => 'SMGAJ/26-27/026'],
        ['row' => 47, 'date' => '2026-07-03', 'weight' => 29.955, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/027'],
        ['row' => 48, 'date' => '2026-07-03', 'weight' => 27.479, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/028'],
        ['row' => 49, 'date' => '2026-07-06', 'weight' => 2.689, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/029'],
        ['row' => 50, 'date' => '2026-07-10', 'weight' => 12.189, 'karigar' => 'JGD Diamonds', 'source_name' => 'JGD DIAMONDS', 'voucher' => 'SMGAJ/26-27/030'],
        ['row' => 51, 'date' => '2026-07-11', 'weight' => 300.000, 'karigar' => 'Uttam Mal', 'source_name' => 'UTTAM MAL', 'voucher' => 'SMGAJ/26-27/031'],
        ['row' => 52, 'date' => '2026-07-21', 'weight' => 53.152, 'karigar' => 'Shree Gourango', 'source_name' => 'SHREE GOURANGO', 'voucher' => 'SMGAJ/26-27/032'],
    ];

    public function up()
    {
        $this->assertSchema();
        $this->assertSourceTotals();

        $item = $this->resolve24KItem();
        $itemId = (int) $item['id'];
        $purityId = isset($item['gold_purity_id']) ? (int) $item['gold_purity_id'] : null;
        $location = $this->resolveLocation();
        $warehouse = $this->resolveWarehouse((string) $location['name']);
        $binId = $this->resolveBinId((int) $warehouse['id']);
        $adminId = $this->resolveAdminId();
        $karigars = $this->resolveKarigars();
        $retainedOpening = $this->resolveRetainedOpening($itemId);
        $purchases = $this->resolveVerifiedPurchases($itemId);

        $this->assertReferencesAreUnused($purchases);
        $this->assertGoldLedgerIsReady($itemId, (int) $retainedOpening['id']);

        $purchaseValue = array_sum(array_column($purchases, 'line_value'));
        $rate = round($purchaseValue / self::EXPECTED_PURCHASE_WEIGHT, 2);
        $posting = new PostingService($this->db);

        $this->db->transException(true)->transStart();

        $warehouseAccountId = $posting->ensureAccount(
            'WAREHOUSE',
            'WH-' . (int) $warehouse['id'],
            (string) $warehouse['name'] . ' Warehouse',
            'warehouses',
            (int) $warehouse['id']
        );
        $sourceAccountId = $posting->ensureAccount(
            'SOURCE',
            'PRODUCTION-ISSUEMENT-SOURCE',
            'Imported Detailed Issuement Source'
        );

        $posting->postVoucher([
            'voucher_no' => self::OPENING_VOUCHER,
            'voucher_type' => 'GOLD_OPENING_BALANCE',
            'voucher_date' => '2026-04-01',
            'to_warehouse_id' => (int) $warehouse['id'],
            'to_bin_id' => $binId,
            'debit_account_id' => $warehouseAccountId,
            'credit_account_id' => $sourceAccountId,
            'skip_inventory_movement' => true,
            'remarks' => 'First April 24K opening from ' . self::SOURCE_FILE . '; later monthly openings ignored.',
            'created_by' => $adminId,
            'created_ip' => '127.0.0.1',
        ], [$this->goldVoucherLine(
            self::FIRST_OPENING_WEIGHT,
            self::FIRST_OPENING_WEIGHT,
            $purityId,
            $rate,
            'Workbook row 4 opening'
        )]);

        foreach ($purchases as $purchase) {
            $vendorId = (int) ($purchase['vendor_id'] ?? 0);
            $creditAccountId = $sourceAccountId;
            if ($vendorId > 0) {
                $creditAccountId = $posting->ensureAccount(
                    'VENDOR',
                    'VENDOR-' . $vendorId,
                    'Vendor - ' . (string) ($purchase['vendor_name'] ?? $purchase['supplier_name']),
                    'vendors',
                    $vendorId
                );
            }
            $purchaseRate = (float) $purchase['weight'] > 0
                ? round((float) $purchase['line_value'] / (float) $purchase['weight'], 3)
                : 0.0;
            $posting->postVoucher([
                'voucher_no' => (string) $purchase['invoice_no'],
                'voucher_type' => 'GOLD_PURCHASE',
                'voucher_date' => (string) $purchase['purchase_date'],
                'to_warehouse_id' => (int) $warehouse['id'],
                'to_bin_id' => $binId,
                'party_id' => $vendorId > 0 ? $vendorId : null,
                'debit_account_id' => $warehouseAccountId,
                'credit_account_id' => $creditAccountId,
                'skip_inventory_movement' => true,
                'remarks' => sprintf(
                    'Verified 24K gold purchase %s from %s.',
                    (string) $purchase['invoice_no'],
                    (string) ($purchase['vendor_name'] ?? $purchase['supplier_name'])
                ),
                'created_by' => $adminId,
                'created_ip' => '127.0.0.1',
            ], [$this->goldVoucherLine(
                (float) $purchase['weight'],
                (float) $purchase['fine_weight'],
                $purityId,
                $purchaseRate,
                (string) $purchase['invoice_no'],
                (float) $purchase['line_value']
            )]);
        }

        $issueIds = [];
        foreach (self::ISSUEMENTS as $issuement) {
            $karigar = $karigars[$issuement['karigar']];
            $createdAt = $issuement['date'] . ' 18:00:00';
            $lineValue = round($issuement['weight'] * $rate, 2);
            $notes = sprintf(
                'Imported historical 24K issuement from %s row %d; source party: %s.',
                self::SOURCE_FILE,
                $issuement['row'],
                $issuement['source_name']
            );

            $this->db->table('gold_inventory_issue_headers')->insert([
                'issue_date' => $issuement['date'],
                'voucher_no' => $issuement['voucher'],
                'order_id' => null,
                'karigar_id' => (int) $karigar['id'],
                'location_id' => (int) $location['id'],
                'issue_to' => (string) $karigar['name'],
                'purpose' => 'Jobwork',
                'notes' => $notes,
                'attachment_name' => null,
                'attachment_path' => null,
                'created_by' => $adminId,
                'account_voucher_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $issueId = (int) $this->db->insertID();
            $issueIds[$issuement['voucher']] = $issueId;

            $this->db->table('gold_inventory_issue_lines')->insert([
                'issue_id' => $issueId,
                'item_id' => $itemId,
                'weight_gm' => $issuement['weight'],
                'fine_weight_gm' => $issuement['weight'],
                'rate_per_gm' => $rate,
                'line_value' => $lineValue,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $karigarAccountId = $posting->ensureAccount(
                'KARIGAR',
                'KARIGAR-' . (int) $karigar['id'],
                'Karigar - ' . (string) $karigar['name'],
                'karigars',
                (int) $karigar['id']
            );
            $accountVoucher = $posting->postVoucher([
                'voucher_no' => $issuement['voucher'],
                'voucher_type' => 'GOLD_ISSUE',
                'voucher_date' => $issuement['date'],
                'from_warehouse_id' => (int) $warehouse['id'],
                'from_bin_id' => $binId,
                'party_id' => (int) $karigar['id'],
                'debit_account_id' => $karigarAccountId,
                'credit_account_id' => $warehouseAccountId,
                'skip_inventory_movement' => true,
                'remarks' => 'Gold issue ' . $issuement['voucher'] . ' | ' . $notes,
                'created_by' => $adminId,
                'created_ip' => '127.0.0.1',
            ], [$this->goldVoucherLine(
                $issuement['weight'],
                $issuement['weight'],
                $purityId,
                $rate,
                $issuement['voucher']
            )]);

            $this->db->table('gold_inventory_issue_headers')->where('id', $issueId)->update([
                'account_voucher_id' => (int) $accountVoucher['voucher_id'],
            ]);
        }

        if (count($issueIds) !== self::EXPECTED_COUNT) {
            throw new RuntimeException('The historical gold issuement import did not create all 31 headers.');
        }

        $this->rebuildGoldLedger(
            $retainedOpening,
            $purchases,
            $issueIds,
            $karigars,
            $itemId,
            (int) $location['id'],
            $rate,
            $adminId
        );
        $this->updateImportSummary();
        $this->assertImportedTotals($itemId);

        $this->db->transComplete();
    }

    /**
     * This migration imports production records and is intentionally
     * irreversible. A rollback must not silently remove live karigar balances.
     */
    public function down()
    {
    }

    private function assertSchema(): void
    {
        foreach ([
            'gold_inventory_items',
            'gold_inventory_stock',
            'gold_inventory_purchase_headers',
            'gold_inventory_purchase_lines',
            'gold_inventory_issue_headers',
            'gold_inventory_issue_lines',
            'gold_inventory_ledger_entries',
            'inventory_locations',
            'warehouses',
            'bins',
            'karigars',
            'vendors',
            'admin_users',
            'accounts',
            'account_balances',
            'vouchers',
            'voucher_lines',
            'ledger_entries',
        ] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Required table is missing: ' . $table);
            }
        }
    }

    private function assertSourceTotals(): void
    {
        $references = array_column(self::ISSUEMENTS, 'voucher');
        $weight = round(array_sum(array_column(self::ISSUEMENTS, 'weight')), 3);
        if (count(self::ISSUEMENTS) !== self::EXPECTED_COUNT
            || count(array_unique($references)) !== self::EXPECTED_COUNT
            || abs($weight - self::EXPECTED_WEIGHT) > 0.0005) {
            throw new RuntimeException('Embedded BABU GOLD issuement rows failed source reconciliation.');
        }
        if (in_array('SMGAJ/26-27/007', $references, true)) {
            throw new RuntimeException('Missing source voucher /007 must not be synthesized.');
        }
    }

    /** @return array<string,mixed> */
    private function resolve24KItem(): array
    {
        $item = $this->db->table('gold_inventory_items')
            ->groupStart()->where('purity_code', '24K')->orWhere('purity_percent >=', 99.99)->groupEnd()
            ->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $item) {
            throw new RuntimeException('The 24K gold inventory item is missing.');
        }
        return $item;
    }

    /** @return array<string,mixed> */
    private function resolveLocation(): array
    {
        $location = $this->db->table('inventory_locations')
            ->where('name', 'Main Production Store')->where('is_active', 1)->get()->getRowArray();
        if (! $location) {
            $location = $this->db->table('inventory_locations')
                ->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        }
        if (! $location) {
            throw new RuntimeException('An active inventory location is required.');
        }
        return $location;
    }

    /** @return array<string,mixed> */
    private function resolveWarehouse(string $locationName): array
    {
        $warehouse = $this->db->table('warehouses')
            ->where('name', $locationName)->where('is_active', 1)->get()->getRowArray();
        if (! $warehouse) {
            $warehouse = $this->db->table('warehouses')
                ->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        }
        if (! $warehouse) {
            throw new RuntimeException('An active warehouse is required.');
        }
        return $warehouse;
    }

    private function resolveBinId(int $warehouseId): int
    {
        $bin = $this->db->table('bins')
            ->where('warehouse_id', $warehouseId)->where('bin_code', 'MAIN')->get()->getRowArray();
        if (! $bin) {
            $bin = $this->db->table('bins')
                ->where('warehouse_id', $warehouseId)->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        }
        if (! $bin) {
            throw new RuntimeException('An active warehouse bin is required.');
        }
        return (int) $bin['id'];
    }

    private function resolveAdminId(): int
    {
        $admin = $this->db->table('admin_users')
            ->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $admin) {
            throw new RuntimeException('An active administrator is required for import attribution.');
        }
        return (int) $admin['id'];
    }

    /** @return array<string,array<string,mixed>> */
    private function resolveKarigars(): array
    {
        $names = array_values(array_unique(array_column(self::ISSUEMENTS, 'karigar')));
        $rows = $this->db->table('karigars')->whereIn('name', $names)->get()->getResultArray();
        $karigars = [];
        foreach ($rows as $row) {
            $karigars[(string) $row['name']] = $row;
        }
        foreach ($names as $name) {
            if (! isset($karigars[$name])) {
                throw new RuntimeException('Karigar mapping is missing for ' . $name . '.');
            }
        }
        return $karigars;
    }

    /** @return array<string,mixed> */
    private function resolveRetainedOpening(int $itemId): array
    {
        $opening = $this->db->table('gold_inventory_ledger_entries')
            ->where('txn_type', 'OPENING_BALANCE')
            ->where('reference_table', 'gold_inventory_stock')
            ->where('reference_id', $itemId)
            ->where('notes', 'Live opening balance retained after historical gold invoices were corrected.')
            ->get()->getRowArray();
        if (! $opening) {
            throw new RuntimeException('The retained live 24K opening balance was not found.');
        }
        return $opening;
    }

    /** @return list<array<string,mixed>> */
    private function resolveVerifiedPurchases(int $itemId): array
    {
        $rows = $this->db->table('gold_inventory_purchase_headers ph')
            ->select(
                'ph.id, ph.purchase_date, ph.invoice_no, ph.vendor_id, ph.supplier_name, ph.location_id, '
                . 'ph.created_at, v.name vendor_name, COUNT(pl.id) line_count, '
                . 'COALESCE(SUM(pl.weight_gm),0) weight, COALESCE(SUM(pl.fine_weight_gm),0) fine_weight, '
                . 'COALESCE(SUM(pl.line_value),0) line_value',
                false
            )
            ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->where('ph.production_document_id IS NOT NULL', null, false)
            ->where('pl.item_id', $itemId)
            ->groupBy('ph.id')
            ->orderBy('ph.purchase_date', 'ASC')
            ->orderBy('ph.id', 'ASC')
            ->get()->getResultArray();

        $lineCount = array_sum(array_map('intval', array_column($rows, 'line_count')));
        $weight = round(array_sum(array_map('floatval', array_column($rows, 'weight'))), 3);
        $invoices = array_map(static fn(array $row): string => trim((string) $row['invoice_no']), $rows);
        if (count($rows) !== self::EXPECTED_PURCHASES
            || $lineCount !== self::EXPECTED_PURCHASE_LINES
            || abs($weight - self::EXPECTED_PURCHASE_WEIGHT) > 0.0005
            || count(array_unique($invoices)) !== self::EXPECTED_PURCHASES
            || in_array('', $invoices, true)) {
            throw new RuntimeException('Verified 24K gold purchases did not reconcile to 18 invoices, 19 lines and 1669.942 gm.');
        }

        return $rows;
    }

    private function assertGoldLedgerIsReady(int $itemId, int $retainedOpeningId): void
    {
        $rows = $this->db->table('gold_inventory_ledger_entries')
            ->select('id')->where('item_id', $itemId)->get()->getResultArray();
        if (count($rows) !== 1 || (int) $rows[0]['id'] !== $retainedOpeningId) {
            throw new RuntimeException('24K gold ledger contains live movements; historical reconstruction was stopped to protect them.');
        }
    }

    /** @param list<array<string,mixed>> $purchases */
    private function assertReferencesAreUnused(array $purchases): void
    {
        $references = array_column(self::ISSUEMENTS, 'voucher');
        if ($this->db->table('gold_inventory_issue_headers')->whereIn('voucher_no', $references)->countAllResults() > 0) {
            throw new RuntimeException('One or more BABU GOLD issuement references already exist.');
        }
        $purchaseReferences = array_map(
            static fn(array $purchase): string => (string) $purchase['invoice_no'],
            $purchases
        );
        if ($this->db->table('vouchers')->whereIn(
            'voucher_no',
            array_merge($references, $purchaseReferences, [self::OPENING_VOUCHER])
        )->countAllResults() > 0) {
            throw new RuntimeException('One or more BABU GOLD accounting voucher references already exist.');
        }
    }

    /** @return array<string,mixed> */
    private function goldVoucherLine(
        float $weight,
        float $fine,
        ?int $purityId,
        float $rate,
        string $remarks,
        ?float $amount = null
    ): array {
        return [
            'item_type' => 'GOLD',
            'item_key' => 'GOLD-FINE',
            'material_name' => 'Pure Gold 24K',
            'gold_purity_id' => $purityId,
            'qty_pcs' => 0,
            'qty_cts' => 0,
            'qty_weight' => round($weight, 3),
            'fine_gold' => round($fine, 3),
            'rate' => $rate,
            'amount' => round($amount ?? ($weight * $rate), 2),
            'remarks' => $remarks,
        ];
    }

    /**
     * @param array<string,mixed> $retainedOpening
     * @param list<array<string,mixed>> $purchases
     * @param array<string,int> $issueIds
     * @param array<string,array<string,mixed>> $karigars
     */
    private function rebuildGoldLedger(
        array $retainedOpening,
        array $purchases,
        array $issueIds,
        array $karigars,
        int $itemId,
        int $locationId,
        float $rate,
        int $adminId
    ): void {
        $events = [[
            'date' => '2026-04-01',
            'priority' => 0,
            'sequence' => 0,
            'txn_type' => 'OPENING_BALANCE',
            'reference_table' => 'gold_inventory_stock',
            'reference_id' => $itemId,
            'karigar_id' => null,
            'debit_weight' => self::FIRST_OPENING_WEIGHT,
            'credit_weight' => 0.0,
            'debit_fine' => self::FIRST_OPENING_WEIGHT,
            'credit_fine' => 0.0,
            'rate' => $rate,
            'line_value' => round(self::FIRST_OPENING_WEIGHT * $rate, 2),
            'notes' => 'First April 24K opening from ' . self::SOURCE_FILE . ' row 4; May, June and July openings ignored.',
            'created_at' => '2026-04-01 09:00:00',
        ]];

        foreach ($purchases as $purchase) {
            $weight = round((float) $purchase['weight'], 3);
            $fine = round((float) $purchase['fine_weight'], 3);
            $lineValue = round((float) $purchase['line_value'], 2);
            $events[] = [
                'date' => (string) $purchase['purchase_date'],
                'priority' => 1,
                'sequence' => (int) $purchase['id'],
                'txn_type' => 'purchase',
                'reference_table' => 'gold_inventory_purchase_headers',
                'reference_id' => (int) $purchase['id'],
                'karigar_id' => null,
                'debit_weight' => $weight,
                'credit_weight' => 0.0,
                'debit_fine' => $fine,
                'credit_fine' => 0.0,
                'rate' => $weight > 0 ? round($lineValue / $weight, 2) : 0.0,
                'line_value' => $lineValue,
                'notes' => sprintf(
                    'Verified 24K gold purchase %s from %s.',
                    (string) $purchase['invoice_no'],
                    (string) ($purchase['vendor_name'] ?? $purchase['supplier_name'])
                ),
                'created_at' => (string) ($purchase['created_at'] ?: ($purchase['purchase_date'] . ' 12:00:00')),
            ];
        }

        foreach (self::ISSUEMENTS as $issuement) {
            $karigar = $karigars[$issuement['karigar']];
            $events[] = [
                'date' => $issuement['date'],
                'priority' => 2,
                'sequence' => $issuement['row'],
                'txn_type' => 'issue',
                'reference_table' => 'gold_inventory_issue_headers',
                'reference_id' => $issueIds[$issuement['voucher']],
                'karigar_id' => (int) $karigar['id'],
                'debit_weight' => 0.0,
                'credit_weight' => $issuement['weight'],
                'debit_fine' => 0.0,
                'credit_fine' => $issuement['weight'],
                'rate' => $rate,
                'line_value' => round($issuement['weight'] * $rate, 2),
                'notes' => 'Historical 24K issuement ' . $issuement['voucher'] . ' imported from ' . self::SOURCE_FILE . '.',
                'created_at' => $issuement['date'] . ' 18:00:00',
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return [$a['date'], $a['priority'], $a['sequence']] <=> [$b['date'], $b['priority'], $b['sequence']];
        });

        $this->db->table('gold_inventory_ledger_entries')->where('id', (int) $retainedOpening['id'])->delete();
        $runningWeight = 0.0;
        $runningFine = 0.0;
        foreach ($events as $event) {
            $runningWeight = round($runningWeight + $event['debit_weight'] - $event['credit_weight'], 3);
            $runningFine = round($runningFine + $event['debit_fine'] - $event['credit_fine'], 3);
            $this->db->table('gold_inventory_ledger_entries')->insert([
                'txn_date' => $event['date'],
                'txn_type' => $event['txn_type'],
                'reference_table' => $event['reference_table'],
                'reference_id' => $event['reference_id'],
                'order_id' => null,
                'karigar_id' => $event['karigar_id'],
                'location_id' => $locationId,
                'item_id' => $itemId,
                'debit_weight_gm' => $event['debit_weight'],
                'credit_weight_gm' => $event['credit_weight'],
                'debit_fine_gm' => $event['debit_fine'],
                'credit_fine_gm' => $event['credit_fine'],
                'balance_weight_gm' => $runningWeight,
                'balance_fine_gm' => $runningFine,
                'rate_per_gm' => $event['rate'],
                'line_value' => $event['line_value'],
                'notes' => $event['notes'],
                'created_by' => $adminId,
                'created_at' => $event['created_at'],
            ]);
        }

        $expected = round(self::FIRST_OPENING_WEIGHT + self::EXPECTED_PURCHASE_WEIGHT - self::EXPECTED_WEIGHT, 3);
        if (abs($runningWeight - $expected) > 0.0005 || abs($runningFine - $expected) > 0.0005) {
            throw new RuntimeException('Gold ledger does not reconcile opening + purchases - issuements.');
        }

        $this->db->table('gold_inventory_stock')->where('item_id', $itemId)->update([
            'weight_balance_gm' => $expected,
            'fine_balance_gm' => $expected,
            'avg_cost_per_gm' => $rate,
            'stock_value' => round($expected * $rate, 2),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $purchaseIds = array_map('intval', array_column($purchases, 'id'));
        $this->db->table('gold_inventory_purchase_headers')->whereIn('id', $purchaseIds)->update([
            'stock_posted' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function updateImportSummary(): void
    {
        if (! $this->db->tableExists('production_import_batches')) {
            return;
        }
        foreach ($this->db->table('production_import_batches')->select('id, summary_json')->get()->getResultArray() as $batch) {
            $summary = json_decode((string) ($batch['summary_json'] ?? '{}'), true);
            $summary = is_array($summary) ? $summary : [];
            $summary['gold_issuements'] = self::EXPECTED_COUNT;
            $summary['gold_issuement_weight_gm'] = self::EXPECTED_WEIGHT;
            $summary['gold_issuement_purity'] = '24K';
            $summary['gold_issuement_source'] = self::SOURCE_FILE;
            $summary['gold_first_opening_gm'] = self::FIRST_OPENING_WEIGHT;
            $summary['gold_purchase_ledger_entries'] = self::EXPECTED_PURCHASES;
            $summary['gold_purchase_ledger_weight_gm'] = self::EXPECTED_PURCHASE_WEIGHT;
            $summary['gold_issuement_reconciliation'] = 'April opening imported; May/June/July openings ignored; 31 complete referenced rows imported; /007 absent in source; incomplete SANTOSH HYD row ignored';
            $this->db->table('production_import_batches')->where('id', (int) $batch['id'])->update([
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function assertImportedTotals(int $itemId): void
    {
        $references = array_column(self::ISSUEMENTS, 'voucher');
        $totals = $this->db->table('gold_inventory_issue_headers ih')
            ->select('COUNT(DISTINCT ih.id) issue_count, COALESCE(SUM(il.weight_gm),0) issue_weight', false)
            ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->whereIn('ih.voucher_no', $references)
            ->get()->getRowArray() ?? [];
        if ((int) ($totals['issue_count'] ?? 0) !== self::EXPECTED_COUNT
            || abs(round((float) ($totals['issue_weight'] ?? 0), 3) - self::EXPECTED_WEIGHT) > 0.0005) {
            throw new RuntimeException('Imported gold issuements failed database reconciliation.');
        }

        $stock = $this->db->table('gold_inventory_stock')->where('item_id', $itemId)->get()->getRowArray() ?? [];
        $expectedWeight = round(self::FIRST_OPENING_WEIGHT + self::EXPECTED_PURCHASE_WEIGHT - self::EXPECTED_WEIGHT, 3);
        $expectedFine = $expectedWeight;
        if (abs(round((float) ($stock['weight_balance_gm'] ?? 0), 3) - $expectedWeight) > 0.0005
            || abs(round((float) ($stock['fine_balance_gm'] ?? 0), 3) - $expectedFine) > 0.0005) {
            throw new RuntimeException('24K stock does not reconcile after opening + purchases - issuements.');
        }

        $ledger = $this->db->table('gold_inventory_ledger_entries')
            ->select(
                'COUNT(*) entry_count, COALESCE(SUM(debit_weight_gm),0) debit_weight, '
                . 'COALESCE(SUM(credit_weight_gm),0) credit_weight',
                false
            )
            ->where('item_id', $itemId)->get()->getRowArray() ?? [];
        if ((int) ($ledger['entry_count'] ?? 0) !== (1 + self::EXPECTED_PURCHASES + self::EXPECTED_COUNT)
            || abs(round((float) ($ledger['debit_weight'] ?? 0), 3) - (self::FIRST_OPENING_WEIGHT + self::EXPECTED_PURCHASE_WEIGHT)) > 0.0005
            || abs(round((float) ($ledger['credit_weight'] ?? 0), 3) - self::EXPECTED_WEIGHT) > 0.0005) {
            throw new RuntimeException('Gold ledger opening, purchase and issuement entries failed reconciliation.');
        }
    }
}
