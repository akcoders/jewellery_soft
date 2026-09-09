<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class OrderDeletionWorkflowTest extends CIUnitTestCase
{
    public function testDeletionIsProtectedByDedicatedSuperAdminPermission(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-09-000089_AddSecureOrderDeletion.php');
        $routes = $this->source('Config/Routes.php');

        $this->assertStringContainsString("'orders.delete'", $migration);
        $this->assertStringContainsString("['SUPER_ADMIN', 'OWNER']", $migration);
        $this->assertStringContainsString("createTable('order_deletion_audits'", $migration);
        $this->assertStringContainsString("permission:orders.delete", $routes);
        $this->assertStringContainsString("OrderController::delete", $routes);
    }

    public function testUiAndControllerRequireExplicitPermanentDeletionConfirmation(): void
    {
        $view = $this->source('Views/admin/orders/show.php');
        $controller = $this->source('Controllers/Admin/OrderController.php');

        $this->assertStringContainsString('Delete Order', $view);
        $this->assertStringContainsString('name="delete_reason"', $view);
        $this->assertStringContainsString('name="confirm_order_no"', $view);
        $this->assertStringContainsString('name="confirm_permanent_delete"', $view);
        $this->assertStringContainsString('hash_equals', $controller);
        $this->assertStringContainsString('$this->orderDeletionService->delete(', $controller);
    }

    public function testServiceReversesOwnedPostingsAndProtectsLegalOrSharedRecords(): void
    {
        $service = $this->source('Services/OrderDeletionService.php');

        $this->assertStringContainsString('reverseAndRemoveVouchers', $service);
        $this->assertStringContainsString('reverseVoucher(', $service);
        $this->assertStringContainsString('reverseIssue($issueId)', $service);
        $this->assertStringContainsString('assertNoProtectedDocuments', $service);
        $this->assertStringContainsString("['invoices', 'order_id', 'customer invoice']", $service);
        $this->assertStringContainsString("['labour_bill_items', 'order_id', 'labour bill']", $service);
        $this->assertStringContainsString("setNullByValue('issue_lines', 'allocation_order_id'", $service);
        $this->assertStringContainsString('isPathReferenced', $service);
        $this->assertStringContainsString('deleteSafeUploadFile', $service);
        $this->assertStringContainsString("realpath(FCPATH . 'uploads')", $service);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
