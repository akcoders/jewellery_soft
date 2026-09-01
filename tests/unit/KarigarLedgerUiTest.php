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
}
