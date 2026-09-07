<?php

namespace Tests\Unit;

use App\Services\AdminAlertCenterService;
use CodeIgniter\Test\CIUnitTestCase;

final class AdminAlertCenterTest extends CIUnitTestCase
{
    public function testSuccessfulLoginRequestsOneTimeAlertModal(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/AuthController.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');

        $this->assertStringContainsString("setFlashdata('show_admin_alert_center', true)", $controller);
        $this->assertStringContainsString("getFlashdata('show_admin_alert_center')", $layout);
        $this->assertStringContainsString("data-auto-open", $layout);
        $this->assertStringContainsString("bootstrap.Modal.getOrCreateInstance", $layout);
    }

    public function testHeaderBellAndCategorizedAlertSectionsAreAvailable(): void
    {
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');
        $partial = (string) file_get_contents(APPPATH . 'Views/admin/partials/alert_center.php');

        $this->assertStringContainsString('admin-alert-trigger__count', $layout);
        $this->assertStringContainsString('#adminAlertCenterModal', $layout);
        $this->assertStringContainsString('Pending payments', $partial);
        $this->assertStringContainsString('Pending orders', $partial);
        $this->assertStringContainsString('Follow-ups due', $partial);
        $this->assertStringContainsString('thumbnail_url', $partial);
    }

    public function testAlertsUseActionableBusinessDataAndRespectPermissions(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/AdminAlertCenterService.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');

        $this->assertStringContainsString("whereNotIn('o.status', ['Completed', 'Dispatched', 'Cancelled'])", $service);
        $this->assertStringContainsString("tableExists('purchase_bill_payments')", $service);
        $this->assertStringContainsString("tableExists('labour_bill_payments')", $service);
        $this->assertStringContainsString('next_followup_date', $service);
        $this->assertStringContainsString('new \\App\\Services\\AdminAlertCenterService())->build($canOrders, $canAccounts)', $layout);
    }

    public function testSummaryCalculatesOpenOrdersDueFollowupsAndActualUnpaidBalances(): void
    {
        $db = \Config\Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
            'foreignKeys' => true,
        ], false);

        $schema = [
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT, order_name TEXT, status TEXT, due_date TEXT, followup_due_at TEXT, created_at TEXT, deleted_at TEXT)',
            'CREATE TABLE order_followups (id INTEGER PRIMARY KEY, order_id INTEGER, stage TEXT, next_followup_date TEXT, followup_taken_on TEXT)',
            'CREATE TABLE vendors (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE purchase_headers (id INTEGER PRIMARY KEY, vendor_id INTEGER, purchase_date TEXT, due_date TEXT, invoice_no TEXT, supplier_name TEXT, invoice_total REAL, paid_amount REAL)',
            'CREATE TABLE purchase_lines (id INTEGER PRIMARY KEY, purchase_id INTEGER, line_value REAL)',
            'CREATE TABLE purchase_bill_payments (id INTEGER PRIMARY KEY, source_type TEXT, source_id INTEGER, amount REAL)',
            'CREATE TABLE karigars (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE labour_bills (id INTEGER PRIMARY KEY, bill_no TEXT, bill_date TEXT, due_date TEXT, total_amount REAL, karigar_id INTEGER)',
            'CREATE TABLE labour_bill_payments (id INTEGER PRIMARY KEY, labour_bill_id INTEGER, amount REAL)',
        ];
        foreach ($schema as $statement) {
            $db->query($statement);
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $db->table('orders')->insert(['id' => 1, 'order_no' => 'ORD-1', 'order_name' => 'Open ring', 'status' => 'In Production', 'due_date' => $yesterday, 'followup_due_at' => $yesterday . ' 12:00:00']);
        $db->table('orders')->insert(['id' => 2, 'order_no' => 'ORD-2', 'order_name' => 'Completed ring', 'status' => 'Completed', 'due_date' => $yesterday]);
        $db->table('order_followups')->insert(['id' => 1, 'order_id' => 1, 'stage' => 'In Production', 'next_followup_date' => $yesterday . ' 12:00:00']);
        $db->table('vendors')->insert(['id' => 1, 'name' => 'Diamond Vendor']);
        $db->table('purchase_headers')->insert(['id' => 1, 'vendor_id' => 1, 'purchase_date' => $yesterday, 'due_date' => $yesterday, 'invoice_no' => 'INV-1', 'supplier_name' => 'Diamond Vendor', 'invoice_total' => 1000, 'paid_amount' => 100]);
        $db->table('purchase_lines')->insert(['id' => 1, 'purchase_id' => 1, 'line_value' => 1000]);
        $db->table('purchase_bill_payments')->insert(['id' => 1, 'source_type' => 'diamond', 'source_id' => 1, 'amount' => 200]);
        $db->table('karigars')->insert(['id' => 1, 'name' => 'Karigar One']);
        $db->table('labour_bills')->insert(['id' => 1, 'bill_no' => 'LB-1', 'bill_date' => $yesterday, 'due_date' => $yesterday, 'total_amount' => 500, 'karigar_id' => 1]);
        $db->table('labour_bill_payments')->insert(['id' => 1, 'labour_bill_id' => 1, 'amount' => 100]);

        $summary = (new AdminAlertCenterService($db))->build(true, true);

        $this->assertSame(1, $summary['orders']['count']);
        $this->assertSame(1, $summary['orders']['overdue_count']);
        $this->assertSame(1, $summary['followups']['count']);
        $this->assertSame(1, $summary['followups']['overdue_count']);
        $this->assertSame(2, $summary['payments']['count']);
        $this->assertSame(1200.0, $summary['payments']['amount']);
        $this->assertSame(4, $summary['total_count']);
    }
}
