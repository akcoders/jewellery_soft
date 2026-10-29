<?php

namespace Tests\Unit;

use App\Services\DiamondBagService;
use App\Services\DiamondIssueOrderService;
use App\Services\MobileApprovalService;
use App\Services\StoneInventory\StoneIssueOrderService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class IssuementOrderEligibilityTest extends CIUnitTestCase
{
    private BaseConnection $issueDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->issueDb = Database::connect([
            'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
        ], false);
        foreach ([
            'CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT, order_name TEXT, status TEXT, customer_id INTEGER, assigned_karigar_id INTEGER, created_by INTEGER, followup_assigned_to INTEGER, deleted_at TEXT)',
            'CREATE TABLE order_receive_summaries (id INTEGER PRIMARY KEY, order_id INTEGER)',
            'CREATE TABLE order_material_movements (id INTEGER PRIMARY KEY, order_id INTEGER, movement_type TEXT)',
        ] as $sql) {
            $this->issueDb->query($sql);
        }
        $this->issueDb->table('customers')->insert(['id' => 1, 'name' => 'Customer']);
        foreach ([1 => 'Completed', 2 => 'Completed', 3 => 'Completed', 4 => 'Cancelled', 5 => 'Completed', 6 => 'Ready', 7 => 'In Progress', 8 => 'Complete'] as $id => $status) {
            $this->issueDb->table('orders')->insert([
                'id' => $id, 'order_no' => 'ORD-' . $id, 'order_name' => 'Ornament ' . $id,
                'status' => $status, 'customer_id' => 1, 'assigned_karigar_id' => 10,
                'deleted_at' => $id === 5 ? '2026-10-01 10:00:00' : null,
            ]);
        }
        $this->issueDb->table('order_receive_summaries')->insert(['order_id' => 2]);
        $this->issueDb->table('order_material_movements')->insertBatch([
            ['order_id' => 3, 'movement_type' => 'receive'],
            ['order_id' => 1, 'movement_type' => 'issue'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->issueDb->close();
        parent::tearDown();
    }

    public function testCompletedUnreceivedOrdersAreAvailableInBothMaterialPickers(): void
    {
        $diamond = new DiamondIssueOrderService($this->issueDb);
        $stone = new StoneIssueOrderService($this->issueDb);

        $this->assertSame([8, 7, 6, 1], array_column($diamond->orderOptions(), 'id'));
        $this->assertSame([8, 7, 6, 1], array_column($stone->orderOptions(), 'id'));
        $completed = array_column($diamond->orderOptions(), null, 'id')[1];
        $this->assertSame('Completed', $completed['status']);
        $this->assertSame('Ornament 1', $completed['order_name']);
        foreach ([1, 6, 7, 8] as $orderId) {
            $this->assertNull($diamond->validate($orderId));
            $this->assertNull($stone->validate($orderId));
        }
    }

    public function testReceivingInEitherReceiptSourceBlocksFurtherIssuement(): void
    {
        foreach ([2, 3] as $orderId) {
            $this->assertStringContainsString('already received', (new DiamondIssueOrderService($this->issueDb))->validate($orderId));
            $this->assertStringContainsString('already received', (new StoneIssueOrderService($this->issueDb))->validate($orderId));
        }
    }

    public function testNewerReceivedOrdersDoNotConsumeTheEligibleOrderLimit(): void
    {
        $orders = [];
        $summaries = [];
        $movements = [];
        foreach (range(100, 1099) as $id) {
            $orders[] = ['id' => $id, 'order_no' => 'ORD-' . $id, 'status' => 'Completed'];
            if ($id % 2 === 0) {
                $summaries[] = ['order_id' => $id];
            } else {
                $movements[] = ['order_id' => $id, 'movement_type' => 'receive'];
            }
        }
        $this->issueDb->table('orders')->insertBatch($orders);
        $this->issueDb->table('order_receive_summaries')->insertBatch($summaries);
        $this->issueDb->table('order_material_movements')->insertBatch($movements);
        // A nullable legacy row must not make NOT IN exclude every order.
        $this->issueDb->table('order_receive_summaries')->insert(['order_id' => null]);
        $this->issueDb->table('order_material_movements')->insert(['order_id' => null, 'movement_type' => 'receive']);

        $this->assertSame([8, 7, 6, 1], array_column((new DiamondIssueOrderService($this->issueDb))->orderOptions(), 'id'));
        $this->assertSame([8, 7, 6, 1], array_column((new StoneIssueOrderService($this->issueDb))->orderOptions(), 'id'));
        $this->assertSame([8, 7, 6, 2, 1], array_column((new StoneIssueOrderService($this->issueDb))->orderOptions(2), 'id'));
    }

    public function testStoneEditKeepsItsOlderReceivedOrderWhenThePickerIsFull(): void
    {
        $orders = array_map(static fn(int $id): array => [
            'id' => $id, 'order_no' => 'ORD-' . $id, 'status' => 'Completed',
        ], range(100, 1099));
        $this->issueDb->table('orders')->insertBatch($orders);

        $diamondOptions = (new DiamondIssueOrderService($this->issueDb))->orderOptions();
        $this->assertCount(1000, $diamondOptions);
        $this->assertSame(1099, $diamondOptions[0]['id']);
        $this->assertSame(100, $diamondOptions[999]['id']);

        $stoneOptions = (new StoneIssueOrderService($this->issueDb))->orderOptions(2);
        $this->assertCount(1000, $stoneOptions);
        $this->assertSame(1099, $stoneOptions[0]['id']);
        $this->assertSame(2, $stoneOptions[999]['id']);
    }

    public function testCancelledDeletedAndMissingOrdersRemainUnavailable(): void
    {
        foreach ([4, 5, 999] as $orderId) {
            $this->assertNotNull((new DiamondIssueOrderService($this->issueDb))->validate($orderId));
            $this->assertNotNull((new StoneIssueOrderService($this->issueDb))->validate($orderId));
        }
        $this->assertNull((new DiamondIssueOrderService($this->issueDb))->validate(0));
        $this->assertSame('Select the order this stone is issued for.', (new StoneIssueOrderService($this->issueDb))->validate(0));
    }

    public function testExistingReceivedStoneIssueCanKeepItsOrderDuringAnEdit(): void
    {
        $stone = new StoneIssueOrderService($this->issueDb);

        $this->assertNull($stone->validate(2, 2));
        $this->assertSame([8, 7, 6, 2, 1], array_column($stone->orderOptions(2), 'id'));
        $this->assertNotNull($stone->validate(3, 2));
    }

    public function testReceivingRecordedAfterSelectionIsRecheckedBeforePostingAnyMaterial(): void
    {
        $this->assertNull((new DiamondIssueOrderService($this->issueDb))->validate(1));
        $this->issueDb->table('order_receive_summaries')->insert(['order_id' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ornament already received');
        // A mixed voucher must fail before gold records or a voucher are created.
        (new MobileApprovalService($this->issueDb))->createIssuementWithoutApproval([
            'gold_lines' => [['item_id' => 1]],
            'diamond_lines' => [['allocation_order_id' => 1]],
        ], 10);
    }

    public function testLinkedBagOrderIsRecheckedEvenWithoutPerLineAllocation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ornament already received');

        (new MobileApprovalService($this->issueDb))->createIssuementWithoutApproval([
            'order_id' => 2, 'diamond_lines' => [['allocation_order_id' => null]],
        ], 10);
    }

    public function testStoneReceiptIsRecheckedBeforePostingGoldInAMixedVoucher(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ornament already received');

        (new MobileApprovalService($this->issueDb))->createIssuementWithoutApproval([
            'gold_lines' => [['item_id' => 1]],
            'stone_order_id' => 3, 'stone_lines' => [['item_id' => 1]],
        ], 10);
    }

    public function testBagActionsReflectReceivingAndCancelledState(): void
    {
        foreach ([
            'CREATE TABLE diamond_bags (id INTEGER PRIMARY KEY, bag_no TEXT, prepared_date TEXT, order_id INTEGER, pcs_balance REAL, cts_balance REAL, inventory_location_id INTEGER, notes TEXT, audit_image_path TEXT)',
            'CREATE TABLE diamond_bag_items (id INTEGER PRIMARY KEY, bag_id INTEGER)',
            'CREATE TABLE issue_headers (id INTEGER PRIMARY KEY, issue_date TEXT)',
            'CREATE TABLE issue_lines (id INTEGER PRIMARY KEY, bag_id INTEGER, issue_id INTEGER, pcs REAL, carat REAL, allocation_order_id INTEGER)',
            'CREATE TABLE karigars (id INTEGER PRIMARY KEY, name TEXT)',
        ] as $sql) {
            $this->issueDb->query($sql);
        }
        foreach ([1, 2, 4] as $orderId) {
            $this->issueDb->table('diamond_bags')->insert([
                'id' => $orderId, 'bag_no' => 'BAG-' . $orderId, 'order_id' => $orderId,
                'pcs_balance' => 10, 'cts_balance' => 1, 'inventory_location_id' => 1,
            ]);
        }
        $service = new DiamondBagService($this->issueDb);

        $this->assertTrue($service->issuedForOrder(1)[0]['can_issue']);
        $received = $service->issuedForOrder(2)[0];
        $this->assertFalse($received['can_issue']);
        $this->assertStringContainsString('already received', $received['issue_blocked_reason']);
        $cancelled = $service->issuedForOrder(4)[0];
        $this->assertFalse($cancelled['can_issue']);
        $this->assertStringContainsString('cancelled order', $cancelled['issue_blocked_reason']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ornament already received');
        $service->issuementContext(2);
    }
}
