<?php

namespace Tests\Unit;

use App\Libraries\DiamondDisplay;
use CodeIgniter\Test\CIUnitTestCase;

final class DiamondDisplayTest extends CIUnitTestCase
{
    public function testBagReceiptHidesCalibratedSizeAndPreservesProductCode(): void
    {
        $detail = [
            'component_type' => 'diamond',
            'component_name' => 'BAG-001 / SI/IJ / Round / 1.20 mm / IJ / SI / Order AB-RING-01',
            'diamond_bag_item_id' => 12,
        ];

        $this->assertSame('SI/IJ', DiamondDisplay::componentName($detail));
        $this->assertSame(
            'BAG-001 / SI/IJ / Round / 1.20 mm / IJ / SI / Order AB-RING-01',
            $detail['component_name']
        );
    }

    public function testFinishedInventorySnapshotWithoutTraceIdsHidesSizes(): void
    {
        $this->assertSame('PAN', DiamondDisplay::componentName([
            'type' => 'diamond',
            'name' => 'BAG-002 / PAN / Princess / 2.5 mm / Unallocated',
        ]));
        $this->assertSame('SI/IJ', DiamondDisplay::componentName([
            'type' => 'diamond',
            'name' => 'SI/IJ / Round / 2.5-3 / IJ / SI',
        ]));
    }

    public function testLegacyProductAndOtherMaterialsRemainReadable(): void
    {
        $this->assertSame('SI/IJ', DiamondDisplay::componentName([
            'component_type' => 'diamond', 'component_name' => 'SI/IJ',
        ]));
        $this->assertSame('Emerald / Cabochon', DiamondDisplay::componentName([
            'component_type' => 'stone', 'component_name' => 'Emerald / Cabochon',
        ]));
        $this->assertSame('Diamond', DiamondDisplay::componentName(['type' => 'diamond']));
    }
}
