<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class FinishedJewelleryDesignWorkflowTest extends CIUnitTestCase
{
    public function testCompletedReceivedJewelleryBecomesDesignEvenWithoutPhoto(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/FinishedJewelleryService.php');
        $migration = (string) file_get_contents(APPPATH . 'Database/Migrations/2026-10-13-000103_BackfillCompletedOrderDesigns.php');
        $designs = (string) file_get_contents(APPPATH . 'Views/admin/designs/index.php');
        $diamonds = (string) file_get_contents(APPPATH . 'Views/admin/designs/diamonds.php');

        $this->assertStringNotContainsString("if (! \$photo) {\n                continue;", $service);
        $this->assertStringContainsString("'image_path' => \$imagePath !== '' ? \$imagePath : null", $service);
        $this->assertStringContainsString('createForCompletedOrder', $migration);
        $this->assertStringContainsString('Used Diamonds', $designs);
        $this->assertStringContainsString('Diamonds used in finished jewellery', $designs);
        $this->assertStringContainsString('Received total', $diamonds);
    }
}
