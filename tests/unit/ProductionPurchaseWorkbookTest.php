<?php

namespace Tests\Unit;

use App\Services\ProductionPurchaseWorkbookService;
use CodeIgniter\Test\CIUnitTestCase;

final class ProductionPurchaseWorkbookTest extends CIUnitTestCase
{
    public function testVerifiedWorkbookReconcilesAllProductionPurchases(): void
    {
        $service = new ProductionPurchaseWorkbookService();
        $purchases = $service->purchases();

        $this->assertFileExists($service->workbookPath());
        $this->assertCount(35, $purchases);
        $this->assertCount(13, $service->vendors());
        $this->assertSame(39, array_sum(array_map(
            static fn(array $purchase): int => count($purchase['line_items']),
            $purchases
        )));

        $paid = array_values(array_filter(
            $purchases,
            static fn(array $purchase): bool => $purchase['payment_status'] === 'Paid'
        ));
        $this->assertCount(5, $paid);
        foreach ($purchases as $purchase) {
            $filenameIsPaid = str_contains(strtoupper(basename((string) $purchase['source_path'])), 'PAID');
            $this->assertSame($filenameIsPaid, $purchase['payment_status'] === 'Paid');
            $this->assertEqualsWithDelta(
                (float) $purchase['invoice_total'],
                (float) $purchase['taxable_amount'] + (float) $purchase['gst_amount'] + (float) $purchase['round_off_amount'],
                0.01
            );
        }

        $this->assertEqualsWithDelta(29734538.87, array_sum(array_column($purchases, 'taxable_amount')), 0.01);
        $this->assertEqualsWithDelta(815349.58, array_sum(array_column($purchases, 'gst_amount')), 0.01);
        $this->assertEqualsWithDelta(30549888.07, array_sum(array_column($purchases, 'invoice_total')), 0.01);
        $this->assertEqualsWithDelta(964763.00, array_sum(array_column($purchases, 'paid_amount')), 0.01);
    }
}
