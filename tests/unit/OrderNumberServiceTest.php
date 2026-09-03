<?php

namespace Tests\Unit;

use App\Services\OrderNumberService;
use CodeIgniter\Test\CIUnitTestCase;

final class OrderNumberServiceTest extends CIUnitTestCase
{
    public function testOrderCategoryCodesAreStableAndReadable(): void
    {
        $service = new OrderNumberService(db_connect());

        $this->assertSame('BGL', $service->categoryCode('Bangle'));
        $this->assertSame('JMK', $service->categoryCode('Jhumki'));
        $this->assertSame('BRC', $service->categoryCode('Bracelet'));
        $this->assertSame('HRM', $service->categoryCode('Haaram'));
    }

    public function testSalesPersonCodeUsesInitialsOrSingleNamePrefix(): void
    {
        $service = new OrderNumberService(db_connect());

        $this->assertSame('DM', $service->nameCode('Divyanshu Mishra'));
        $this->assertSame('AAKA', $service->nameCode('Aakash'));
        $this->assertSame('NS', $service->nameCode(''));
    }

    public function testAllFutureOrderCreationPathsUseCentralGenerator(): void
    {
        foreach ([
            APPPATH . 'Controllers/Admin/OrderController.php',
            APPPATH . 'Controllers/Customer/OrdersController.php',
            APPPATH . 'Controllers/Api/OrdersController.php',
        ] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringContainsString('OrderNumberService', $source, $path);
            $this->assertStringContainsString('->generate(', $source, $path);
        }
    }
}
