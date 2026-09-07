<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class KarigarLedgerUiTest extends CIUnitTestCase
{
    public function testKarigarLedgersUseClearBusinessLabelsAndNavigation(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/karigars/show.php');

        $this->assertStringContainsString('Karigar Account Ledgers', $view);
        $this->assertStringContainsString('Material given increases the karigar balance', $view);
        $this->assertStringContainsString('href="#pure-gold-ledger"', $view);
        $this->assertStringContainsString('href="#diamond-ledger"', $view);
        $this->assertStringContainsString('href="#stone-ledger"', $view);
        $this->assertStringContainsString('href="#payment-ledger"', $view);
        $this->assertStringContainsString('Closing with karigar', $view);
        $this->assertStringContainsString('karigar-entry-badge', $view);
    }

    public function testAllAccountTablesRetainDataTableFeatures(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/karigars/show.php');

        foreach (['pure-gold', 'diamond', 'stone', 'payment'] as $ledger) {
            $this->assertStringContainsString('id="karigar-' . $ledger . '-ledger-table"', $view);
        }
        $this->assertGreaterThanOrEqual(6, substr_count($view, 'karigar-ledger-table'));
    }

    public function testKarigarRegisterShowsWorkProgressInsteadOfCommercialRates(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/KarigarController.php');
        $view = (string) file_get_contents(APPPATH . 'Views/admin/karigars/index.php');

        $this->assertStringContainsString('completed_work_count', $controller);
        $this->assertStringContainsString('pending_work_count', $controller);
        $this->assertStringContainsString("IN ('Completed', 'Dispatched')", $controller);
        $this->assertStringContainsString("NOT IN ('Completed', 'Dispatched', 'Cancelled')", $controller);
        $this->assertStringContainsString('Work Completed', $view);
        $this->assertStringContainsString('Work Pending', $view);
        $this->assertStringNotContainsString('Rate / gm', $view);
        $this->assertStringNotContainsString('Wastage %', $view);
    }
}
