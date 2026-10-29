<?php

namespace Tests\Unit;

use App\Services\OrderReadyStageService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;
use Throwable;

final class OrderReadyStageServiceTest extends CIUnitTestCase
{
    private BaseConnection $readyDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->readyDb = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => ''], false);
        foreach ([
            'CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT, order_name TEXT, status TEXT, customer_id INTEGER, parent_order_id INTEGER, followup_due_at TEXT, receiving_status TEXT, received_at TEXT, received_by INTEGER, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, item_status TEXT, updated_at TEXT)',
            'CREATE TABLE order_receive_summaries (id INTEGER PRIMARY KEY, order_id INTEGER)',
            'CREATE TABLE order_material_movements (id INTEGER PRIMARY KEY, order_id INTEGER, movement_type TEXT)',
            'CREATE TABLE order_status_history (id INTEGER PRIMARY KEY, order_id INTEGER, from_status TEXT, to_status TEXT, remarks TEXT, changed_by INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE order_followup_schedules (id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT, score_delta REAL, updated_at TEXT)',
            'CREATE TABLE mobile_tasks (id INTEGER PRIMARY KEY, reference_type TEXT, reference_id INTEGER, is_done INTEGER, status TEXT, score_delta REAL, updated_at TEXT)',
        ] as $sql) {
            $this->readyDb->query($sql);
        }
        $this->readyDb->table('customers')->insert(['id' => 1, 'name' => 'Meera Jewellers']);
        foreach ([1 => 'Completed', 2 => 'Completed', 3 => 'Ready', 4 => 'Cancelled', 5 => 'Completed', 6 => 'In Production', 7 => 'Completed'] as $id => $status) {
            $this->readyDb->table('orders')->insert([
                'id' => $id, 'order_no' => 'ORD-' . $id, 'order_name' => 'Ring ' . $id,
                'status' => $status, 'customer_id' => 1, 'parent_order_id' => $id === 6 ? 1 : null,
                'followup_due_at' => '2026-11-01 10:00:00', 'deleted_at' => $id === 5 ? '2026-10-01' : null,
                'receiving_status' => $id === 1 ? 'received' : 'pending',
                'received_at' => $id === 1 ? '2026-10-01 10:00:00' : null,
                'received_by' => $id === 1 ? 42 : null,
            ]);
            $this->readyDb->table('order_items')->insert(['order_id' => $id, 'item_status' => $status]);
        }
        $this->readyDb->table('order_receive_summaries')->insert(['order_id' => 2]);
        $this->readyDb->table('order_material_movements')->insertBatch([
            ['order_id' => 7, 'movement_type' => 'receive'],
            ['order_id' => 1, 'movement_type' => 'issue'],
        ]);
        $this->readyDb->table('order_followup_schedules')->insertBatch([
            ['id' => 1, 'order_id' => 1, 'status' => 'pending', 'score_delta' => -2],
            ['id' => 2, 'order_id' => 6, 'status' => 'pending', 'score_delta' => -2],
        ]);
        $this->readyDb->table('mobile_tasks')->insertBatch([
            ['id' => 1, 'reference_type' => 'order_followup', 'reference_id' => 1, 'is_done' => 0, 'status' => 'pending', 'score_delta' => -2],
            ['id' => 2, 'reference_type' => 'order_followup', 'reference_id' => 2, 'is_done' => 0, 'status' => 'pending', 'score_delta' => -2],
        ]);
    }

    protected function tearDown(): void
    {
        $this->readyDb->close();
        parent::tearDown();
    }

    public function testPickerIncludesUnreceivedCompletedOrdersAndSearchesBeforeItsLimit(): void
    {
        $service = new OrderReadyStageService($this->readyDb);
        $this->assertSame([6, 3, 1], array_map('intval', array_column($service->orderOptions(), 'id')));
        $this->assertSame([1], array_map('intval', array_column($service->orderOptions('ORD-1'), 'id')));
        $this->assertCount(3, $service->orderOptions('Meera'));
    }

    public function testReadyUrlRequiresAdminPermissionAndPostCsrf(): void
    {
        $routes = \Config\Services::routes(false);
        $routes->loadRoutes();
        foreach (['GET', 'POST'] as $method) {
            $filters = $routes->getFiltersForRoute('admin/orders/ready-stage', $method);
            $this->assertContains('adminAuth', $filters);
            $this->assertContains('permission:orders.status', $filters);
            if ($method === 'POST') {
                $this->assertContains('csrf', $filters);
            }
        }
    }

    public function testSelectedLegacyCompletedOrderAndItemsBecomeReadyWithoutReceivingOrFamilyChanges(): void
    {
        $result = (new OrderReadyStageService($this->readyDb))->markReady(1, 42);
        $this->assertTrue($result['changed']);
        $this->assertSame('Completed', $result['previous_status']);
        $this->assertSame('Ready', $this->order(1)['status']);
        $this->assertNull($this->order(1)['followup_due_at']);
        $this->assertSame('pending', $this->order(1)['receiving_status']);
        $this->assertNull($this->order(1)['received_at']);
        $this->assertNull($this->order(1)['received_by']);
        $this->assertSame('Ready', $this->readyDb->table('order_items')->where('order_id', 1)->get()->getRowArray()['item_status']);
        $this->assertSame('In Production', $this->order(6)['status']);
        $this->assertSame('2026-11-01 10:00:00', $this->order(6)['followup_due_at']);
        $this->assertSame(1, $this->readyDb->table('order_receive_summaries')->countAllResults());
        $this->assertSame(2, $this->readyDb->table('order_material_movements')->countAllResults());
        $history = $this->readyDb->table('order_status_history')->get()->getRowArray();
        $this->assertSame('Completed', $history['from_status']);
        $this->assertSame('Ready', $history['to_status']);
        $this->assertSame(42, (int) $history['changed_by']);
        $this->assertStringContainsString('Receiving is still pending', $history['remarks']);
        foreach (['order_followup_schedules', 'mobile_tasks'] as $table) {
            $row = $this->readyDb->table($table)->where('id', 1)->get()->getRowArray();
            $this->assertSame('cancelled', $row['status']);
            $this->assertSame(0.0, (float) $row['score_delta']);
            $this->assertSame('pending', $this->readyDb->table($table)->where('id', 2)->get()->getRowArray()['status']);
        }
    }

    public function testReceivedCancelledDeletedMissingAndAnonymousRequestsDoNotChangeOrders(): void
    {
        foreach ([[2, 42], [7, 42], [4, 42], [5, 42], [999, 42], [0, 42], [1, 0]] as [$id, $actor]) {
            try {
                (new OrderReadyStageService($this->readyDb))->markReady($id, $actor);
                $this->fail('Invalid stage correction was accepted for ' . $id);
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertSame('Completed', $this->order(1)['status']);
        $this->assertSame(0, $this->readyDb->table('order_status_history')->countAllResults());
    }

    public function testDuplicateSubmissionDoesNotCreateDuplicateHistory(): void
    {
        $service = new OrderReadyStageService($this->readyDb);
        $service->markReady(1, 42);
        $result = $service->markReady(1, 42);
        $this->assertFalse($result['changed']);
        $this->assertSame(1, $this->readyDb->table('order_status_history')->countAllResults());
    }

    public function testFailedAuditWriteRollsBackTheOrderItemsAndReminderChanges(): void
    {
        $this->readyDb->query("CREATE TRIGGER reject_ready_audit BEFORE INSERT ON order_status_history BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
        $failure = null;
        try {
            (new OrderReadyStageService($this->readyDb))->markReady(1, 42);
        } catch (Throwable $e) {
            $failure = $e;
        }
        $this->assertNotNull($failure, 'A failed audit must not save a stage correction.');
        $this->assertSame('Completed', $this->order(1)['status']);
        $this->assertSame('Completed', $this->readyDb->table('order_items')->where('order_id', 1)->get()->getRowArray()['item_status']);
        $this->assertSame('pending', $this->readyDb->table('order_followup_schedules')->where('id', 1)->get()->getRowArray()['status']);
        $this->assertSame('pending', $this->readyDb->table('mobile_tasks')->where('id', 1)->get()->getRowArray()['status']);
    }

    /** @return array<string,mixed> */
    private function order(int $id): array
    {
        return $this->readyDb->table('orders')->where('id', $id)->get()->getRowArray();
    }
}
