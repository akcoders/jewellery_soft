<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AllTransactionsReportTest extends CIUnitTestCase
{
    public function testUnifiedReportReadsEveryOperationalTransactionSource(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/ReportController.php');

        $this->assertStringContainsString("'from' => trim((string) (\$this->request->getGet('from') ?? ''))", $controller);
        $this->assertStringContainsString("'to' => trim((string) (\$this->request->getGet('to') ?? ''))", $controller);
        $this->assertStringContainsString('transactionRowsOpeningBalances($filters)', $controller);
        $this->assertStringContainsString('transactionRowsReceivings($filters)', $controller);
        $this->assertStringContainsString("table('order_status_history h')", $controller);
        $this->assertStringContainsString("table('account_payments ap')", $controller);
        $this->assertStringContainsString("table('vendor_payments vp')", $controller);
        $this->assertStringContainsString("table('purchase_bill_payments p')", $controller);
        $this->assertStringContainsString("table('labour_bill_payments lp')", $controller);
        $this->assertStringContainsString("table('karigar_payment_ledgers kp')", $controller);
        $this->assertStringContainsString('uniquePaymentRows($paymentRows)', $controller);
        $this->assertStringContainsString("LOWER(om.movement_type) != 'receive'", $controller);
        $this->assertStringContainsString("db_connect()->escape((string) \$filters['from'])", $controller);
        $this->assertStringContainsString("db_connect()->escape((string) \$filters['to'])", $controller);
    }

    public function testProfessionalRegisterKeepsSearchableFiltersAndDataTablePaging(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/reports/transactions.php');

        $this->assertStringContainsString('name="transaction_group"', $view);
        $this->assertStringContainsString('name="party_type"', $view);
        $this->assertGreaterThanOrEqual(7, substr_count($view, 'js-searchable-select'));
        $this->assertStringContainsString('Blank shows all history', $view);
        $this->assertStringContainsString('data-dt-page-length="25"', $view);
        $this->assertStringContainsString('tx-activity', $view);
        $this->assertStringContainsString('tx-material', $view);
        $this->assertStringContainsString('tx-status', $view);
        $this->assertStringContainsString('tx-weight-list', $view);
        $this->assertStringContainsString('Auto-synced from live entries', $view);
    }
}
