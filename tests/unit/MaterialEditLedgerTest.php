<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MaterialEditLedgerTest extends CIUnitTestCase
{
    public function testIssueEditsRefreshOneVoucherInsteadOfPostingReversals(): void
    {
        foreach ([
            'GoldInventory/IssuesController.php',
            'DiamondInventory/IssuesController.php',
            'StoneInventory/IssuesController.php',
        ] as $controller) {
            $source = (string) file_get_contents(APPPATH . 'Controllers/Admin/' . $controller);
            $this->assertStringContainsString('refreshInventoryHeaderVoucher(', $source, $controller);
            $this->assertStringNotContainsString("issue updated', (int) session('admin_id')", $source, $controller);
            $this->assertStringNotContainsString("issue edited', (int) session('admin_id')", $source, $controller);
        }

        $accounting = (string) file_get_contents(APPPATH . 'Services/KarigarMaterialAccountingService.php');
        $posting = (string) file_get_contents(APPPATH . 'Services/PostingService.php');
        $this->assertStringContainsString('public function refreshInventoryHeaderVoucher', $accounting);
        $this->assertStringContainsString('replaceVoucher($replaceVoucherId', $accounting);
        $this->assertStringContainsString('public function replaceVoucher', $posting);
        $this->assertStringContainsString("'UPDATE'", $posting);
    }

    public function testGoldEditsReplaceTheirSourceLedgerRows(): void
    {
        $purchases = (string) file_get_contents(APPPATH . 'Controllers/Admin/GoldInventory/PurchasesController.php');
        $issues = (string) file_get_contents(APPPATH . 'Controllers/Admin/GoldInventory/IssuesController.php');
        $stock = (string) file_get_contents(APPPATH . 'Services/GoldInventory/StockService.php');

        $this->assertStringContainsString("'record_ledger' => false", $purchases);
        $this->assertStringContainsString("clearLedgerEntriesForReference('gold_inventory_purchase_headers'", $purchases);
        $this->assertStringContainsString('recalculateLedgerBalances()', $purchases);
        $this->assertStringContainsString("'record_ledger' => false", $issues);
        $this->assertStringContainsString("clearLedgerEntriesForReference('gold_inventory_issue_headers'", $issues);
        $this->assertStringContainsString('recalculateLedgerBalances()', $issues);
        $this->assertStringContainsString('public function clearLedgerEntriesForReference', $stock);
        $this->assertStringContainsString('public function recalculateLedgerBalances', $stock);
    }

    public function testCleanupMigrationPreservesSourceTransactionsAndRebuildsCurrentRows(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-31-000074_CleanMaterialEditLedgerNoise.php'
        );

        $this->assertStringContainsString('cleanEditReversalVouchers', $migration);
        $this->assertStringContainsString('rebuildCurrentGoldSourceEntries', $migration);
        $this->assertStringContainsString("'Gold issue edited'", $migration);
        $this->assertStringContainsString("SELECT h.purchase_date, 'purchase'", $migration);
        $this->assertStringContainsString("SELECT h.issue_date, 'issue'", $migration);
        $this->assertStringNotContainsString('DELETE FROM gold_inventory_purchase_headers', $migration);
        $this->assertStringNotContainsString('DELETE FROM gold_inventory_issue_headers', $migration);
    }
}
