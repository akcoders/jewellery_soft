<?php

use App\Services\ProductionDataImportService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ProductionReadyImageMatchingTest extends CIUnitTestCase
{
    public function testExactAndSeparatorRowImagesMapToTheirOwnReadyItems(): void
    {
        $reflection = new ReflectionClass(ProductionDataImportService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('matchReadyImage');
        $method->setAccessible(true);
        $placements = [
            ['key' => 'BEMAL:5', 'start_row' => 32, 'end_row' => 35, 'path' => 'ready-images/bemal-r32-5.jpeg'],
            ['key' => 'BEMAL:6', 'start_row' => 36, 'end_row' => 39, 'path' => 'ready-images/bemal-r36-6.jpeg'],
        ];
        $used = [];

        $firstArgs = [$placements, 32, 36, &$used];
        $this->assertSame('ready-images/bemal-r32-5.jpeg', $method->invokeArgs($service, $firstArgs));

        $secondArgs = [$placements, 37, 39, &$used];
        $this->assertSame('ready-images/bemal-r36-6.jpeg', $method->invokeArgs($service, $secondArgs));
        $this->assertSame(['BEMAL:5' => true, 'BEMAL:6' => true], $used);
    }
}
