<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AttraiIntegrationWorkflowTest extends CIUnitTestCase
{
    public function testAttraiHasIndependentAdminConfigurationAndWebhook(): void
    {
        $migration = $this->source('Database/Migrations/2026-10-16-000112_AddAttraiIntegration.php');
        $settings = $this->source('Views/admin/company_settings/index.php');
        $routes = $this->source('Config/Routes.php');

        $this->assertStringContainsString("'attrai_api_url'", $migration);
        $this->assertStringContainsString("'attrai_webhook_secret'", $migration);
        $this->assertStringContainsString("'provider_key'", $migration);
        $this->assertStringContainsString("'attrai_notification_number'", $migration);
        $this->assertStringContainsString("'karigars' => ['attrai_number'", $migration);
        $this->assertStringContainsString("'employees' => ['attrai_number'", $migration);
        $this->assertStringContainsString('name="attrai_api_url"', $settings);
        $this->assertStringContainsString('name="attrai_approval_numbers"', $settings);
        $this->assertStringContainsString('name="attrai_approval_body_template"', $settings);
        $this->assertStringContainsString('name="attrai_template_follower_assigned"', $settings);
        $this->assertStringContainsString("integrations/attrai/webhook", $routes);
        $this->assertStringContainsString("company-settings/attrai-test", $routes);
    }

    public function testApprovalButtonsUseSignedActionsAndExistingApprovalService(): void
    {
        $service = $this->source('Services/AttraiIntegrationService.php');
        $approval = $this->source('Services/MobileApprovalService.php');
        $followup = $this->source('Services/OrderFollowupService.php');

        $this->assertStringContainsString("hash_hmac('sha256'", $service);
        $this->assertStringContainsString("'approve_no_point'", $service);
        $this->assertStringContainsString('$service->approve(', $service);
        $this->assertStringContainsString('$service->reject(', $service);
        $this->assertStringContainsString('ATTRAI approval request notification failed', $approval);
        $this->assertStringContainsString('ATTRAI follow-up approval notification failed', $followup);
    }

    public function testOrderUpdatesAndKarigarAssignmentUseAttraiProvider(): void
    {
        $orders = $this->source('Services/OrderWhatsAppService.php');
        $queue = $this->source('Services/WhatsappSenderQueueService.php');
        $adminOrders = $this->source('Controllers/Admin/OrderController.php');
        $mobileOrders = $this->source('Controllers/Api/Mobile/OrdersController.php');

        $this->assertStringContainsString("? 'attrai' : 'whatsapp'", $orders);
        $this->assertStringContainsString("'provider_key' => \$providerKey", $orders);
        $this->assertStringContainsString("strtolower(trim(\$provider)) !== 'attrai'", $queue);
        $this->assertStringContainsString('notifyKarigarAssigned($id)', $adminOrders);
        $this->assertStringContainsString('notifyKarigarAssigned($id)', $mobileOrders);
        $this->assertStringContainsString("'event' => 'karigar_assigned'", $this->source('Services/AttraiIntegrationService.php'));
        $this->assertStringContainsString("'event' => 'follower_assigned'", $this->source('Services/AttraiIntegrationService.php'));
        $this->assertStringContainsString("'media_url' =>", $orders);
    }

    public function testDedicatedAttraiNumbersExistOnOrderKarigarEmployeeAndPwa(): void
    {
        $orderModel = $this->source('Models/OrderModel.php');
        $karigarModel = $this->source('Models/KarigarModel.php');
        $employeeModel = $this->source('Models/EmployeeModel.php');
        $pwaOrder = $this->rootSource('app_kit/FlutKit/lib/jewellery_pwa/screens/order_create_screen.dart');
        $pwaApprovals = $this->rootSource('app_kit/FlutKit/lib/jewellery_pwa/screens/mobile_approvals_screen.dart');

        $this->assertStringContainsString("'attrai_notification_number'", $orderModel);
        $this->assertStringContainsString("'attrai_number'", $karigarModel);
        $this->assertStringContainsString("'attrai_number'", $employeeModel);
        $this->assertStringContainsString("'attrai_notification_number'", $pwaOrder);
        $this->assertStringContainsString("_field('attrai_number', 'ATTRAI Number')", $pwaApprovals);
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
