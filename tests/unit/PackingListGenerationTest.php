<?php

namespace Tests\Unit;

use App\Services\PdfService;
use CodeIgniter\Test\CIUnitTestCase;

final class PackingListGenerationTest extends CIUnitTestCase
{
    public function testPackingListUsesActualDatabaseInsertIdInEveryLiveCreationPath(): void
    {
        foreach ([
            APPPATH . 'Controllers/Admin/OrderController.php',
            APPPATH . 'Controllers/Api/DocumentsController.php',
            APPPATH . 'Controllers/Api/PackingController.php',
        ] as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString('$db->insertID()', $source, $path);
            $this->assertDoesNotMatchRegularExpression(
                '/\$packingId\s*=\s*\(int\)\s*\$db->table\([\'\"]packing_lists[\'\"]\)->insert/s',
                $source,
                $path
            );
        }
    }

    public function testOrderDocumentPathsRepairMissingPackingItems(): void
    {
        foreach ([
            APPPATH . 'Controllers/Admin/OrderController.php',
            APPPATH . 'Controllers/Api/DocumentsController.php',
        ] as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString('ensurePackingListItemsForOrder', $source, $path);
            $this->assertStringContainsString('insertMissingPackingListItems', $source, $path);
        }
    }

    public function testMultiOrderPackingListIsA4LandscapeAndRestrictedToReceivedOwnedOrders(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/BatchPackingListService.php');
        $view = (string) file_get_contents(APPPATH . 'Views/pdf/batch_packing_list.php');

        $this->assertStringContainsString("->whereIn('o.status', ['Completed', 'Complete'])", $service);
        $this->assertStringContainsString("->join('order_receive_summaries rs'", $service);
        $this->assertStringContainsString("->where('o.followup_assigned_to', \$userId)", $service);
        $this->assertStringContainsString("->orWhere('o.created_by', \$userId)", $service);
        $this->assertStringContainsString('@page { size: A4 landscape;', $view);
        $this->assertStringContainsString('Diamond Details', $view);
        $this->assertStringContainsString('Stone Details', $view);
        $this->assertStringContainsString('ITEM TOTAL', $view);
        $this->assertStringContainsString('COMBINED TOTAL', $view);
        foreach (['Vendor', 'Batch<br>No.', 'Customer<br>/ Party', 'Sales M.', 'Order Date', 'Due Date', 'Receiving<br>Date', 'Reference'] as $removedHeading) {
            $this->assertStringNotContainsString($removedHeading, $view);
        }
    }

    public function testMultiOrderPackingListPdfRenders(): void
    {
        $row = [
            'karigar_name' => 'GR', 'batch_no' => 1, 'item_no' => 1,
            'customer_name' => 'Test Customer', 'sales_person_name' => 'Sales',
            'order_received_date' => '2026-10-01', 'due_date' => '2026-10-08',
            'received_at' => '2026-10-10', 'order_no' => 'ORD-1',
            'item_description' => 'Ring', 'purity_code' => '18KT',
            'gross_weight_gm' => 5, 'diamond_details' => ['DIA | 10 pcs | 1 ct | rate 100 | amt 100'],
            'diamond_pcs' => 10, 'diamond_weight_cts' => 1, 'diamond_amount_total' => 100,
            'stone_details' => ['RUBY | 2 pcs | .2 ct | rate 50 | amt 10'],
            'stone_pcs' => 2, 'stone_weight_cts' => .2, 'stone_amount_total' => 10,
            'net_gold_weight_gm' => 4.8, 'pure_gold_weight_gm' => 3.6,
            'gold_amount' => 24000, 'pure_wastage_weight_gm' => .1,
            'wastage_percent' => 2, 'labour_amount' => 1200, 'total_valuation' => 25300,
        ];
        $totals = $row;
        $pdf = (new PdfService())->render('pdf/batch_packing_list', [
            'company' => ['company_name' => 'Aabhushan'],
            'report' => [
                'packing_no' => 'PK-TEST', 'generated_at' => '2026-10-11 10:00:00',
                'rows' => [$row], 'totals' => $totals,
            ],
        ], 'A4', 'landscape');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}
