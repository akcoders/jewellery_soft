<?php

namespace Tests\Unit;

use App\Services\DesignDiamondDetailService;
use CodeIgniter\Test\CIUnitTestCase;

final class DesignDiamondDetailServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect([
            'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
        ], false);
        foreach ([
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT)',
            'CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, design_id INTEGER)',
            'CREATE TABLE order_receive_details (id INTEGER PRIMARY KEY, order_id INTEGER, component_type TEXT, component_name TEXT, pcs REAL, weight_cts REAL, diamond_bag_item_id INTEGER)',
            'CREATE TABLE order_diamond_size_details (id INTEGER PRIMARY KEY, order_id INTEGER, quality TEXT, shade TEXT, shape_name TEXT, size_label TEXT, chalni_label TEXT, pcs REAL, weight_cts REAL, source_file TEXT, source_sheet TEXT, source_row INTEGER, source_block TEXT, notes TEXT, match_status TEXT)',
            'CREATE TABLE diamond_bag_items (id INTEGER PRIMARY KEY, bag_id INTEGER, shape_master_id INTEGER, size_master_id INTEGER)',
            'CREATE TABLE diamond_bags (id INTEGER PRIMARY KEY, bag_no TEXT)',
            'CREATE TABLE diamond_shape_masters (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE diamond_size_masters (id INTEGER PRIMARY KEY, size_label TEXT, size_code TEXT, chalni_label TEXT)',
        ] as $sql) {
            $this->db->query($sql);
        }
        $this->db->table('orders')->insertBatch([
            ['id' => 1, 'order_no' => 'ORIGINAL'], ['id' => 2, 'order_no' => 'REPEAT'], ['id' => 3, 'order_no' => 'UNRELATED'],
        ]);
        $this->db->table('order_items')->insertBatch([
            ['id' => 1, 'order_id' => 1, 'design_id' => 10],
            ['id' => 2, 'order_id' => 2, 'design_id' => 10],
            ['id' => 3, 'order_id' => 3, 'design_id' => 20],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->close();
        parent::tearDown();
    }

    public function testHistoricalReferenceUsesOnlyMatchedOrdersAndRetainsUnsizedReturns(): void
    {
        $base = ['order_id' => 1, 'quality' => 'SI/IJ', 'source_file' => 'issument.xls', 'source_sheet' => 'Issue', 'match_status' => 'matched'];
        $this->db->table('order_diamond_size_details')->insert($base + ['pcs' => 50, 'weight_cts' => 1.2, 'size_label' => '1.2 mm', 'source_row' => 10]);
        $this->db->table('order_diamond_size_details')->insert($base + ['pcs' => -5, 'weight_cts' => -0.12, 'source_row' => 11]);
        $this->db->table('order_diamond_size_details')->insert(array_replace($base, ['pcs' => 999, 'weight_cts' => 999, 'match_status' => 'ambiguous']));
        $this->db->table('order_diamond_size_details')->insert(array_replace($base, ['order_id' => 3, 'pcs' => 888, 'weight_cts' => 888]));

        $orders = (new DesignDiamondDetailService($this->db))->ordersForDesign(['id' => 10, 'source_order_id' => 1]);

        $this->assertSame([1, 2], array_column($orders, 'id'));
        $this->assertTrue($orders[0]['historical']);
        $this->assertCount(2, $orders[0]['rows']);
        $this->assertEqualsWithDelta(45, $orders[0]['total_pcs'], 0.0001);
        $this->assertEqualsWithDelta(1.08, $orders[0]['total_cts'], 0.0001);
        $this->assertNull($orders[0]['rows'][1]['size_label']);
        $this->assertFalse($orders[1]['historical']);
        $this->assertSame([], $orders[1]['rows']);
    }

    public function testActualReceivedBagRowsTakePrecedenceWithoutDoubleCountingHistoricalIssues(): void
    {
        $this->db->table('diamond_bags')->insert(['id' => 1, 'bag_no' => 'BAG-01']);
        $this->db->table('diamond_shape_masters')->insert(['id' => 1, 'name' => 'Round']);
        $this->db->table('diamond_size_masters')->insert(['id' => 1, 'size_label' => '1.20 mm', 'size_code' => 'R12', 'chalni_label' => '2-2.5']);
        $this->db->table('diamond_bag_items')->insert(['id' => 1, 'bag_id' => 1, 'shape_master_id' => 1, 'size_master_id' => 1]);
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'diamond', 'component_name' => 'SI/IJ', 'pcs' => 12, 'weight_cts' => .24, 'diamond_bag_item_id' => 1]);
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'diamond', 'component_name' => 'Polki', 'pcs' => 2, 'weight_cts' => .05]);
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'stone', 'component_name' => 'Ruby', 'pcs' => 3, 'weight_cts' => 2]);
        $this->db->table('order_diamond_size_details')->insert(['order_id' => 1, 'match_status' => 'matched', 'pcs' => 50, 'weight_cts' => 1]);

        $orders = (new DesignDiamondDetailService($this->db))->ordersForDesign(['id' => 10, 'source_order_id' => 1]);

        $this->assertFalse($orders[0]['historical']);
        $this->assertCount(2, $orders[0]['rows']);
        $this->assertEquals(14, $orders[0]['total_pcs']);
        $this->assertEqualsWithDelta(.29, $orders[0]['received_total_cts'], .0001);
        $this->assertSame('Polki', $orders[0]['rows'][1]['quality']);
        $this->assertNull($orders[0]['rows'][1]['size_label']);
        $this->assertSame('Round', $orders[0]['rows'][0]['shape_name']);
        $this->assertSame('1.20 mm', $orders[0]['rows'][0]['size_label']);
        $this->assertSame('2-2.5', $orders[0]['rows'][0]['chalni_label']);
    }

    public function testPartialHistoricalCoveragePreservesReceivingTotalsAndShowsMissingSizes(): void
    {
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'diamond', 'component_name' => 'SI/IJ', 'pcs' => 157, 'weight_cts' => 2.37]);
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'diamond', 'component_name' => 'Polki', 'pcs' => 1, 'weight_cts' => .05]);
        $this->db->table('order_diamond_size_details')->insert(['order_id' => 1, 'quality' => 'SI/IJ', 'pcs' => 157, 'weight_cts' => 2.37, 'size_label' => 'MIX', 'source_file' => 'issument.xls', 'source_sheet' => 'SATTA', 'source_row' => 17, 'match_status' => 'matched', 'notes' => 'MIX sizes supplied; exact split not recorded.']);

        $design = ['id' => 10, 'source_order_id' => 1, 'name' => 'Pendant', 'design_code' => 'DS-01'];
        $orders = (new DesignDiamondDetailService($this->db))->ordersForDesign($design);

        $this->assertTrue($orders[0]['historical']);
        $this->assertEquals(158, $orders[0]['received_total_pcs']);
        $this->assertEqualsWithDelta(2.42, $orders[0]['received_total_cts'], .0001);
        $this->assertEquals(157, $orders[0]['total_pcs']);
        $this->assertEquals(1, $orders[0]['unmatched_received_pcs']);
        $this->assertEqualsWithDelta(.05, $orders[0]['unmatched_received_cts'], .0001);
        $html = view('admin/designs/diamonds', ['design' => $design, 'orders' => $orders]);
        $this->assertStringContainsString('Actual received: 158 PCS', $html);
        $this->assertStringContainsString('Size reference covers 157 of 158 received PCS', $html);
        $this->assertStringContainsString('2.370 of 2.420 received CTS', $html);
        $this->assertStringContainsString('Remaining diamonds (1 PCS · 0.050 CTS) have no matched size record.', $html);
        $this->assertStringContainsString('MIX sizes supplied; exact split not recorded.', $html);
    }

    public function testMissingHistoricalSchemaAndUnbaggedReceiptsDoNotInventSizes(): void
    {
        $this->db->query('DROP TABLE order_diamond_size_details');
        $this->db->resetDataCache();
        $this->db->table('order_receive_details')->insert(['order_id' => 1, 'component_type' => 'diamond', 'component_name' => 'SI/IJ', 'pcs' => 8, 'weight_cts' => .16]);
        $this->db->table('order_items')->insert(['order_id' => 1, 'design_id' => 30]);

        $orders = (new DesignDiamondDetailService($this->db))->ordersForDesign(['id' => 10, 'source_order_id' => 1]);

        $this->assertTrue($orders[0]['multiple_items']);
        $this->assertFalse($orders[0]['historical']);
        $this->assertNull($orders[0]['rows'][0]['shape_name']);
        $this->assertNull($orders[0]['rows'][0]['size_label']);
        $this->assertEquals(8, $orders[0]['total_pcs']);
    }
}
