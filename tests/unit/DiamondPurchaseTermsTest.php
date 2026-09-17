<?php

namespace Tests\Unit;

use App\Services\PurchaseTermsService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

final class DiamondPurchaseTermsTest extends CIUnitTestCase
{
    public function testDueDateIsCalculatedFromPurchaseDateAndTerms(): void
    {
        $result = (new PurchaseTermsService())->resolve('2026-01-31', '30');

        $this->assertSame(30, $result['payment_terms_days']);
        $this->assertSame('2026-03-02', $result['due_date']);
    }

    public function testZeroAndEmptyTermsAreHandled(): void
    {
        $service = new PurchaseTermsService();

        $this->assertSame([
            'payment_terms_days' => 0,
            'due_date' => '2026-09-17',
        ], $service->resolve('2026-09-17', 0));
        $this->assertSame([
            'payment_terms_days' => null,
            'due_date' => null,
        ], $service->resolve('2026-09-17', null));
    }

    public function testInvalidTermsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PurchaseTermsService())->resolve('2026-09-17', '-1');
    }

    public function testLegacyDueDateIsConvertedToTerms(): void
    {
        $result = (new PurchaseTermsService())->resolveLegacyDueDate('2026-09-17', '2026-10-17');

        $this->assertSame(30, $result['payment_terms_days']);
        $this->assertSame('2026-10-17', $result['due_date']);
    }

    public function testAdminAndMobileFlowsPersistServerCalculatedDueDate(): void
    {
        $adminController = $this->source('Controllers/Admin/DiamondInventory/PurchasesController.php');
        $mobileController = $this->source('Controllers/Api/Mobile/TransactionsController.php');
        $form = $this->source('Views/admin/diamond_inventory/purchases/form.php');
        $flutter = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/transaction_create_screen.dart'
        );

        $this->assertStringContainsString('name="terms"', $form);
        $this->assertStringContainsString('id="diamond_due_date"', $form);
        $this->assertStringContainsString('readonly', $form);
        $this->assertStringContainsString("getPost('terms')", $adminController);
        $this->assertStringContainsString("'due_date' => \$paymentTerms['due_date']", $adminController);
        $this->assertStringContainsString("array_key_exists('terms', \$payload)", $mobileController);
        $this->assertStringContainsString('resolveLegacyDueDate', $mobileController);
        $this->assertStringContainsString("'due_date' => \$paymentTerms['due_date']", $mobileController);
        $this->assertStringContainsString("payload['terms']", $flutter);
        $this->assertStringContainsString("labelText: 'Terms (Days)'", $flutter);
        $this->assertStringNotContainsString("payload['due_date']", $flutter);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
