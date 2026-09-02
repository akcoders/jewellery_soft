<?php

namespace Tests\Unit;

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
}
