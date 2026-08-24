<?php

namespace App\Database\Migrations;

use App\Services\PostingService;
use CodeIgniter\Database\Migration;
use RuntimeException;

class ImportDiamondStockLedgerTransactions extends Migration
{
    private const SOURCE_FILE = 'DIA.STOCK LEDGER (26-27).xlsx';
    private const OPENING_TOTAL = 334.320;
    private const PURCHASE_TOTAL = 199.490;
    private const VERIFIED_PURCHASE_TOTAL = 156.900;
    private const ISSUE_TOTAL = 247.470;
    private const CLOSING_TOTAL = 286.340;

    /** @var array<string,float> */
    private const OPENING = [
        'VVS-VS. ROUND' => 171.890,
        'VS-SI. ROUND' => 34.690,
        'SI/IJ' => 8.950,
        'BUG' => 2.830,
        'RC' => 48.910,
        'PAN' => 5.470,
        'MQ' => 29.580,
        'POLKI' => 25.670,
        'PRS' => 3.250,
        'FANCY/EM' => 3.080,
    ];

    /**
     * Every Purchase row from the workbook is retained. Ten rows reconcile to
     * verified PDFs. Two rows have no matching PDF and therefore deliberately
     * carry zero price/tax rather than invented financial data.
     *
     * @var list<array<string,mixed>>
     */
    private const PURCHASES = [
        [
            'sheet' => 'APR-26', 'row' => 4, 'date' => '2026-04-01', 'party' => 'ASHWA GEMS',
            'reference' => '01/2026-27', 'document_invoice' => '01/2026-27',
            'lines' => [
                ['product' => 'LAB GROWN', 'cts' => 1.000, 'rate' => 11000.00, 'amount' => 11000.00],
                ['product' => 'LAB GROWN', 'cts' => 25.340, 'rate' => 9000.00, 'amount' => 228060.00],
            ],
        ],
        [
            'sheet' => 'APR-26', 'row' => 9, 'date' => '2026-04-04', 'party' => 'ASHWA GEMS',
            'reference' => '02/2026-27', 'document_invoice' => '02/2026-27',
            'lines' => [['product' => 'LAB GROWN', 'cts' => 28.430, 'rate' => 9000.00, 'amount' => 255870.00]],
        ],
        [
            'sheet' => 'APR-26', 'row' => 12, 'date' => '2026-04-14', 'party' => 'ASHWA GEMS',
            'reference' => '03/2026-27', 'document_invoice' => '03/2026-27',
            'lines' => [['product' => 'LAB GROWN', 'cts' => 22.130, 'rate' => 17154.00, 'amount' => 379618.02]],
        ],
        [
            'sheet' => 'APR-26', 'row' => 17, 'date' => '2026-04-21', 'party' => 'KALASHA FINE JEWELS',
            'reference' => 'KFJ/26-27/043', 'document_invoice' => 'KFJ/26-27/043',
            'lines' => [
                ['product' => 'SI/IJ', 'cts' => 14.310, 'rate' => 13479.76, 'amount' => 192895.36],
                ['product' => 'POLKI', 'cts' => 3.970, 'rate' => 13479.76, 'amount' => 53514.64],
            ],
        ],
        [
            'sheet' => 'JUNE-26', 'row' => 4, 'date' => '2026-06-02', 'party' => 'KALASHA FINE JEWELS',
            'reference' => 'KFJ/26-27/109', 'document_invoice' => 'KFJ/26-27/109',
            'lines' => [['product' => 'VVS-VS. ROUND', 'cts' => 8.840, 'rate' => 39999.66, 'amount' => 353597.00]],
        ],
        [
            'sheet' => 'JUNE-26', 'row' => 8, 'date' => '2026-06-06', 'party' => 'DIAMONDONCALL',
            'reference' => 'DOC-01593', 'document_invoice' => 'DOC-01593',
            'lines' => [['product' => 'FANCY/EM', 'cts' => 0.900, 'rate' => 217810.70, 'amount' => 196029.63]],
        ],
        [
            'sheet' => 'JUNE-26', 'row' => 10, 'date' => '2026-06-10', 'party' => 'SMGJ PURCHASE',
            'reference' => 'SMGJ/HYD/BS/020', 'document_invoice' => null,
            'lines' => [
                ['product' => 'VVS-VS. ROUND', 'cts' => 32.400, 'rate' => 0.00, 'amount' => 0.00],
                ['product' => 'POLKI', 'cts' => 0.180, 'rate' => 0.00, 'amount' => 0.00],
            ],
        ],
        [
            'sheet' => 'JUNE-26', 'row' => 11, 'date' => '2026-06-10', 'party' => 'ASHWA GEMS',
            'reference' => '10/2026-27', 'document_invoice' => '14/2026-27',
            'lines' => [['product' => 'VVS-VS. ROUND', 'cts' => 43.260, 'rate' => 35000.00, 'amount' => 1514100.00]],
        ],
        [
            'sheet' => 'JUNE-26', 'row' => 14, 'date' => '2026-06-26', 'party' => 'VEER DIAM',
            'reference' => 'VD/L/071/2026-27', 'document_invoice' => 'VD/L/071/2026-27',
            'lines' => [['product' => 'VVS-VS. ROUND', 'cts' => 2.700, 'rate' => 34000.00, 'amount' => 91800.00]],
        ],
        [
            'sheet' => 'JULY-26', 'row' => 8, 'date' => '2026-07-03', 'party' => 'KALASHA FINE JEWELS',
            'reference' => 'KFJ/26-27/151', 'document_invoice' => 'KFJ/26-27/151',
            'lines' => [
                ['product' => 'VVS-VS. ROUND', 'cts' => 1.800, 'rate' => 42969.35, 'amount' => 77344.83],
                ['product' => 'MQ', 'cts' => 0.810, 'rate' => 42969.35, 'amount' => 34805.17],
            ],
        ],
        [
            'sheet' => 'JULY-26', 'row' => 9, 'date' => '2026-07-06', 'party' => 'KALASHA FINE JEWELS',
            'reference' => 'KFJ/26-27/151', 'document_invoice' => null,
            'lines' => [
                ['product' => 'SI/IJ', 'cts' => 5.950, 'rate' => 0.00, 'amount' => 0.00],
                ['product' => 'POLKI', 'cts' => 4.060, 'rate' => 0.00, 'amount' => 0.00],
            ],
        ],
        [
            'sheet' => 'JULY-26', 'row' => 11, 'date' => '2026-07-09', 'party' => 'KALASHA FINE JEWELS',
            'reference' => 'KFJ/26-27/156', 'document_invoice' => 'KFJ/26-27/156',
            'lines' => [['product' => 'VVS-VS. ROUND', 'cts' => 3.410, 'rate' => 40000.00, 'amount' => 136400.00]],
        ],
    ];

    /** @var list<array<string,mixed>> */
    private const ISSUES = [
        ['sheet'=>'APR-26','row'=>5,'date'=>'2026-04-01','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/001','lines'=>['LAB GROWN'=>26.340]],
        ['sheet'=>'APR-26','row'=>6,'date'=>'2026-04-01','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/002','lines'=>['VVS-VS. ROUND'=>2.480]],
        ['sheet'=>'APR-26','row'=>7,'date'=>'2026-04-02','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/003','lines'=>['VVS-VS. ROUND'=>2.160]],
        ['sheet'=>'APR-26','row'=>8,'date'=>'2026-04-03','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/004','lines'=>['SI/IJ'=>11.860,'POLKI'=>9.080]],
        ['sheet'=>'APR-26','row'=>10,'date'=>'2026-04-04','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/005','lines'=>['LAB GROWN'=>28.430]],
        ['sheet'=>'APR-26','row'=>11,'date'=>'2026-04-06','party'=>'RHEEA JEWELS','reference'=>'SMGAJ-J/26-27/006','lines'=>['VVS-VS. ROUND'=>8.440]],
        ['sheet'=>'APR-26','row'=>13,'date'=>'2026-04-14','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/007','lines'=>['LAB GROWN'=>22.130]],
        ['sheet'=>'APR-26','row'=>14,'date'=>'2026-04-14','party'=>'RHEEA JEWELS','reference'=>'SMGAJ-J/26-27/008','lines'=>['VVS-VS. ROUND'=>5.890]],
        ['sheet'=>'APR-26','row'=>15,'date'=>'2026-04-16','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/009','lines'=>['VVS-VS. ROUND'=>0.130]],
        ['sheet'=>'APR-26','row'=>16,'date'=>'2026-04-17','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/010','lines'=>['VVS-VS. ROUND'=>9.550]],
        ['sheet'=>'APR-26','row'=>18,'date'=>'2026-04-24','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/011','lines'=>['VVS-VS. ROUND'=>3.610,'MQ'=>0.360]],
        ['sheet'=>'MAY-26','row'=>4,'date'=>'2026-05-01','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/012','lines'=>['VVS-VS. ROUND'=>10.970]],
        ['sheet'=>'MAY-26','row'=>5,'date'=>'2026-05-05','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/013','lines'=>['VVS-VS. ROUND'=>11.960,'BUG'=>1.410]],
        ['sheet'=>'MAY-26','row'=>6,'date'=>'2026-05-08','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/014','lines'=>['VVS-VS. ROUND'=>0.320]],
        ['sheet'=>'MAY-26','row'=>7,'date'=>'2026-05-12','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/015','lines'=>['VVS-VS. ROUND'=>3.180,'BUG'=>0.250]],
        // The workbook says 04.06.2024; the FY sheet and voucher sequence prove 2026.
        ['sheet'=>'JUNE-26','row'=>5,'date'=>'2026-06-04','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/018','lines'=>['VVS-VS. ROUND'=>2.020]],
        ['sheet'=>'JUNE-26','row'=>6,'date'=>'2026-06-06','party'=>'RHEEA JEWELS','reference'=>'SMGAJ-J/26-27/019','lines'=>['VVS-VS. ROUND'=>2.980]],
        ['sheet'=>'JUNE-26','row'=>7,'date'=>'2026-06-06','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/020','lines'=>['VVS-VS. ROUND'=>4.960]],
        ['sheet'=>'JUNE-26','row'=>9,'date'=>'2026-06-08','party'=>'RHEEA JEWELS','reference'=>'SMGAJ-J/26-27/021','lines'=>['VVS-VS. ROUND'=>9.220]],
        ['sheet'=>'JUNE-26','row'=>12,'date'=>'2026-06-10','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/022','lines'=>['VVS-VS. ROUND'=>41.130]],
        ['sheet'=>'JUNE-26','row'=>13,'date'=>'2026-06-15','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/023','lines'=>['SI/IJ'=>2.190,'RC'=>0.720,'POLKI'=>0.980]],
        ['sheet'=>'JULY-26','row'=>4,'date'=>'2026-07-01','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/024','lines'=>['VVS-VS. ROUND'=>2.220,'MQ'=>0.810]],
        ['sheet'=>'JULY-26','row'=>5,'date'=>'2026-07-01','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/025','lines'=>['SI/IJ'=>5.950,'POLKI'=>4.060]],
        ['sheet'=>'JULY-26','row'=>6,'date'=>'2026-07-02','party'=>'SAFWAN JEWELLERY WORKSHOP','reference'=>'SMGAJ-J/26-27/026','lines'=>['VVS-VS. ROUND'=>1.910]],
        ['sheet'=>'JULY-26','row'=>7,'date'=>'2026-07-03','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/028','lines'=>['VVS-VS. ROUND'=>3.410]],
        ['sheet'=>'JULY-26','row'=>10,'date'=>'2026-07-06','party'=>'UTTAM MAL','reference'=>'SMGAJ-J/26-27/029','lines'=>['FANCY/EM'=>0.900]],
        ['sheet'=>'JULY-26','row'=>12,'date'=>'2026-07-10','party'=>'JGD DIAMONDS','reference'=>'SMGAJ-J/26-27/030','lines'=>['VVS-VS. ROUND'=>1.780]],
        ['sheet'=>'JULY-26','row'=>13,'date'=>'2026-07-21','party'=>'SHREE GOURANGO GOLD WORK SHOP','reference'=>'SMGAJ-J/26-27/032','lines'=>['VVS-VS. ROUND'=>3.680]],
    ];

    public function up()
    {
        $this->assertSchema();
        $this->addPurchaseColumns();
        $this->createOpeningTable();
        $this->assertSourceTotals();
        $this->assertCleanableBaseline();

        $items = $this->resolveItems();
        $karigars = $this->resolveKarigars();
        $location = $this->resolveLocation();
        $warehouse = $this->resolveWarehouse((string) $location['name']);
        $binId = $this->resolveBinId((int) $warehouse['id']);
        $adminId = $this->resolveAdminId();

        $this->db->transException(true)->transStart();
        $this->cleanLegacyDiamondImport();
        $this->rebuildAccountBalances();

        $posting = new PostingService($this->db);
        $warehouseAccountId = $posting->ensureAccount(
            'WAREHOUSE', 'WH-' . (int) $warehouse['id'], (string) $warehouse['name'] . ' Warehouse',
            'warehouses', (int) $warehouse['id']
        );
        $sourceAccountId = $posting->ensureAccount(
            'SOURCE', 'PRODUCTION-SOURCE', 'Imported Production Source'
        );

        $events = [];
        $this->insertOpening(
            $items, $events, $posting, $warehouseAccountId, $sourceAccountId,
            (int) $warehouse['id'], $binId, (int) $location['id'], $adminId
        );
        $purchaseResult = $this->insertPurchases(
            $items, $events, $posting, $warehouseAccountId, $sourceAccountId,
            (int) $warehouse['id'], $binId, $adminId
        );
        $issueResult = $this->insertIssues(
            $items, $karigars, $events, $posting, $warehouseAccountId,
            (int) $warehouse['id'], $binId, (int) $location['id'], $adminId
        );

        $this->rebuildStock($items, $events);
        $this->syncIssueVoucherValues();
        $this->updateImportSummary($purchaseResult, $issueResult);
        $this->assertImportedTotals($purchaseResult, $issueResult, $warehouseAccountId);
        $this->db->transComplete();
    }

    /** Production imports are intentionally not silently deleted by rollback. */
    public function down()
    {
    }

    private function assertSchema(): void
    {
        foreach ([
            'purchase_headers','purchase_lines','items','stock','issue_headers','issue_lines','return_headers','return_lines',
            'diamond_purchase_attachments','diamond_inventory_adjustment_headers','diamond_inventory_adjustment_lines',
            'production_purchase_documents','vendors','karigars','inventory_locations','warehouses','bins','admin_users',
            'accounts','account_balances','vouchers','voucher_lines','ledger_entries','purchase_bill_payments',
        ] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Required table is missing: ' . $table);
            }
        }
    }

    private function addPurchaseColumns(): void
    {
        $columns = [
            'production_document_id' => ['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'null'=>true,'after'=>'vendor_id'],
            'supplier_address' => ['type'=>'TEXT','null'=>true,'after'=>'supplier_name'],
            'supplier_gstin' => ['type'=>'VARCHAR','constraint'=>25,'null'=>true,'after'=>'supplier_address'],
            'supplier_phone' => ['type'=>'VARCHAR','constraint'=>50,'null'=>true,'after'=>'supplier_gstin'],
            'supplier_email' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true,'after'=>'supplier_phone'],
            'place_of_supply' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true,'after'=>'due_date'],
            'purchase_description' => ['type'=>'TEXT','null'=>true,'after'=>'place_of_supply'],
            'taxable_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'purchase_description'],
            'cgst_rate' => ['type'=>'DECIMAL','constraint'=>'7,3','null'=>true,'after'=>'taxable_amount'],
            'cgst_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'cgst_rate'],
            'sgst_rate' => ['type'=>'DECIMAL','constraint'=>'7,3','null'=>true,'after'=>'cgst_amount'],
            'sgst_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'sgst_rate'],
            'igst_rate' => ['type'=>'DECIMAL','constraint'=>'7,3','null'=>true,'after'=>'sgst_amount'],
            'igst_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'igst_rate'],
            'gst_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'igst_amount'],
            'round_off_amount' => ['type'=>'DECIMAL','constraint'=>'12,2','default'=>0,'after'=>'gst_amount'],
            'payment_status' => ['type'=>'VARCHAR','constraint'=>20,'default'=>'Pending','after'=>'invoice_total'],
            'paid_amount' => ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0,'after'=>'payment_status'],
            'payment_date' => ['type'=>'DATE','null'=>true,'after'=>'paid_amount'],
            'stock_posted' => ['type'=>'TINYINT','constraint'=>1,'default'=>1,'after'=>'payment_date'],
            'source_sheet' => ['type'=>'VARCHAR','constraint'=>40,'null'=>true,'after'=>'stock_posted'],
            'source_row' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'null'=>true,'after'=>'source_sheet'],
            'verification_status' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true,'after'=>'source_row'],
            'account_voucher_id' => ['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'null'=>true,'after'=>'verification_status'],
        ];
        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'purchase_headers')) {
                $this->forge->addColumn('purchase_headers', [$name => $definition]);
            }
        }
        $indexes = $this->db->getIndexData('purchase_headers');
        if (! isset($indexes['uq_diamond_purchase_document'])) {
            $this->db->query('ALTER TABLE `purchase_headers` ADD UNIQUE INDEX `uq_diamond_purchase_document` (`production_document_id`)');
        }
    }

    private function createOpeningTable(): void
    {
        if ($this->db->tableExists('diamond_inventory_opening_balances')) {
            return;
        }
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'auto_increment'=>true],
            'opening_date'=>['type'=>'DATE'],
            'reference_no'=>['type'=>'VARCHAR','constraint'=>80],
            'item_id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true],
            'pcs'=>['type'=>'DECIMAL','constraint'=>'18,3','default'=>0],
            'carat'=>['type'=>'DECIMAL','constraint'=>'18,3'],
            'rate_per_carat'=>['type'=>'DECIMAL','constraint'=>'18,2','default'=>0],
            'line_value'=>['type'=>'DECIMAL','constraint'=>'18,2','default'=>0],
            'location_id'=>['type'=>'INT','constraint'=>11,'unsigned'=>true,'null'=>true],
            'notes'=>['type'=>'TEXT','null'=>true],
            'created_by'=>['type'=>'INT','constraint'=>11,'unsigned'=>true,'null'=>true],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['opening_date','item_id']);
        $this->forge->createTable('diamond_inventory_opening_balances', true);
    }

    private function assertSourceTotals(): void
    {
        $opening = round(array_sum(self::OPENING), 3);
        $purchases = 0.0; $verified = 0.0; $purchaseLines = 0;
        foreach (self::PURCHASES as $purchase) {
            foreach ($purchase['lines'] as $line) {
                $purchases += (float) $line['cts']; $purchaseLines++;
                if ($purchase['document_invoice'] !== null) $verified += (float) $line['cts'];
            }
        }
        $issues = 0.0; $issueLines = 0; $references = [];
        foreach (self::ISSUES as $issue) {
            $references[] = $issue['reference'];
            foreach ($issue['lines'] as $cts) { $issues += (float) $cts; $issueLines++; }
        }
        if (abs($opening-self::OPENING_TOTAL)>0.0005 || abs(round($purchases,3)-self::PURCHASE_TOTAL)>0.0005
            || abs(round($verified,3)-self::VERIFIED_PURCHASE_TOTAL)>0.0005 || $purchaseLines!==17
            || abs(round($issues,3)-self::ISSUE_TOTAL)>0.0005 || $issueLines!==36
            || count(self::ISSUES)!==28 || count(array_unique($references))!==28
            || abs(round($opening+$purchases-$issues,3)-self::CLOSING_TOTAL)>0.0005) {
            throw new RuntimeException('Embedded diamond workbook data failed source reconciliation.');
        }
    }

    private function assertCleanableBaseline(): void
    {
        if ($this->db->table('purchase_headers')->countAllResults() !== 12
            || $this->db->table('issue_headers')->countAllResults() !== 0
            || $this->db->table('return_headers')->countAllResults() !== 0
            || $this->db->table('diamond_inventory_adjustment_headers')->countAllResults() !== 0) {
            throw new RuntimeException('Diamond inventory contains live transactions; historical replacement was stopped to protect them.');
        }
        $documents = $this->db->table('production_purchase_documents')
            ->where('category', 'diamond')->whereIn('invoice_no', $this->verifiedDocumentInvoices())->countAllResults();
        if ($documents !== 10) {
            throw new RuntimeException('Expected all 10 PDF-matched diamond purchase documents.');
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function resolveItems(): array
    {
        $needed = array_unique(array_merge(array_keys(self::OPENING), $this->allTransactionProducts()));
        $items = [];
        foreach ($this->db->table('items')->get()->getResultArray() as $item) {
            $items[$this->productKey((string) ($item['clarity'] ?? ''))] = $item;
        }
        foreach ($needed as $product) {
            $key = $this->productKey($product);
            if (! isset($items[$key])) throw new RuntimeException('Diamond item mapping is missing for ' . $product . '.');
        }
        return $items;
    }

    /** @return list<string> */
    private function allTransactionProducts(): array
    {
        $products = [];
        foreach (self::PURCHASES as $purchase) foreach ($purchase['lines'] as $line) $products[] = $line['product'];
        foreach (self::ISSUES as $issue) foreach (array_keys($issue['lines']) as $product) $products[] = $product;
        return $products;
    }

    /** @return array<string,array<string,mixed>> */
    private function resolveKarigars(): array
    {
        $aliases = [
            'SHREE GOURANGO GOLD WORK SHOP'=>'SHREE GOURANGO',
            'SAFWAN JEWELLERY WORKSHOP'=>'SAFWAN JEWELLERY',
        ];
        $rows = $this->db->table('karigars')->get()->getResultArray();
        $byName = [];
        foreach ($rows as $row) $byName[$this->nameKey((string) $row['name'])] = $row;
        $mapped = [];
        foreach (self::ISSUES as $issue) {
            $source = $issue['party']; $target = $aliases[$source] ?? $source; $key = $this->nameKey($target);
            if (! isset($byName[$key])) throw new RuntimeException('Karigar mapping is missing for ' . $source . '.');
            $mapped[$source] = $byName[$key];
        }
        return $mapped;
    }

    /** @return array<string,mixed> */
    private function resolveLocation(): array
    {
        $row = $this->db->table('inventory_locations')->where('name','Main Production Store')->where('is_active',1)->get()->getRowArray();
        if (! $row) throw new RuntimeException('Main Production Store is missing.');
        return $row;
    }

    /** @return array<string,mixed> */
    private function resolveWarehouse(string $name): array
    {
        $row = $this->db->table('warehouses')->where('name',$name)->where('is_active',1)->get()->getRowArray();
        if (! $row) throw new RuntimeException('Main Production Store warehouse is missing.');
        return $row;
    }

    private function resolveBinId(int $warehouseId): int
    {
        $row = $this->db->table('bins')->where('warehouse_id',$warehouseId)->where('bin_code','MAIN')->get()->getRowArray();
        if (! $row) throw new RuntimeException('Main warehouse bin is missing.');
        return (int) $row['id'];
    }

    private function resolveAdminId(): int
    {
        $row = $this->db->table('admin_users')->select('id')->where('is_active',1)->orderBy('id','ASC')->get()->getRowArray();
        if (! $row) throw new RuntimeException('An active administrator is required.');
        return (int) $row['id'];
    }

    private function cleanLegacyDiamondImport(): void
    {
        $purchaseIds = array_map('intval', array_column($this->db->table('purchase_headers')->select('id')->get()->getResultArray(),'id'));
        if ($purchaseIds !== []) {
            $this->db->table('purchase_bill_payments')->where('source_type','diamond')->whereIn('source_id',$purchaseIds)->delete();
            $this->db->table('diamond_purchase_attachments')->whereIn('purchase_id',$purchaseIds)->delete();
            $this->db->table('purchase_lines')->whereIn('purchase_id',$purchaseIds)->delete();
            $this->db->table('purchase_headers')->whereIn('id',$purchaseIds)->delete();
        }
        // Keep the replacement atomic. TRUNCATE would implicitly commit on MySQL.
        $this->db->query('DELETE FROM diamond_inventory_opening_balances');
        $this->db->table('stock')->set(['pcs_balance'=>0,'carat_balance'=>0,'avg_cost_per_carat'=>0,'stock_value'=>0,'updated_at'=>date('Y-m-d H:i:s')])->update();

        $rows = $this->db->table('vouchers')->select('id')
            ->groupStart()->like('voucher_no','IMP-DREC-','after')->orLike('voucher_no','IMP-DPUR-','after')->groupEnd()
            ->whereIn('voucher_type',['OPENING_DIAMOND','DIAMOND_PURCHASE'])->get()->getResultArray();
        $voucherIds = array_map('intval',array_column($rows,'id'));
        if ($voucherIds === []) return;
        foreach (['ledger_entries','voucher_lines'] as $table) $this->db->table($table)->whereIn('voucher_id',$voucherIds)->delete();
        if ($this->db->tableExists('voucher_reversals')) {
            $this->db->table('voucher_reversals')->groupStart()->whereIn('original_voucher_id',$voucherIds)->orWhereIn('reversal_voucher_id',$voucherIds)->groupEnd()->delete();
        }
        if ($this->db->tableExists('audit_logs')) $this->db->table('audit_logs')->where('entity_type','voucher')->whereIn('entity_id',$voucherIds)->delete();
        $this->db->table('vouchers')->whereIn('id',$voucherIds)->delete();
    }

    private function rebuildAccountBalances(): void
    {
        // Keep the replacement atomic. TRUNCATE would implicitly commit on MySQL.
        $this->db->query('DELETE FROM account_balances');
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $this->db->query("INSERT INTO account_balances
            (account_id,item_type,item_key,qty_pcs,qty_cts,qty_weight,fine_gold_qty,created_at,updated_at)
            SELECT account_id,item_type,item_key,ROUND(SUM(qty_pcs),3),ROUND(SUM(qty_cts),3),ROUND(SUM(qty_weight),3),ROUND(SUM(fine_gold_qty),3),{$now},{$now}
            FROM (
                SELECT le.debit_account_id account_id,le.item_type,le.item_key,le.qty_pcs,le.qty_cts,le.qty_weight,le.fine_gold_qty
                FROM ledger_entries le JOIN vouchers v ON v.id=le.voucher_id WHERE v.status='Posted'
                UNION ALL
                SELECT le.credit_account_id,le.item_type,le.item_key,-le.qty_pcs,-le.qty_cts,-le.qty_weight,-le.fine_gold_qty
                FROM ledger_entries le JOIN vouchers v ON v.id=le.voucher_id WHERE v.status='Posted'
            ) x WHERE account_id IS NOT NULL AND account_id>0 GROUP BY account_id,item_type,item_key");
    }

    /**
     * @param array<string,array<string,mixed>> $items
     * @param list<array<string,mixed>> $events
     */
    private function insertOpening(array $items, array &$events, PostingService $posting, int $warehouseAccountId,
        int $sourceAccountId, int $warehouseId, int $binId, int $locationId, int $adminId): void
    {
        foreach (self::OPENING as $product => $cts) {
            $itemId = (int) $items[$this->productKey($product)]['id'];
            $this->db->table('diamond_inventory_opening_balances')->insert([
                'opening_date'=>'2026-04-01','reference_no'=>'DIA-OPEN-20260401','item_id'=>$itemId,'pcs'=>0,'carat'=>$cts,
                'rate_per_carat'=>0,'line_value'=>0,'location_id'=>$locationId,
                'notes'=>'First April opening from ' . self::SOURCE_FILE . '; later monthly openings ignored.',
                'created_by'=>$adminId,'created_at'=>'2026-04-01 09:00:00','updated_at'=>'2026-04-01 09:00:00',
            ]);
            $events[] = ['date'=>'2026-04-01','priority'=>0,'sequence'=>$itemId,'type'=>'opening','item_id'=>$itemId,'cts'=>$cts,'value'=>0.0];
        }
        $posting->postVoucher([
            'voucher_no'=>'DIA-OPEN-20260401','voucher_type'=>'OPENING_DIAMOND','voucher_date'=>'2026-04-01',
            'to_warehouse_id'=>$warehouseId,'to_bin_id'=>$binId,'debit_account_id'=>$warehouseAccountId,'credit_account_id'=>$sourceAccountId,
            'skip_inventory_movement'=>true,'remarks'=>'First April diamond opening from ' . self::SOURCE_FILE . '; May/June/July openings ignored.',
            'created_by'=>$adminId,'created_ip'=>'127.0.0.1',
        ],[$this->diamondPoolLine(self::OPENING_TOTAL,0,'April opening')]);
    }

    /**
     * @param array<string,array<string,mixed>> $items
     * @param list<array<string,mixed>> $events
     * @return array{headers:int,lines:int,cts:float,verified:int,unverified:int,taxable:float,invoices:float}
     */
    private function insertPurchases(array $items, array &$events, PostingService $posting, int $warehouseAccountId,
        int $sourceAccountId, int $warehouseId, int $binId, int $adminId): array
    {
        $documents = [];
        foreach ($this->db->table('production_purchase_documents')->where('category','diamond')->whereIn('invoice_no',$this->verifiedDocumentInvoices())->get()->getResultArray() as $doc) {
            $documents[(string)$doc['invoice_no']] = $doc;
        }
        $result = ['headers'=>0,'lines'=>0,'cts'=>0.0,'verified'=>0,'unverified'=>0,'taxable'=>0.0,'invoices'=>0.0];
        foreach (self::PURCHASES as $purchaseIndex => $purchase) {
            $docInvoice = $purchase['document_invoice']; $document = $docInvoice !== null ? ($documents[$docInvoice] ?? null) : null;
            if ($docInvoice !== null && ! $document) throw new RuntimeException('Verified diamond PDF not found for ' . $docInvoice . '.');
            $verified = $document !== null;
            $vendor = $verified ? $this->ensureDocumentVendor($document) : $this->resolveUnverifiedVendor((string)$purchase['party']);
            $lineSubtotal = round(array_sum(array_column($purchase['lines'],'amount')),2);
            $taxPercentage = $verified ? round((float)($document['cgst_rate']??0)+(float)($document['sgst_rate']??0)+(float)($document['igst_rate']??0),3) : 0.0;
            $notes = $verified
                ? sprintf('Verified PDF-mapped historical diamond purchase from %s %s row %d.',self::SOURCE_FILE,$purchase['sheet'],$purchase['row'])
                : sprintf('Historical diamond stock receipt from %s %s row %d; matching PDF and price are pending.',self::SOURCE_FILE,$purchase['sheet'],$purchase['row']);
            $values = [
                'purchase_date'=>$purchase['date'],'vendor_id'=>(int)$vendor['id'],'production_document_id'=>$verified?(int)$document['id']:null,
                'supplier_name'=>$verified?(string)$document['vendor_name']:(string)$vendor['name'],
                'supplier_address'=>$verified?($document['vendor_address']?:null):($vendor['address']??null),
                'supplier_gstin'=>$verified?($document['vendor_gstin']?:null):($vendor['gstin']??null),
                'supplier_phone'=>$verified?($document['vendor_phone']?:null):($vendor['phone']??null),
                'supplier_email'=>$verified?($document['vendor_email']?:null):($vendor['email']??null),
                'invoice_no'=>$purchase['reference'],'due_date'=>$verified?($document['due_date']?:null):null,
                'place_of_supply'=>$verified?($document['place_of_supply']?:null):null,
                'purchase_description'=>$verified?($document['purchase_description']?:null):'PDF/price pending',
                'taxable_amount'=>$verified?(float)$document['taxable_amount']:0,'cgst_rate'=>$verified?$document['cgst_rate']:null,
                'cgst_amount'=>$verified?(float)$document['cgst_amount']:0,'sgst_rate'=>$verified?$document['sgst_rate']:null,
                'sgst_amount'=>$verified?(float)$document['sgst_amount']:0,'igst_rate'=>$verified?$document['igst_rate']:null,
                'igst_amount'=>$verified?(float)$document['igst_amount']:0,'gst_amount'=>$verified?(float)$document['gst_amount']:0,
                'round_off_amount'=>$verified?(float)$document['round_off_amount']:0,'tax_percentage'=>$taxPercentage,
                'invoice_total'=>$verified?(float)$document['invoice_amount']:0,'payment_status'=>$verified?(string)$document['payment_status']:'PDF Pending',
                'paid_amount'=>$verified?(float)$document['paid_amount']:0,'payment_date'=>$verified?($document['payment_date']?:null):null,
                'stock_posted'=>1,'source_sheet'=>$purchase['sheet'],'source_row'=>$purchase['row'],
                'verification_status'=>$verified?'PDF Verified':'PDF Missing - Price Pending','account_voucher_id'=>null,'notes'=>$notes,
                'created_at'=>$purchase['date'].' 12:00:00','updated_at'=>date('Y-m-d H:i:s'),
            ];
            $this->db->table('purchase_headers')->insert($values); $purchaseId=(int)$this->db->insertID();
            $totalCts=0.0;
            foreach ($purchase['lines'] as $lineIndex=>$line) {
                $itemId=(int)$items[$this->productKey($line['product'])]['id'];
                $this->db->table('purchase_lines')->insert([
                    'purchase_id'=>$purchaseId,'item_id'=>$itemId,'pcs'=>0,'carat'=>$line['cts'],'rate_per_carat'=>$line['rate'],
                    'line_value'=>$line['amount'],'created_at'=>$purchase['date'].' 12:00:00','updated_at'=>$purchase['date'].' 12:00:00',
                ]);
                $lineId=(int)$this->db->insertID(); $totalCts+=(float)$line['cts'];
                $events[]=['date'=>$purchase['date'],'priority'=>1,'sequence'=>($purchaseIndex*100)+$lineIndex,'type'=>'purchase','item_id'=>$itemId,'cts'=>(float)$line['cts'],'value'=>(float)$line['amount'],'line_id'=>$lineId];
                $result['lines']++;
            }
            $creditAccountId=$posting->ensureAccount('VENDOR','VENDOR-'.(int)$vendor['id'],'Vendor - '.(string)$vendor['name'],'vendors',(int)$vendor['id']);
            $voucherNo=(string)$purchase['reference'];
            if ($this->db->table('vouchers')->where('voucher_no',$voucherNo)->countAllResults()>0) $voucherNo.='-'.str_replace('-','',(string)$purchase['date']);
            $voucher=$posting->postVoucher([
                'voucher_no'=>$voucherNo,'voucher_type'=>'DIAMOND_PURCHASE','voucher_date'=>$purchase['date'],'to_warehouse_id'=>$warehouseId,'to_bin_id'=>$binId,
                'party_id'=>(int)$vendor['id'],'debit_account_id'=>$warehouseAccountId,'credit_account_id'=>$creditAccountId,'skip_inventory_movement'=>true,
                'remarks'=>$notes,'created_by'=>$adminId,'created_ip'=>'127.0.0.1',
            ],[$this->diamondPoolLine($totalCts,$lineSubtotal,(string)$purchase['reference'])]);
            $this->db->table('purchase_headers')->where('id',$purchaseId)->update(['account_voucher_id'=>(int)$voucher['voucher_id']]);
            if ($verified && strtolower((string)$document['payment_status'])==='paid') $this->insertLinkedPayment($purchaseId,$document,$adminId);
            $result['headers']++; $result['cts']+=$totalCts; $result[$verified?'verified':'unverified']++;
            $result['taxable']+=(float)($verified?$document['taxable_amount']:0); $result['invoices']+=(float)($verified?$document['invoice_amount']:0);
        }
        return $result;
    }

    /**
     * @param array<string,array<string,mixed>> $items
     * @param array<string,array<string,mixed>> $karigars
     * @param list<array<string,mixed>> $events
     * @return array{headers:int,lines:int,cts:float}
     */
    private function insertIssues(array $items,array $karigars,array &$events,PostingService $posting,int $warehouseAccountId,
        int $warehouseId,int $binId,int $locationId,int $adminId): array
    {
        $result=['headers'=>0,'lines'=>0,'cts'=>0.0];
        foreach (self::ISSUES as $issueIndex=>$issue) {
            $karigar=$karigars[$issue['party']];
            $notes=sprintf('Imported historical diamond issuement from %s %s row %d.',self::SOURCE_FILE,$issue['sheet'],$issue['row']);
            $this->db->table('issue_headers')->insert([
                'issue_date'=>$issue['date'],'voucher_no'=>$issue['reference'],'order_id'=>null,'karigar_id'=>(int)$karigar['id'],'location_id'=>$locationId,
                'issue_to'=>(string)$karigar['name'],'purpose'=>'Jobwork','notes'=>$notes,'attachment_name'=>null,'attachment_path'=>null,
                'created_by'=>$adminId,'account_voucher_id'=>null,'created_at'=>$issue['date'].' 18:00:00','updated_at'=>$issue['date'].' 18:00:00',
            ]);
            $issueId=(int)$this->db->insertID(); $totalCts=0.0;
            foreach ($issue['lines'] as $product=>$cts) {
                $itemId=(int)$items[$this->productKey($product)]['id'];
                $this->db->table('issue_lines')->insert([
                    'issue_id'=>$issueId,'item_id'=>$itemId,'pcs'=>0,'carat'=>$cts,'rate_per_carat'=>0,'line_value'=>0,
                    'created_at'=>$issue['date'].' 18:00:00','updated_at'=>$issue['date'].' 18:00:00',
                ]);
                $lineId=(int)$this->db->insertID(); $totalCts+=(float)$cts;
                $events[]=['date'=>$issue['date'],'priority'=>2,'sequence'=>($issueIndex*100)+$lineId,'type'=>'issue','item_id'=>$itemId,'cts'=>(float)$cts,'value'=>0.0,'line_id'=>$lineId];
                $result['lines']++;
            }
            $karigarAccountId=$posting->ensureAccount('KARIGAR','KARIGAR-'.(int)$karigar['id'],'Karigar - '.(string)$karigar['name'],'karigars',(int)$karigar['id']);
            $voucher=$posting->postVoucher([
                'voucher_no'=>$issue['reference'],'voucher_type'=>'DIAMOND_ISSUE','voucher_date'=>$issue['date'],'from_warehouse_id'=>$warehouseId,'from_bin_id'=>$binId,
                'party_id'=>(int)$karigar['id'],'debit_account_id'=>$karigarAccountId,'credit_account_id'=>$warehouseAccountId,'skip_inventory_movement'=>true,
                'remarks'=>'Diamond issue '.$issue['reference'].' | '.$notes,'created_by'=>$adminId,'created_ip'=>'127.0.0.1',
            ],[$this->diamondPoolLine($totalCts,0,(string)$issue['reference'])]);
            $this->db->table('issue_headers')->where('id',$issueId)->update(['account_voucher_id'=>(int)$voucher['voucher_id']]);
            $result['headers']++; $result['cts']+=$totalCts;
        }
        return $result;
    }

    /** @param array<string,array<string,mixed>> $items @param list<array<string,mixed>> $events */
    private function rebuildStock(array $items,array $events): void
    {
        usort($events,static fn(array $a,array $b):int=>[$a['date'],$a['priority'],$a['sequence']]<=>[$b['date'],$b['priority'],$b['sequence']]);
        $state=[];
        foreach ($items as $item) $state[(int)$item['id']]=['cts'=>0.0,'value'=>0.0];
        foreach ($events as $event) {
            $itemId=(int)$event['item_id']; $cts=(float)$event['cts']; $current=$state[$itemId]??['cts'=>0.0,'value'=>0.0];
            if ($event['type']==='issue') {
                // SI/IJ is temporarily -2.910 cts on 3 April in the source sheet;
                // its matching purchase is dated 21 April. Preserve source dates and
                // enforce non-negative balances after the complete historical import.
                $rate=$current['cts']>0?$current['value']/$current['cts']:0.0; $issueValue=round($cts*$rate,2);
                $current['cts']=round($current['cts']-$cts,3); $current['value']=max(0,round($current['value']-$issueValue,2));
                $this->db->table('issue_lines')->where('id',(int)$event['line_id'])->update(['rate_per_carat'=>round($rate,2),'line_value'=>$issueValue]);
            } else {
                $current['cts']=round($current['cts']+$cts,3); $current['value']=round($current['value']+(float)$event['value'],2);
            }
            $state[$itemId]=$current;
        }
        foreach ($state as $itemId=>$balance) {
            if ($balance['cts'] < -0.0005) {
                throw new RuntimeException('Final diamond stock is negative for item #'.$itemId.'.');
            }
            $avg=$balance['cts']>0?$balance['value']/$balance['cts']:0.0;
            $this->db->table('stock')->where('item_id',$itemId)->update([
                'pcs_balance'=>0,'carat_balance'=>$balance['cts'],'avg_cost_per_carat'=>round($avg,2),
                'stock_value'=>round($balance['value'],2),'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function syncIssueVoucherValues(): void
    {
        $rows = $this->db->table('issue_headers ih')
            ->select('ih.account_voucher_id, SUM(il.carat) cts, SUM(il.line_value) line_value', false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->where('ih.account_voucher_id IS NOT NULL', null, false)
            ->groupBy('ih.account_voucher_id')
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $cts = (float) ($row['cts'] ?? 0);
            $value = (float) ($row['line_value'] ?? 0);
            $this->db->table('voucher_lines')
                ->where('voucher_id', (int) $row['account_voucher_id'])
                ->where('line_no', 1)
                ->update([
                    'rate' => $cts > 0 ? round($value / $cts, 3) : 0,
                    'amount' => round($value, 2),
                ]);
        }
    }

    /** @return array<string,mixed> */
    private function ensureDocumentVendor(array $document): array
    {
        $vendor=$this->db->table('vendors')->where('id',(int)$document['vendor_id'])->get()->getRowArray();
        if (! $vendor) throw new RuntimeException('Verified vendor is missing for document #'.$document['id'].'.');
        $this->db->table('vendors')->where('id',(int)$vendor['id'])->update([
            'name'=>$document['vendor_name'],'address'=>$document['vendor_address']?:null,'gstin'=>$document['vendor_gstin']?:null,
            'phone'=>$document['vendor_phone']?:null,'email'=>$document['vendor_email']?:null,'is_active'=>1,'updated_at'=>date('Y-m-d H:i:s'),
        ]);
        return $this->db->table('vendors')->where('id',(int)$vendor['id'])->get()->getRowArray()??$vendor;
    }

    /** @return array<string,mixed> */
    private function resolveUnverifiedVendor(string $party): array
    {
        $target=$party==='KALASHA FINE JEWELS'?'KALASHA FINE JEWELS PRIVATE LIMITED':'Smgj Purchase';
        $row=$this->db->table('vendors')->where('name',$target)->get()->getRowArray();
        if (! $row) throw new RuntimeException('Unverified purchase vendor is missing for '.$party.'.');
        return $row;
    }

    private function insertLinkedPayment(int $purchaseId,array $document,int $adminId): void
    {
        $this->db->table('purchase_bill_payments')->insert([
            'source_type'=>'diamond','source_id'=>$purchaseId,'payment_date'=>(string)($document['payment_date']?:$document['document_date']),
            'amount'=>(float)$document['invoice_amount'],'reference_no'=>(string)$document['invoice_no'],
            'notes'=>'Paid status linked to verified production-document payment; no duplicate cash payment created.',
            'created_by'=>$adminId,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string,mixed> */
    private function diamondPoolLine(float $cts,float $amount,string $remarks): array
    {
        return ['item_type'=>'DIAMOND','item_key'=>'DIAMOND-POOL','material_name'=>'Diamond Pool','qty_pcs'=>0,'qty_cts'=>round($cts,3),
            'qty_weight'=>0,'fine_gold'=>0,'rate'=>$cts>0?round($amount/$cts,3):0,'amount'=>round($amount,2),'remarks'=>$remarks];
    }

    /** @return list<string> */
    private function verifiedDocumentInvoices(): array
    {
        $values=[]; foreach(self::PURCHASES as $purchase) if($purchase['document_invoice']!==null)$values[]=$purchase['document_invoice'];
        return array_values(array_unique($values));
    }

    private function productKey(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/',' ',$value)??''));
    }

    private function nameKey(string $value): string
    {
        return strtoupper(trim(preg_replace('/[^A-Z0-9]+/',' ',strtoupper($value))??''));
    }

    /** @param array<string,mixed> $purchases @param array<string,mixed> $issues */
    private function updateImportSummary(array $purchases,array $issues): void
    {
        if (! $this->db->tableExists('production_import_batches')) return;
        foreach($this->db->table('production_import_batches')->select('id,summary_json')->get()->getResultArray() as $batch){
            $summary=json_decode((string)($batch['summary_json']??'{}'),true); $summary=is_array($summary)?$summary:[];
            $summary['diamond_opening_cts']=self::OPENING_TOTAL;$summary['diamond_purchase_headers']=$purchases['headers'];
            $summary['diamond_purchase_lines']=$purchases['lines'];$summary['diamond_purchase_cts']=round($purchases['cts'],3);
            $summary['diamond_verified_pdf_purchases']=$purchases['verified'];$summary['diamond_pdf_pending_purchases']=$purchases['unverified'];
            $summary['diamond_issue_headers']=$issues['headers'];$summary['diamond_issue_lines']=$issues['lines'];
            $summary['diamond_issue_cts']=round($issues['cts'],3);$summary['diamond_closing_cts']=self::CLOSING_TOTAL;
            $summary['diamond_stock_reconciliation']='April opening only + all Purchase rows - all Issue rows; 10 PDFs verified, 2 prices pending';
            $this->db->table('production_import_batches')->where('id',(int)$batch['id'])->update(['summary_json'=>json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>date('Y-m-d H:i:s')]);
        }
    }

    /** @param array<string,mixed> $purchases @param array<string,mixed> $issues */
    private function assertImportedTotals(array $purchases,array $issues,int $warehouseAccountId): void
    {
        $opening=(float)($this->db->table('diamond_inventory_opening_balances')->selectSum('carat')->get()->getRowArray()['carat']??0);
        $purchase=(float)($this->db->table('purchase_lines')->selectSum('carat')->get()->getRowArray()['carat']??0);
        $issued=(float)($this->db->table('issue_lines')->selectSum('carat')->get()->getRowArray()['carat']??0);
        $closing=(float)($this->db->table('stock')->selectSum('carat_balance')->get()->getRowArray()['carat_balance']??0);
        $balance=$this->db->table('account_balances')->where('account_id',$warehouseAccountId)->where('item_type','DIAMOND')->where('item_key','DIAMOND-POOL')->get()->getRowArray()??[];
        if($purchases['headers']!==12||$purchases['lines']!==17||$purchases['verified']!==10||$purchases['unverified']!==2
            ||$issues['headers']!==28||$issues['lines']!==36||abs(round($opening,3)-self::OPENING_TOTAL)>0.0005
            ||abs(round($purchase,3)-self::PURCHASE_TOTAL)>0.0005||abs(round($issued,3)-self::ISSUE_TOTAL)>0.0005
            ||abs(round($closing,3)-self::CLOSING_TOTAL)>0.0005||abs(round((float)($balance['qty_cts']??0),3)-self::CLOSING_TOTAL)>0.0005){
            throw new RuntimeException('Imported diamond opening, purchases, issues, stock, or account ledger failed reconciliation.');
        }
    }
}
