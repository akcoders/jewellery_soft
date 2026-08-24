<?php

namespace Tests\Unit;

use App\Services\ReadyOrderWorkbookImportService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

final class ReadyOrderWorkbookTest extends CIUnitTestCase
{
    public function testVerifiedPackingListParsesEveryCompletedOrderAndFinancialTotal(): void
    {
        $path = ROOTPATH . 'anuj/PL-2026-2027 order ready.xlsx';
        $this->assertFileExists($path);
        $this->assertSame(
            'dfc98c19ab15b213cb3c5f2ca30ff2059555a126d5de98214c957625fa043138',
            hash_file('sha256', $path)
        );

        $reflection = new ReflectionClass(ReadyOrderWorkbookImportService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $karigarIds = $reflection->getProperty('karigarIds');
        $karigarIds->setValue($service, [
            'GR' => 1,
            'RHEEA' => 2,
            'UTTAM MAL' => 3,
            'SHREE GOURANGO' => 4,
            'SAFWAN JEWELLERY' => 5,
        ]);
        $parse = $reflection->getMethod('parseWorkbook');
        $items = $parse->invoke($service, $path, [
            'GR' => [],
            'RHEEA' => [],
            'UTTAM MAL' => [],
            'SHREE GOURANGO' => [],
            'SAFWAN JEWELLERY' => [],
        ]);

        $this->assertCount(78, $items);
        $paidMarked = array_filter($items, static fn(array $item): bool => $item['payment_status'] === 'Paid');
        $this->assertCount(62, $paidMarked);
        $this->assertCount(54, array_filter(
            $paidMarked,
            static fn(array $item): bool => (float) $item['labour_charges'] > 0.0
        ));
        $this->assertSame([
            'GR' => 11,
            'RHEEA' => 7,
            'UTTAM MAL' => 30,
            'SHREE GOURANGO' => 23,
            'SAFWAN JEWELLERY' => 7,
        ], array_count_values(array_column($items, 'source_sheet')));

        $expected = [
            'gross_weight_gm' => 2089.262,
            'net_weight_gm' => 1853.994,
            'pure_weight_gm' => 1529.944,
            'diamond_weight_cts' => 273.150,
            'stone_weight_cts' => 903.190,
            'gold_amount' => 22473042.85,
            'labour_charges' => 780250.92,
            'total_value' => 40943147.83,
        ];
        foreach ($expected as $field => $total) {
            $precision = in_array($field, ['gold_amount', 'labour_charges', 'total_value'], true) ? 2 : 3;
            $this->assertEqualsWithDelta(
                $total,
                round(array_sum(array_column($items, $field)), $precision),
                $precision === 2 ? 0.01 : 0.001,
                $field
            );
        }

        $paidLabour = array_sum(array_map(
            static fn(array $item): float => $item['payment_status'] === 'Paid' ? (float) $item['labour_charges'] : 0.0,
            $items
        ));
        $this->assertEqualsWithDelta(633386.44, round($paidLabour, 2), 0.01);
    }
}
