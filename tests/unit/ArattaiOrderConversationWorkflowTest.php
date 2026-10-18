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
        $this->assertStringContainsString("finishSession(\$session, 'approval_pending'", $wizard);
        $this->assertStringContainsString("'order_create'", $wizard);
        $this->assertStringContainsString('claimConfirmation($session)', $wizard);
        $this->assertStringContainsString("'status' => 'processing'", $wizard);
        $this->assertStringContainsString('Action already taken: This order was already created as', $wizard);
        $this->assertStringContainsString("'Confirm Order'", $wizard);
    }

    public function testOfficialArattaiFileEnvelopeIsRecognisedAsAnImage(): void
    {
        $wizard = $this->source('Services/ArattaiOrderConversationService.php');

        $this->assertStringContainsString("\$type === 'file'", $wizard);
        $this->assertStringContainsString("str_starts_with(\$contentType, 'image/')", $wizard);
        $this->assertStringContainsString("'type', 'mime_type', 'mimeType', 'content_type', 'contentType'", $wizard);
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
        $this->assertStringContainsString("? 'interactive'", $integration);
        $this->assertStringContainsString("['image', 'document', 'audio', 'video']", $integration);
    }

    public function testArattaiTransactionMenuAndMaterialRulesAreImplemented(): void
    {
        $menu = $this->source('Services/ArattaiOrderConversationService.php');
        $transactions = $this->source('Services/ArattaiTransactionConversationService.php');

        foreach (['Follow-up', 'Issuement', 'Return', 'Bag Creation', 'Purchase', 'Receiving', 'Packing List', 'Delivery Challan'] as $label) {
            $this->assertStringContainsString("'{$label}'", $menu, $label);
        }
        $this->assertStringContainsString("['Skip Image', 'Cancel Current Process']", $transactions);
        $this->assertStringContainsString('Issuement image is compulsory.', $transactions);
        $this->assertStringContainsString("'add gold', 'gold' => 'erp_issue_gold_item'", $transactions);
        $this->assertStringContainsString("'add diamond', 'diamond' => 'erp_issue_diamond_bag'", $transactions);
        $this->assertStringContainsString("'add stone', 'stone' => 'erp_issue_stone_order'", $transactions);
        $this->assertStringContainsString("'order_id' => null", $this->source('Services/MobileApprovalService.php'));
        $this->assertStringContainsString('issuementContext((int) $bag[\'id\'])', $transactions);
    }

    public function testReturnBagReceivingAndDocumentsUseOperationalServices(): void
    {
        $transactions = $this->source('Services/ArattaiTransactionConversationService.php');
        $integration = $this->source('Services/AttraiIntegrationService.php');
        $routes = $this->source('Config/Routes.php');

        $this->assertStringContainsString('returnableIssueLines', $transactions);
        $this->assertStringContainsString('applyReturn($returnId)', $transactions);
        $this->assertStringContainsString('DiamondBagService())->create', $transactions);
        $this->assertStringContainsString('OrderReceivingService())->receive', $transactions);
        $this->assertStringContainsString('No issued diamond bag line was found', $transactions);
        $this->assertStringContainsString('No issued stone line was found', $transactions);
        $this->assertStringContainsString("'type' => 'document'", $integration);
        $this->assertStringContainsString('documents/issuements/(:segment)', $routes);
        $this->assertStringContainsString('PDF will arrive after approval.', $transactions);
    }

    public function testTransactionConfirmationsAreIdempotent(): void
    {
        $transactions = $this->source('Services/ArattaiTransactionConversationService.php');

        $this->assertStringContainsString("->where('status', 'active')->update(['status' => 'processing'", $transactions);
        $this->assertStringContainsString('This action was already completed successfully.', $transactions);
        $this->assertStringContainsString('Action already taken or currently processing.', $transactions);
    }

    public function testPurchaseCustomChallanAndDirectFollowupActionsAreImplemented(): void
    {
        $transactions = $this->source('Services/ArattaiTransactionConversationService.php');
        $integration = $this->source('Services/AttraiIntegrationService.php');
        $approval = $this->source('Services/MobileApprovalService.php');
        $purchase = $this->source('Services/ArattaiPurchaseService.php');

        $this->assertStringContainsString("'purchase' => 'erp_purchase_type'", $transactions);
        $this->assertStringContainsString('Purchase Bill now', $transactions);
        $this->assertStringContainsString('new ArattaiPurchaseService()', $transactions);
        $this->assertStringContainsString('Custom Delivery Challan', $transactions);
        $this->assertStringContainsString("'delivery_challan'", $transactions);
        $this->assertStringContainsString('Take Follow-up', $integration);
        $this->assertStringContainsString("'take follow-up ' . \$sourceId", $integration);
        $this->assertStringContainsString("'order_create'", $approval);
        $this->assertStringContainsString('Purchase bill is compulsory.', $purchase);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
