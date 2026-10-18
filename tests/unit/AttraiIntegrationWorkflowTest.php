<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AttraiIntegrationWorkflowTest extends CIUnitTestCase
{
    public function testAttraiHasIndependentAdminConfigurationAndWebhook(): void
    {
        $migration = $this->source('Database/Migrations/2026-10-16-000112_AddAttraiIntegration.php');
        $oauthMigration = $this->source('Database/Migrations/2026-10-16-000113_AddArattaiOfficialOAuth.php');
        $settings = $this->source('Views/admin/company_settings/index.php');
        $routes = $this->source('Config/Routes.php');

        $this->assertStringContainsString("'attrai_api_url'", $migration);
        $this->assertStringContainsString("'attrai_webhook_secret'", $migration);
        $this->assertStringContainsString("'provider_key'", $migration);
        $this->assertStringContainsString("'attrai_notification_number'", $migration);
        $this->assertStringContainsString("'karigars' => ['attrai_number'", $migration);
        $this->assertStringContainsString("'employees' => ['attrai_number'", $migration);
        $this->assertStringContainsString("'attrai_client_id'", $oauthMigration);
        $this->assertStringContainsString("'attrai_refresh_token'", $oauthMigration);
        $this->assertStringContainsString('name="attrai_client_id"', $settings);
        $this->assertStringContainsString('name="attrai_client_secret"', $settings);
        $this->assertStringContainsString('name="attrai_approval_numbers"', $settings);
        $this->assertStringContainsString('Create &amp; Submit ERP Templates', $settings);
        $this->assertStringContainsString("get('integrations/attrai/webhook'", $routes);
        $this->assertStringContainsString("integrations/attrai/webhook", $routes);
        $this->assertStringContainsString("company-settings/attrai-test", $routes);
        $this->assertStringContainsString("company-settings/arattai/connect", $routes);
        $this->assertStringContainsString("company-settings/arattai/templates", $routes);
    }

    public function testApprovalButtonsUseSignedActionsAndExistingApprovalService(): void
    {
        $service = $this->source('Services/AttraiIntegrationService.php');
        $approval = $this->source('Services/MobileApprovalService.php');
        $followup = $this->source('Services/OrderFollowupService.php');

        $this->assertStringContainsString("hash_hmac('sha256'", $service);
        $this->assertStringContainsString("base64_encode(hash_hmac('sha256'", $service);
        $this->assertStringContainsString("'category' => 'input'", $service);
        $this->assertStringContainsString("'type' => 'quick_reply'", $service);
        $this->assertStringNotContainsString("'id' => (string) \$option['id']", $service);
        $this->assertStringContainsString("'type' => 'image'", $service);
        $this->assertStringContainsString("'caption' => mb_substr", $service);
        $this->assertStringContainsString("'approve_no_point'", $service);
        $this->assertStringContainsString('$service->approve(', $service);
        $this->assertStringContainsString('$service->reject(', $service);
        $this->assertStringContainsString('ATTRAI approval request notification failed', $approval);
        $this->assertStringContainsString('ATTRAI follow-up approval notification failed', $followup);
    }

    public function testApprovalQuickRepliesResolveQuotedRequestContext(): void
    {
        $service = $this->source('Services/AttraiIntegrationService.php');

        $this->assertStringContainsString('approvalRequestIdFromPayload', $service);
        $this->assertStringContainsString("preg_match('/\\bRequest\\s*:\\s*#?\\s*(\\d+)\\b/i'", $service);
        $this->assertStringContainsString("'reply_message_id'", $service);
        $this->assertStringContainsString("'quotedMessageId'", $service);
        $this->assertStringContainsString("'approval_context_missing'", $service);
        $this->assertStringContainsString('Please tap Approve or Disapprove on the latest approval card again.', $service);
    }

    public function testOfficialOAuthRefreshAndDefaultTemplatesAreImplemented(): void
    {
        $oauth = $this->source('Services/ArattaiOAuthService.php');
        $queue = $this->source('Services/WhatsappSenderQueueService.php');

        $this->assertStringContainsString('https://accounts.arattai.in/oauth/v2/auth', $oauth);
        $this->assertStringContainsString("'grant_type' => 'refresh_token'", $oauth);
        $this->assertStringContainsString('ArattaiBusiness.messagetemplates.CREATE', $oauth);
        $this->assertStringContainsString('Aabhushan ERP approval alert', $oauth);
        $this->assertStringContainsString('Aabhushan ERP order update', $oauth);
        $this->assertStringContainsString("'/messagetemplates/'", $oauth);
        $this->assertStringContainsString("'phone_number' =>", $queue);
        $this->assertStringContainsString("'Arattai-BusinessNumberId'", $queue);
    }

    public function testIncomingArattaiUsersAreCapturedForAdminSetup(): void
    {
        $capture = $this->source('Services/ArattaiUserCaptureService.php');
        $webhook = $this->source('Controllers/Integrations/AttraiWebhookController.php');
        $settings = $this->source('Views/admin/company_settings/index.php');

        $this->assertStringContainsString("'chat.message.received'", $capture);
        $this->assertStringContainsString("['entity']", $capture);
        $this->assertStringContainsString("['user']", $capture);
        $this->assertStringContainsString('ArattaiUserCaptureService', $webhook);
        $this->assertStringContainsString('Recent Arattai Users', $settings);
    }

    public function testArattaiAuditDeliveryAndPendingApprovalBacklogAreImplemented(): void
    {
        $migration = $this->source('Database/Migrations/2026-10-18-000115_AddArattaiDeliveryTracking.php');
        $oauth = $this->source('Services/ArattaiOAuthService.php');
        $queue = $this->source('Services/WhatsappSenderQueueService.php');
        $integration = $this->source('Services/AttraiIntegrationService.php');
        $routes = $this->source('Config/Routes.php');
        $command = $this->source('Commands/SendPendingArattaiApprovals.php');

        $this->assertStringContainsString("'delivery_status'", $migration);
        $this->assertStringContainsString("'provider_failure_reason'", $migration);
        $this->assertStringContainsString("'chat.message.delivered'", $oauth);
        $this->assertStringContainsString('applyArattaiDeliveryEvent', $queue);
        $this->assertStringContainsString("['ack_key']", $queue);
        $this->assertStringContainsString("\$decoded['error']", $queue);
        $this->assertStringContainsString('sendPendingApprovalBacklog', $integration);
        $this->assertStringContainsString('arattai:send-pending-approvals', $command);
        $this->assertStringContainsString("get('arattai-messages'", $routes);
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
