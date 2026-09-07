<?php

namespace Tests\Unit;

use App\Services\SalesIntelligenceService;
use CodeIgniter\Test\CIUnitTestCase;

final class WholesaleStuddedJewellerySalesTest extends CIUnitTestCase
{
    public function testRetailRoutesAndMenuAreReplacedByStuddedJewellery(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');

        $this->assertStringContainsString("studded-jewellery/sale-bills', 'Admin\\ShowroomSalesController::index", $routes);
        $this->assertStringContainsString("studded-jewellery/dashboard', 'Admin\\ShowroomSalesController::dashboard", $routes);
        $this->assertStringContainsString('Studded Jewellery', $layout);
        $this->assertStringContainsString('Jewellery Inventory', $layout);
        $this->assertStringNotContainsString('Retail Showroom', $layout);
        $this->assertStringNotContainsString('$routes->get(\'showrooms\'', $routes);
        $this->assertStringNotContainsString('$routes->get(\'showroom-stock\'', $routes);
        $this->assertStringNotContainsString('$routes->get(\'showroom-sales\'', $routes);
    }

    public function testWholesaleSchemaStoresWeightsRatesTaxAndPackingReference(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-07-000081_ConvertRetailShowroomSalesToWholesaleStuddedSales.php'
        );
        foreach ([
            'packing_list_id', 'gst_master_id', 'tax_breakup_json', 'total_gold_weight',
            'total_diamond_weight', 'total_stone_weight', 'gold_rate', 'diamond_rate',
            'stone_rate', 'other_amount', 'round_off_amount',
        ] as $column) {
            $this->assertStringContainsString("'{$column}'", $migration);
        }
        $this->assertStringContainsString("'null' => true", $migration);
    }

    public function testSaleBuilderUsesInventoryImagesAndGstMasterCalculation(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/ShowroomSalesController.php');
        $form = (string) file_get_contents(APPPATH . 'Views/admin/showroom_sales/form.php');

        $this->assertStringContainsString('TaxMasterService', $controller);
        $this->assertStringContainsString('$this->packingListModel->insert', $controller);
        $this->assertStringContainsString("'showroom_id' => null", $controller);
        $this->assertStringContainsString("'showroom_stock_status' => 'SOLD'", $controller);
        $this->assertStringNotContainsString("'salesperson_employee_id' => 'required", $controller);
        $this->assertStringContainsString('data-jewellery=', $form);
        $this->assertStringContainsString('templateResult', $form);
        $this->assertStringContainsString('Gold Rate / gm', $form);
        $this->assertStringContainsString('GST Master', $form);
    }

    public function testEverySaleExposesGeneratedInvoiceAndCombinedPackingList(): void
    {
        $index = (string) file_get_contents(APPPATH . 'Views/admin/showroom_sales/index.php');
        $invoice = (string) file_get_contents(APPPATH . 'Views/pdf/wholesale_sale_invoice.php');
        $packing = (string) file_get_contents(APPPATH . 'Views/pdf/wholesale_sale_packing_list.php');

        $this->assertStringContainsString("/invoice') ?>?download=1", $index);
        $this->assertStringContainsString("/packing-list') ?>?download=1", $index);
        $this->assertStringContainsString('Tax Invoice', $invoice);
        $this->assertStringContainsString('Studded Diamond', $invoice);
        $this->assertStringContainsString('tax_components', $invoice);
        $this->assertStringContainsString('embedded_image', $packing);
        $this->assertStringContainsString('combined packing list', strtolower($packing));
    }

    public function testSalesIntelligenceComparesBillsWithoutCountingPaymentsAsExpenseAgain(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/SalesIntelligenceService.php');
        $dashboard = (string) file_get_contents(APPPATH . 'Views/admin/showroom_sales/dashboard.php');

        $this->assertStringContainsString("'sales_total'", $service);
        $this->assertStringContainsString("'purchase_total'", $service);
        $this->assertStringContainsString("'labour_expense'", $service);
        $this->assertStringContainsString("->where('voucher_type', 'expenditure')", $service);
        $this->assertStringNotContainsString("table('account_payments')", $service);
        $this->assertStringContainsString('Purchase & Expense vs Sale', $dashboard);
        $this->assertStringContainsString('Monthly business movement', $dashboard);
        $this->assertStringContainsString('Intelligent observations', $dashboard);
    }

    public function testSalesIntelligenceCalculatesBillBasisOperatingComparison(): void
    {
        $db = \Config\Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
            'foreignKeys' => true,
        ], false);
        foreach ([
            'CREATE TABLE showroom_sales (id INTEGER PRIMARY KEY, sale_no TEXT, sale_date TEXT, customer_id INTEGER, invoice_id INTEGER, total_qty REAL, total_gold_weight REAL, total_diamond_weight REAL, total_stone_weight REAL, taxable_amount REAL, gst_amount REAL, total_amount REAL, sale_status TEXT)',
            'CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE customer_receipts (id INTEGER PRIMARY KEY, invoice_id INTEGER, amount REAL)',
            'CREATE TABLE purchase_headers (id INTEGER PRIMARY KEY, purchase_date TEXT, invoice_total REAL, taxable_amount REAL, gst_amount REAL, round_off_amount REAL, production_document_id INTEGER)',
            'CREATE TABLE purchase_lines (id INTEGER PRIMARY KEY, purchase_id INTEGER, line_value REAL)',
            'CREATE TABLE labour_bills (id INTEGER PRIMARY KEY, bill_date TEXT, total_amount REAL)',
            'CREATE TABLE account_journal_vouchers (id INTEGER PRIMARY KEY, voucher_date TEXT, voucher_type TEXT, expense_head TEXT, amount REAL, status TEXT)',
        ] as $statement) {
            $db->query($statement);
        }

        $db->table('customers')->insert(['id' => 1, 'name' => 'Wholesale Customer']);
        $db->table('showroom_sales')->insert([
            'id' => 1, 'sale_no' => 'SB-1', 'sale_date' => '2026-09-10', 'customer_id' => 1,
            'invoice_id' => 10, 'total_qty' => 2, 'total_gold_weight' => 8.5,
            'total_diamond_weight' => 1.2, 'total_stone_weight' => 0.4,
            'taxable_amount' => 1450, 'gst_amount' => 50, 'total_amount' => 1500,
            'sale_status' => 'Completed',
        ]);
        $db->table('customer_receipts')->insert(['id' => 1, 'invoice_id' => 10, 'amount' => 500]);
        $db->table('purchase_headers')->insert(['id' => 1, 'purchase_date' => '2026-09-05', 'invoice_total' => 600, 'taxable_amount' => 582, 'gst_amount' => 18, 'round_off_amount' => 0]);
        $db->table('purchase_lines')->insert(['id' => 1, 'purchase_id' => 1, 'line_value' => 582]);
        $db->table('labour_bills')->insert(['id' => 1, 'bill_date' => '2026-09-07', 'total_amount' => 200]);
        $db->table('account_journal_vouchers')->insert(['id' => 1, 'voucher_date' => '2026-09-08', 'voucher_type' => 'expenditure', 'expense_head' => 'Freight', 'amount' => 100, 'status' => 'Posted']);
        $db->table('account_journal_vouchers')->insert(['id' => 2, 'voucher_date' => '2026-09-08', 'voucher_type' => 'expenditure', 'expense_head' => 'Draft expense', 'amount' => 900, 'status' => 'Draft']);

        $data = (new SalesIntelligenceService($db))->build('2026-09-01', '2026-09-30');

        $this->assertSame(1, $data['summary']['sale_count']);
        $this->assertSame(1500.0, $data['summary']['sales_total']);
        $this->assertSame(500.0, $data['summary']['received']);
        $this->assertSame(1000.0, $data['summary']['outstanding']);
        $this->assertSame(600.0, $data['summary']['purchase_total']);
        $this->assertSame(200.0, $data['summary']['labour_expense']);
        $this->assertSame(100.0, $data['summary']['other_expense']);
        $this->assertSame(900.0, $data['summary']['recorded_cost']);
        $this->assertSame(600.0, $data['summary']['operating_spread']);
        $this->assertSame('Wholesale Customer', $data['top_customers'][0]['customer_name']);
        $this->assertSame(1500.0, $data['monthly'][0]['sales']);
        $this->assertSame(600.0, $data['monthly'][0]['purchases']);
        $this->assertSame(300.0, $data['monthly'][0]['expenses']);
    }
}
