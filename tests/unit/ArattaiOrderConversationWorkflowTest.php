<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ArattaiOrderConversationWorkflowTest extends CIUnitTestCase
{
    public function testConversationSessionAndPaperFormFieldsArePersisted(): void
    {
        $migration = $this->source('Database/Migrations/2026-10-18-000117_CreateArattaiOrderConversations.php');
        $orderModel = $this->source('Models/OrderModel.php');
        $sessionModel = $this->source('Models/ArattaiOrderConversationModel.php');

        $this->assertStringContainsString('arattai_order_conversations', $migration);
        $this->assertStringContainsString("'end_customer_name'", $migration);
        $this->assertStringContainsString("'sales_executive_name'", $migration);
        $this->assertStringContainsString("'draft_data'", $sessionModel);
        $this->assertStringContainsString("'end_customer_name'", $orderModel);
        $this->assertStringContainsString("'sales_executive_name'", $orderModel);
    }

    public function testHiMenuAndFullOrderWizardAreImplemented(): void
    {
        $wizard = $this->source('Services/ArattaiOrderConversationService.php');
        $integration = $this->source('Services/AttraiIntegrationService.php');

        $this->assertStringContainsString("'Create Order'", $wizard);
        $this->assertStringContainsString("'Order Status'", $wizard);
        foreach ([
            'order_from', 'order_received_date', 'end_customer_name', 'contact_number',
            'sales_executive_name', 'material_category', 'order_category_id', 'qty',
            'size_label', 'gold_required_gm', 'diamond_required_cts',
            'certificate_requirement', 'item_description', 'additional_details',
            'gold_rate_block_status', 'approximate_price', 'advance_amount', 'due_date',
            'reference_image', 'signed_order_image', 'confirm',
        ] as $step) {
            $this->assertStringContainsString("'{$step}'", $wizard, $step);
        }
        $this->assertStringContainsString('ArattaiOrderConversationService', $integration);
        $this->assertStringContainsString('conversationActorAdminUserId', $integration);
    }

    public function testRequiredImagesAndIdempotentConfirmationAreEnforced(): void
    {
        $wizard = $this->source('Services/ArattaiOrderConversationService.php');
        $creation = $this->source('Services/ArattaiOrderCreationService.php');

        $this->assertStringContainsString('Reference image and signed order image are required.', $creation);
        $this->assertStringContainsString("'file_type' => 'reference'", $creation);
        $this->assertStringContainsString("'file_type' => 'signed_order'", $creation);
        $this->assertStringContainsString("finishSession(\$session, 'completed'", $wizard);
        $this->assertStringContainsString('claimConfirmation($session)', $wizard);
        $this->assertStringContainsString("'status' => 'processing'", $wizard);
        $this->assertStringContainsString('Action already taken: This order was already created as', $wizard);
        $this->assertStringContainsString("'Confirm Order'", $wizard);
    }

    public function testFinalOrderUsesOneDatabaseTransactionAndCreatesOperationalRecords(): void
    {
        $creation = $this->source('Services/ArattaiOrderCreationService.php');

        $this->assertStringContainsString('$db->transException(true)->transStart();', $creation);
        $this->assertStringContainsString('new OrderModel()', $creation);
        $this->assertStringContainsString('new OrderItemModel()', $creation);
        $this->assertStringContainsString('new JobCardModel()', $creation);
        $this->assertStringContainsString('new OrderStatusHistoryModel()', $creation);
        $this->assertStringContainsString('if (! $db->transStatus())', $creation);
    }

    public function testQuickReplySelectionCanBeRecoveredFromTheArattaiButtonId(): void
    {
        $integration = $this->source('Services/AttraiIntegrationService.php');

        $this->assertStringContainsString('quickReplyLabel($replyContext, $buttonId)', $integration);
        $this->assertStringContainsString("'message_type' => (string) (\$payload['category'] ?? '') === 'input'", $integration);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
