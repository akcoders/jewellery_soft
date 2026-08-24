<?php

namespace App\Database\Migrations;

use App\Services\ProductionPurchaseWorkbookService;
use CodeIgniter\Database\Migration;
use RuntimeException;

class CorrectVerifiedGoldPurchases extends Migration
{
    private const EXPECTED_INVOICES = 18;
    private const EXPECTED_LINES = 19;

    public function up()
    {
        if (! $this->db->tableExists('gold_inventory_purchase_headers')
            || ! $this->db->tableExists('gold_inventory_purchase_lines')
            || ! $this->db->tableExists('production_purchase_documents')) {
            return;
        }

        $this->addHeaderColumns();
        $this->addLineColumns();
        $this->addIndexes();
        $this->reconcileVerifiedGoldInvoices();
    }

    /**
     * The source-ledger pseudo-purchases are replaced with verified invoices.
     * They cannot be reconstructed reliably by a rollback, so this data repair
     * is intentionally irreversible.
     */
    public function down()
    {
    }

    private function addHeaderColumns(): void
    {
        $columns = [
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'purchase_date'],
            'production_document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'vendor_id'],
            'supplier_address' => ['type' => 'TEXT', 'null' => true, 'after' => 'supplier_name'],
            'supplier_gstin' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true, 'after' => 'supplier_address'],
            'supplier_phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'supplier_gstin'],
            'supplier_email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'supplier_phone'],
            'due_date' => ['type' => 'DATE', 'null' => true, 'after' => 'invoice_no'],
            'place_of_supply' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'due_date'],
            'purchase_description' => ['type' => 'TEXT', 'null' => true, 'after' => 'place_of_supply'],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'purchase_description'],
            'cgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'taxable_amount'],
            'cgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'cgst_rate'],
            'sgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'cgst_amount'],
            'sgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'sgst_rate'],
            'igst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'sgst_amount'],
            'igst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'igst_rate'],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'igst_amount'],
            'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'gst_amount'],
            'invoice_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'round_off_amount'],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending', 'after' => 'invoice_total'],
            'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'payment_status'],
            'payment_date' => ['type' => 'DATE', 'null' => true, 'after' => 'paid_amount'],
            'stock_posted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'payment_date'],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'gold_inventory_purchase_headers')) {
                $this->forge->addColumn('gold_inventory_purchase_headers', [$name => $definition]);
            }
        }
    }

    private function addLineColumns(): void
    {
        foreach ([
            'description' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'item_id'],
            'hsn_sac' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'description'],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => 'GMS', 'after' => 'hsn_sac'],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'gold_inventory_purchase_lines')) {
                $this->forge->addColumn('gold_inventory_purchase_lines', [$name => $definition]);
            }
        }

        $this->forge->modifyColumn('gold_inventory_purchase_lines', [
            'rate_per_gm' => ['name' => 'rate_per_gm', 'type' => 'DECIMAL', 'constraint' => '18,3'],
        ]);
    }

    private function addIndexes(): void
    {
        $indexes = $this->db->getIndexData('gold_inventory_purchase_headers');
        if (! isset($indexes['idx_gold_purchase_vendor'])) {
            $this->db->query('ALTER TABLE `gold_inventory_purchase_headers` ADD INDEX `idx_gold_purchase_vendor` (`vendor_id`)');
        }
        if (! isset($indexes['uq_gold_purchase_document'])) {
            $this->db->query('ALTER TABLE `gold_inventory_purchase_headers` ADD UNIQUE INDEX `uq_gold_purchase_document` (`production_document_id`)');
        }
    }

    private function reconcileVerifiedGoldInvoices(): void
    {
        $verified = array_values(array_filter(
            (new ProductionPurchaseWorkbookService())->purchases(),
            static fn(array $purchase): bool => strtolower((string) ($purchase['category_label'] ?? '')) === 'gold'
        ));
        if (count($verified) !== self::EXPECTED_INVOICES) {
            throw new RuntimeException('Expected exactly 18 verified gold invoices.');
        }

        $documents = [];
        foreach ($this->db->table('production_purchase_documents')->where('category', 'gold')->get()->getResultArray() as $document) {
            $documents[$this->normalizePath((string) ($document['source_path'] ?? ''))] = $document;
        }
        if (count($documents) !== self::EXPECTED_INVOICES) {
            throw new RuntimeException('Expected exactly 18 stored gold PDF records.');
        }

        $item = $this->db->table('gold_inventory_items')
            ->groupStart()->where('purity_code', '24K')->orWhere('purity_percent >=', 99.99)->groupEnd()
            ->orderBy('id', 'ASC')->get()->getRowArray();
        if (! $item) {
            throw new RuntimeException('The 24K gold inventory item is missing.');
        }
        $itemId = (int) $item['id'];
        $stock = $this->db->table('gold_inventory_stock')->where('item_id', $itemId)->get()->getRowArray();
        $closingWeight = round((float) ($stock['weight_balance_gm'] ?? 0), 3);
        $closingFine = round((float) ($stock['fine_balance_gm'] ?? $closingWeight), 3);
        $locationId = $this->resolveLocationId();
        $adminId = $this->resolveAdminId();
        $now = date('Y-m-d H:i:s');

        $legacyRows = $this->db->table('gold_inventory_purchase_headers')
            ->select('id')
            ->groupStart()
                ->like('notes', 'Imported from BABU GOLD -26-27.xlsx', 'after')
                ->orWhere('production_document_id IS NOT NULL', null, false)
            ->groupEnd()->get()->getResultArray();
        $legacyIds = array_values(array_filter(array_map('intval', array_column($legacyRows, 'id'))));

        $this->db->transException(true)->transStart();
        $this->deleteLegacyGoldPurchaseRecords($legacyIds);
        $this->deleteLegacyGoldStockPostings();

        $createdIds = [];
        $taxableTotal = 0.0;
        $weightTotal = 0.0;
        $lineCount = 0;
        foreach ($verified as $purchase) {
            $path = $this->normalizePath((string) $purchase['source_path']);
            $document = $documents[$path] ?? null;
            if (! $document) {
                throw new RuntimeException('Stored gold PDF was not found for ' . $path);
            }

            $this->db->table('gold_inventory_purchase_headers')->insert([
                'purchase_date' => $purchase['invoice_date'],
                'vendor_id' => (int) ($document['vendor_id'] ?? 0) ?: null,
                'production_document_id' => (int) $document['id'],
                'supplier_name' => $purchase['vendor_name'],
                'supplier_address' => $purchase['vendor_address'] ?: null,
                'supplier_gstin' => $purchase['vendor_gstin'] ?: null,
                'supplier_phone' => $purchase['vendor_phone'] ?: null,
                'supplier_email' => $purchase['vendor_email'] ?: null,
                'invoice_no' => $purchase['invoice_no'],
                'due_date' => $purchase['due_date'],
                'place_of_supply' => $purchase['place_of_supply'] ?: null,
                'purchase_description' => $purchase['description'] ?: null,
                'taxable_amount' => round((float) $purchase['taxable_amount'], 2),
                'cgst_rate' => $purchase['cgst_rate'],
                'cgst_amount' => round((float) $purchase['cgst_amount'], 2),
                'sgst_rate' => $purchase['sgst_rate'],
                'sgst_amount' => round((float) $purchase['sgst_amount'], 2),
                'igst_rate' => $purchase['igst_rate'],
                'igst_amount' => round((float) $purchase['igst_amount'], 2),
                'gst_amount' => round((float) $purchase['gst_amount'], 2),
                'round_off_amount' => round((float) $purchase['round_off_amount'], 2),
                'invoice_total' => round((float) $purchase['invoice_total'], 2),
                'payment_status' => $purchase['payment_status'],
                'paid_amount' => round((float) $purchase['paid_amount'], 2),
                'payment_date' => $purchase['payment_date'],
                'stock_posted' => 0,
                'location_id' => $locationId,
                'notes' => 'Verified historical gold invoice from production_purchase_register.xlsx; stock is included in the live opening balance.',
                'created_by' => $adminId,
                'created_at' => (string) $purchase['invoice_date'] . ' 18:00:00',
                'updated_at' => $now,
            ]);
            $purchaseId = (int) $this->db->insertID();
            $createdIds[] = $purchaseId;

            foreach ((array) $purchase['line_items'] as $line) {
                $quantity = round((float) ($line['quantity'] ?? 0), 3);
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $this->db->table('gold_inventory_purchase_lines')->insert([
                    'purchase_id' => $purchaseId,
                    'item_id' => $itemId,
                    'description' => trim((string) ($line['description'] ?? '')) ?: null,
                    'hsn_sac' => trim((string) ($line['hsn_sac'] ?? '')) ?: null,
                    'unit' => strtoupper(trim((string) ($line['unit'] ?? 'GMS'))) ?: 'GMS',
                    'weight_gm' => $quantity,
                    'fine_weight_gm' => $quantity,
                    'rate_per_gm' => round((float) ($line['rate'] ?? 0), 3),
                    'line_value' => $amount,
                    'created_at' => (string) $purchase['invoice_date'] . ' 18:00:00',
                    'updated_at' => $now,
                ]);
                $weightTotal += $quantity;
                $lineCount++;
            }
            $taxableTotal += (float) $purchase['taxable_amount'];
        }

        if (count($createdIds) !== self::EXPECTED_INVOICES || $lineCount !== self::EXPECTED_LINES) {
            throw new RuntimeException('The verified gold invoice import did not reconcile to 18 invoices and 19 lines.');
        }

        $this->replaceLiveOpeningBalance($itemId, $locationId, $closingWeight, $closingFine, $weightTotal, $taxableTotal, $adminId);
        $this->rebuildAccountBalances();
        $this->updateImportSummary($lineCount, $weightTotal, $taxableTotal);
        $this->db->transComplete();
    }

    /** @param list<int> $ids */
    private function deleteLegacyGoldPurchaseRecords(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        if ($this->db->tableExists('purchase_bill_payments')) {
            $this->db->table('purchase_bill_payments')->where('source_type', 'gold')->whereIn('source_id', $ids)->delete();
        }
        if ($this->db->tableExists('account_payments')) {
            $this->db->table('account_payments')->where('bill_source_type', 'gold')->whereIn('bill_source_id', $ids)->delete();
        }
        if ($this->db->tableExists('gold_inventory_ledger_entries')) {
            $this->db->table('gold_inventory_ledger_entries')
                ->where('reference_table', 'gold_inventory_purchase_headers')->whereIn('reference_id', $ids)->delete();
        }
        $this->db->table('gold_inventory_purchase_lines')->whereIn('purchase_id', $ids)->delete();
        $this->db->table('gold_inventory_purchase_headers')->whereIn('id', $ids)->delete();
    }

    private function deleteLegacyGoldStockPostings(): void
    {
        if ($this->db->tableExists('gold_inventory_ledger_entries')) {
            $this->db->table('gold_inventory_ledger_entries')
                ->groupStart()
                    ->where('reference_table', 'production_gold_movements')
                    ->orGroupStart()
                        ->where('txn_type', 'OPENING_BALANCE')
                        ->where('notes', 'Live opening balance retained after historical gold invoices were corrected.')
                    ->groupEnd()
                ->groupEnd()->delete();
        }

        if (! $this->db->tableExists('vouchers')) {
            return;
        }
        $rows = $this->db->table('vouchers')->select('id')
            ->where('voucher_type', 'GOLD_STOCK_IN')->like('voucher_no', 'IMP-GSTK-', 'after')
            ->get()->getResultArray();
        $ids = array_values(array_filter(array_map('intval', array_column($rows, 'id'))));
        if ($ids === []) {
            return;
        }
        foreach (['ledger_entries', 'voucher_lines'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->whereIn('voucher_id', $ids)->delete();
            }
        }
        if ($this->db->tableExists('voucher_reversals')) {
            $this->db->table('voucher_reversals')
                ->groupStart()->whereIn('original_voucher_id', $ids)->orWhereIn('reversal_voucher_id', $ids)->groupEnd()->delete();
        }
        $this->db->table('vouchers')->whereIn('id', $ids)->delete();
    }

    private function replaceLiveOpeningBalance(
        int $itemId,
        ?int $locationId,
        float $closingWeight,
        float $closingFine,
        float $purchasedWeight,
        float $taxableTotal,
        ?int $adminId
    ): void {
        $averageCost = $purchasedWeight > 0 ? round($taxableTotal / $purchasedWeight, 2) : 0.0;
        $stockValue = round($closingWeight * $averageCost, 2);
        if ($this->db->tableExists('gold_inventory_stock')) {
            $exists = $this->db->table('gold_inventory_stock')->where('item_id', $itemId)->countAllResults() > 0;
            $values = [
                'weight_balance_gm' => $closingWeight,
                'fine_balance_gm' => $closingFine,
                'avg_cost_per_gm' => $averageCost,
                'stock_value' => $stockValue,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($exists) {
                $this->db->table('gold_inventory_stock')->where('item_id', $itemId)->update($values);
            } else {
                $this->db->table('gold_inventory_stock')->insert(['item_id' => $itemId] + $values);
            }
        }

        if ($this->db->tableExists('gold_inventory_ledger_entries') && ($closingWeight > 0 || $closingFine > 0)) {
            $this->db->table('gold_inventory_ledger_entries')->insert([
                'txn_date' => date('Y-m-d'),
                'txn_type' => 'OPENING_BALANCE',
                'reference_table' => 'gold_inventory_stock',
                'reference_id' => $itemId,
                'order_id' => null,
                'karigar_id' => null,
                'location_id' => $locationId,
                'item_id' => $itemId,
                'debit_weight_gm' => $closingWeight,
                'credit_weight_gm' => 0,
                'debit_fine_gm' => $closingFine,
                'credit_fine_gm' => 0,
                'balance_weight_gm' => $closingWeight,
                'balance_fine_gm' => $closingFine,
                'rate_per_gm' => $averageCost,
                'line_value' => $stockValue,
                'notes' => 'Live opening balance retained after historical gold invoices were corrected.',
                'created_by' => $adminId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function rebuildAccountBalances(): void
    {
        if (! $this->db->tableExists('account_balances') || ! $this->db->tableExists('ledger_entries')) {
            return;
        }
        $this->db->query('DELETE FROM `account_balances`');
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $this->db->query(
            "INSERT INTO account_balances
                (account_id, item_type, item_key, qty_pcs, qty_cts, qty_weight, fine_gold_qty, created_at, updated_at)
             SELECT account_id, item_type, item_key,
                    ROUND(SUM(qty_pcs),3), ROUND(SUM(qty_cts),3),
                    ROUND(SUM(qty_weight),3), ROUND(SUM(fine_gold_qty),3), {$now}, {$now}
             FROM (
                 SELECT le.debit_account_id account_id, le.item_type, le.item_key,
                        le.qty_pcs, le.qty_cts, le.qty_weight, le.fine_gold_qty
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id
                 WHERE v.status = 'Posted'
                 UNION ALL
                 SELECT le.credit_account_id account_id, le.item_type, le.item_key,
                        -le.qty_pcs, -le.qty_cts, -le.qty_weight, -le.fine_gold_qty
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id
                 WHERE v.status = 'Posted'
             ) balances
             WHERE account_id IS NOT NULL AND account_id > 0
             GROUP BY account_id, item_type, item_key"
        );
    }

    private function updateImportSummary(int $lineCount, float $weight, float $taxable): void
    {
        if (! $this->db->tableExists('production_import_batches')) {
            return;
        }
        foreach ($this->db->table('production_import_batches')->select('id, summary_json')->get()->getResultArray() as $batch) {
            $summary = json_decode((string) ($batch['summary_json'] ?? '{}'), true);
            $summary = is_array($summary) ? $summary : [];
            $summary['gold_purchase_invoices'] = self::EXPECTED_INVOICES;
            $summary['gold_purchase_lines'] = $lineCount;
            $summary['gold_purchase_weight_gm'] = round($weight, 3);
            $summary['gold_purchase_taxable_amount'] = round($taxable, 2);
            $summary['gold_purchase_reconciliation'] = 'Verified PDF register linked to native gold purchases';
            $this->db->table('production_import_batches')->where('id', (int) $batch['id'])->update([
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function resolveLocationId(): ?int
    {
        if (! $this->db->tableExists('inventory_locations')) {
            return null;
        }
        $row = $this->db->table('gold_inventory_purchase_headers')
            ->select('location_id')->where('location_id IS NOT NULL', null, false)->orderBy('id', 'ASC')->get()->getRowArray();
        if ($row && (int) ($row['location_id'] ?? 0) > 0) {
            return (int) $row['location_id'];
        }
        $row = $this->db->table('inventory_locations')->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    private function resolveAdminId(): ?int
    {
        if (! $this->db->tableExists('admin_users')) {
            return null;
        }
        $row = $this->db->table('admin_users')->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    private function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? '', '/');
    }
}
