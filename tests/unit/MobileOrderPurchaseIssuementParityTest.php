<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MobileOrderPurchaseIssuementParityTest extends CIUnitTestCase
{
    public function testMobileOrderCreationCapturesTheDetailedWebOrderFields(): void
    {
        $controller = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $screen = $this->rootSource('app_kit/FlutKit/lib/jewellery_mobile/screens/order_create_screen.dart');

        foreach ([
            'order_name', 'order_received_date', 'order_category_id', 'new_order_category',
            'order_type', 'order_design_type', 'order_from', 'customer_id', 'contact_number',
            'material_category', 'sales_person_user_id', 'priority', 'status', 'priority_level',
            'whatsapp_notification_number', 'whatsapp_notify_order_created',
            'expected_diamond_spec', 'expected_stone_spec', 'order_notes',
            'repair_ornament_details', 'repair_work_details', 'repair_receive_weight_gm',
            'repair_received_at', 'certificate_requirement', 'gold_rate_block_status',
            'gold_rate_per_gm', 'approximate_price', 'advance_amount', 'due_date',
            'additional_details', 'attachments',
        ] as $field) {
            $this->assertStringContainsString($field, $controller, 'controller ' . $field);
            $this->assertStringContainsString("'{$field}'", $screen, 'PWA ' . $field);
        }

        foreach (['design_id', 'gold_purity_id', 'item_description', 'size_label', 'qty', 'gold_required_gm', 'diamond_required_cts'] as $field) {
            $this->assertStringContainsString("'{$field}'", $controller, 'controller item ' . $field);
            $this->assertStringContainsString("'{$field}'", $screen, 'PWA item ' . $field);
        }
    }

    public function testPwaUsesOneCombinedIssuementForAllMaterials(): void
    {
        $routes = $this->source('Config/Routes.php');
        $controller = $this->source('Controllers/Api/Mobile/TransactionsController.php');
        $shell = $this->rootSource('app_kit/FlutKit/lib/jewellery_mobile/screens/app_shell.dart');
        $screen = $this->rootSource('app_kit/FlutKit/lib/jewellery_mobile/screens/issuement_create_screen.dart');
        $serviceWorker = $this->rootSource('app_kit/FlutKit/web/aabhushan_app_sw.js');

        $this->assertStringContainsString("post('issuements'", $routes);
        $this->assertStringContainsString('function createCombinedIssuement()', $controller);
        foreach (['gold_lines', 'diamond_lines', 'stone_lines'] as $field) {
            $this->assertStringContainsString("payload['{$field}']", $controller);
            $this->assertStringContainsString("'{$field}'", $screen);
        }
        $this->assertStringContainsString("'issuements'", $shell);
        $this->assertStringContainsString('leading: const _IssuementMenuIcon()', $shell);
        $this->assertStringContainsString('class _IssuementMenuIconPainter extends CustomPainter', $shell);
        $this->assertStringContainsString('./assets/FontManifest.json', $serviceWorker);
        $this->assertStringContainsString('./assets/fonts/MaterialIcons-Regular.otf', $serviceWorker);
        $this->assertStringNotContainsString("_drawerItem(\n                        'diamond_issues'", $shell);
        $this->assertStringNotContainsString("_drawerItem(\n                        'gold_issues'", $shell);
        $this->assertStringNotContainsString("_drawerItem(\n                        'stone_issues'", $shell);
    }

    public function testMobilePurchaseFormMatchesMaterialSpecificWebFields(): void
    {
        $controller = $this->source('Controllers/Api/Mobile/TransactionsController.php');
        $screen = $this->rootSource('app_kit/FlutKit/lib/jewellery_mobile/screens/purchase_create_screen.dart');

        foreach ([
            'purchase_date', 'vendor_id', 'invoice_no', 'supplier_name', 'supplier_address',
            'supplier_gstin', 'supplier_phone', 'supplier_email', 'due_date', 'gst_master_id',
            'round_off_amount', 'notes', 'terms', 'shape_master_id', 'chalni_group_id',
            'diamond_type', 'color', 'clarity', 'pcs', 'carat', 'rate_per_carat',
            'location_id', 'place_of_supply', 'purchase_description', 'payment_status',
            'paid_amount', 'payment_date', 'gold_purity_id', 'form_type', 'description',
            'hsn_sac', 'unit', 'weight_gm', 'rate_per_gm', 'product_name', 'stone_type',
            'qty', 'rate', 'attachments',
        ] as $field) {
            $this->assertStringContainsString($field, $controller, 'controller ' . $field);
            $this->assertStringContainsString("'{$field}'", $screen, 'PWA ' . $field);
        }

        $this->assertStringContainsString('TaxMasterService', $controller);
        $this->assertStringContainsString('savePurchaseAttachments', $controller);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }

    private function rootSource(string $relativePath): string
    {
        return (string) file_get_contents(ROOTPATH . $relativePath);
    }
}
