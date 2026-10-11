<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PwaReceivingBagSelectionTest extends CIUnitTestCase
{
    public function testReceivingUsesOnlyServerValidatedOrderBagLines(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/OrderReceivingService.php');
        $trace = (string) file_get_contents(APPPATH . 'Services/DiamondBagTraceService.php');

        $this->assertStringContainsString("'diamond_bag_lines' => \$diamondBagLines", $service);
        $this->assertStringContainsString('private function diamondRows(', $service);
        $this->assertStringContainsString("\$input['issue_line_ids']", $service);
        $this->assertStringContainsString("Selected diamond bag line is not available for this order.", $service);
        $this->assertStringContainsString("'diamond_issue_line_id'", $service);
        $this->assertStringContainsString('recordStudding($receiveDetailId)', $service);
        $this->assertStringContainsString("->where('il.allocation_order_id', \$orderId)", $trace);
        $this->assertStringContainsString('il.rate_per_carat', $trace);
    }

    public function testPwaDiamondQuantitiesAreAutomaticAndCalculationsAreVisible(): void
    {
        $receiving = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/order_receiving_screen.dart');
        $detail = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/order_detail_screen.dart');

        $this->assertStringContainsString("'issue_line_ids': issueLineIds.toList()", $receiving);
        $this->assertStringContainsString('Select one or more issued bag lines', $receiving);
        $this->assertStringContainsString("'Description (editable)'", $receiving);
        $this->assertStringContainsString("_readOnlyMetric('PCS'", $receiving);
        $this->assertStringContainsString('CALCULATED RECEIVING VALUES', $receiving);
        $this->assertStringContainsString("('Net gold'", $receiving);
        $this->assertStringContainsString("('Pure wastage'", $receiving);
        $this->assertStringContainsString("('Labour'", $receiving);
        $this->assertStringContainsString("_receipt = _stringMap(data['receipt'])", $detail);
        $this->assertStringContainsString('value is Map', $detail);
    }

    public function testAdminReceivingAlsoLocksPcsAndWeightToOrderBagTotals(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $receive = (string) file_get_contents(APPPATH . 'Views/admin/orders/receive.php');

        $this->assertStringContainsString('if ($orderId > 0)', $controller);
        $this->assertStringContainsString('return (new DiamondBagTraceService($db))->receiptOptions($karigarId, $orderId);', $controller);
        $this->assertStringContainsString('$pcs = round($totalAvailablePcs, 3);', $controller);
        $this->assertStringContainsString('$cts = round($totalAvailableCts, 3);', $controller);
        $this->assertStringContainsString("array_sum(array_column(\$diamondRows, 'pcs'))", $controller);
        $this->assertStringContainsString("array_sum(array_column(\$diamondRows, 'weight_cts'))", $controller);
        $this->assertMatchesRegularExpression('/name="studded_diamond_pcs\[\]"[^>]*readonly/', $receive);
        $this->assertMatchesRegularExpression('/name="studded_diamond_weight\[\]"[^>]*readonly/', $receive);
    }
}
