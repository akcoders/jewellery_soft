<?php

namespace Tests\Unit;

use App\Services\BusinessAnalyticsService;
use CodeIgniter\Test\CIUnitTestCase;

final class BusinessAnalyticsPwaTest extends CIUnitTestCase
{
    public function testDashboardReturnsUsableDataWhenOptionalModulesAreMissing(): void
    {
        $db = db_connect('tests', false);
        foreach (['orders', 'customers', 'karigars'] as $table) {
            $db->query('DROP TABLE IF EXISTS db_' . $table);
        }
        $db->query('CREATE TABLE db_customers (id INTEGER PRIMARY KEY, name VARCHAR(100))');
        $db->query('CREATE TABLE db_karigars (id INTEGER PRIMARY KEY, name VARCHAR(100))');
        $db->query('CREATE TABLE db_orders (id INTEGER PRIMARY KEY, order_no VARCHAR(50), order_name VARCHAR(100), status VARCHAR(30), order_type VARCHAR(30), created_at DATETIME, updated_at DATETIME, due_date DATE, approximate_price DECIMAL(14,2), advance_amount DECIMAL(14,2), customer_id INTEGER, assigned_karigar_id INTEGER, priority VARCHAR(20), receiving_status VARCHAR(24), received_at DATETIME, order_category_id INTEGER, deleted_at DATETIME)');
        $db->table('customers')->insert(['id' => 1, 'name' => 'Test Customer']);
        $db->table('karigars')->insert(['id' => 1, 'name' => 'Test Karigar']);
        $db->table('orders')->insert([
            'id' => 1, 'order_no' => 'ORD-1', 'order_name' => 'Ring', 'status' => 'Completed',
            'order_type' => 'Sales', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at' => date('Y-m-d H:i:s'), 'due_date' => date('Y-m-d'),
            'approximate_price' => 100000, 'advance_amount' => 25000, 'customer_id' => 1,
            'assigned_karigar_id' => 1, 'priority' => 'High', 'receiving_status' => 'received',
            'received_at' => date('Y-m-d H:i:s'), 'order_category_id' => null, 'deleted_at' => null,
        ]);

        $data = (new BusinessAnalyticsService($db))->dashboard(30);

        $this->assertSame(1, $data['overview']['orders']);
        $this->assertSame(1, $data['overview']['completed']);
        $this->assertSame(100000.0, $data['overview']['order_value']);
        $this->assertArrayHasKey('purchases', $data);
        $this->assertArrayHasKey('requirements', $data);
        $this->assertArrayHasKey('employee_performance', $data);
    }

    public function testAnalyticsBackendDegradesBySectionInsteadOfReturningServerError(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/BusinessAnalyticsService.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/AnalyticsController.php');

        foreach (['orders', 'inventory', 'purchases', 'requirements', 'employee performance', 'material flow'] as $section) {
            $this->assertStringContainsString("safe('{$section}'", $service);
        }
        $this->assertStringContainsString("'data_warnings' => \$this->warnings", $service);
        $this->assertStringContainsString('catch (Throwable $e)', $controller);
        $this->assertStringContainsString("temporarily unavailable. Please retry.', 503", $controller);
    }

    public function testSeparateAnalyticsPwaHasCompleteReadOnlyBusinessWorkspace(): void
    {
        $root = ROOTPATH . 'public/analytics/';
        $html = (string) file_get_contents($root . 'index.html');
        $script = (string) file_get_contents($root . 'app.js');
        $manifest = json_decode((string) file_get_contents($root . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach (['overview', 'orders', 'purchases', 'requirements', 'inventory', 'team', 'relationships'] as $page) {
            $this->assertStringContainsString('data-page-panel="' . $page . '"', $html);
        }
        foreach (['businessPulseChart', 'orderStatusChart', 'purchaseTrendChart', 'requirementTrendChart', 'inventoryValueChart', 'employeeScoreChart', 'customerPortfolioChart'] as $chart) {
            $this->assertStringContainsString('id="' . $chart . '"', $html);
        }
        $this->assertStringContainsString('`/analytics?days=${', $script);
        $this->assertStringContainsString('Read-only analytics workspace', $html);
        $this->assertSame('Aabhushan Business Intelligence', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFileExists($root . 'vendor/chart.min.js');
        $this->assertLessThan(300000, filesize($root . 'index.html') + filesize($root . 'app.css') + filesize($root . 'app.js') + filesize($root . 'vendor/chart.min.js'));
    }

    public function testOperationalPwaNoLongerEmbedsAdminAnalyticsScreen(): void
    {
        $shell = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/app_shell.dart');

        $this->assertStringNotContainsString('business_analytics_screen.dart', $shell);
        $this->assertStringNotContainsString("case 'analytics'", $shell);
        $this->assertStringNotContainsString("'Business Analytics'", $shell);
    }
}
