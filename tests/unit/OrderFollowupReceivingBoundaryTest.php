<?php

namespace Tests\Unit;

use App\Services\OrderFollowupService;
use App\Services\OrderWorkflowService;
use App\Services\ProductionDataImportService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Jewellery;
use ReflectionMethod;
use RuntimeException;
use Throwable;

final class OrderFollowupReceivingBoundaryTest extends CIUnitTestCase
{
    private BaseConnection $workflowDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowDb = Database::connect([
            'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
        ], false);
        foreach ([
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT, order_name TEXT, parent_order_id INTEGER, status TEXT, followup_assigned_to INTEGER, followup_due_at TEXT, deleted_at TEXT, updated_at TEXT)',
            'CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, item_status TEXT, updated_at TEXT)',
            'CREATE TABLE order_receive_summaries (id INTEGER PRIMARY KEY, order_id INTEGER)',
            'CREATE TABLE order_material_movements (id INTEGER PRIMARY KEY, order_id INTEGER, movement_type TEXT)',
            'CREATE TABLE order_status_history (id INTEGER PRIMARY KEY, order_id INTEGER, from_status TEXT, to_status TEXT, remarks TEXT, changed_by INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE order_followups (id INTEGER PRIMARY KEY, order_id INTEGER, followup_scope TEXT, stage TEXT, description TEXT, next_followup_date TEXT, followup_taken_by INTEGER, followup_taken_on TEXT, image_name TEXT, image_path TEXT, approval_status TEXT, points_enabled INTEGER, normalized_text TEXT, normalized_fingerprint TEXT, duplicate_followup_id INTEGER, duplicate_reason TEXT, reviewed_by INTEGER, reviewed_at TEXT, review_note TEXT, created_at TEXT, updated_at TEXT)',
        ] as $sql) {
            $this->workflowDb->query($sql);
        }
        foreach ([1 => 'QC', 2 => 'QC', 3 => 'Completed', 4 => 'Cancelled', 5 => 'Ready', 6 => 'QC', 7 => 'QC'] as $id => $status) {
            $this->workflowDb->table('orders')->insert([
                'id' => $id, 'order_no' => 'ORD-' . $id, 'order_name' => 'Ornament ' . $id,
                'parent_order_id' => $id === 1 ? null : 1, 'status' => $status,
                'followup_assigned_to' => 10, 'followup_due_at' => '2099-01-01 10:00:00',
                'deleted_at' => $id === 7 ? '2026-10-01 10:00:00' : null,
            ]);
            $this->workflowDb->table('order_items')->insert(['id' => $id, 'order_id' => $id, 'item_status' => $status]);
        }
        $this->workflowDb->table('order_receive_summaries')->insert(['order_id' => 3]);
        $this->workflowDb->table('order_material_movements')->insert(['order_id' => 6, 'movement_type' => 'receive']);
    }

    protected function tearDown(): void
    {
        $this->workflowDb->close();
        parent::tearDown();
    }

    public function testFollowupChoicesEndAtReadyAndKeepTheFullReportingLifecycle(): void
    {
        $config = config(Jewellery::class);
        $this->assertSame(['Confirmed', 'In Production', 'QC', 'Ready'], $config->followupStages);
        $this->assertContains('Completed', $config->orderStatuses);
        $this->assertContains('Packed', $config->orderStatuses);
        $workflow = new OrderWorkflowService($this->workflowDb);
        foreach ($config->followupStages as $stage) {
            $this->assertNull($workflow->followupStageError($stage));
        }
        foreach (['Completed', 'Complete', 'completed', 'Packed', 'Dispatched', 'Delivered', 'Cancelled'] as $stage) {
            $this->assertNotNull($workflow->followupStageError($stage));
        }
    }

    public function testNewCompletedFollowupsAreRejectedWithoutSavingOrUpdatingOrders(): void
    {
        foreach (['Completed', 'Complete'] as $stage) {
            try {
                (new OrderFollowupService($this->workflowDb))->create(1, [
                    'stage' => $stage, 'description' => 'QC inspection finished; ornament can be collected.',
                ], 10);
                $this->fail('Receiving-only completion must not be accepted as a follow-up.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('only after finished jewellery receiving', $error->getMessage());
            }
        }
        $this->assertSame('QC', $this->orderStatus(1));
        $this->assertSame(0, $this->workflowDb->table('order_followups')->countAllResults());
        $this->assertSame(0, $this->workflowDb->table('order_status_history')->countAllResults());
    }

    public function testLegacyPendingCompletedFollowupCannotBeApprovedEvenWithLaterHistory(): void
    {
        $this->pendingFollowup('Completed');
        $this->workflowDb->table('order_followups')->insert([
            'id' => 11, 'order_id' => 1, 'stage' => 'QC', 'approval_status' => 'approved',
            'followup_taken_on' => '2099-01-02 10:00:00',
        ]);
        try {
            (new OrderFollowupService($this->workflowDb))->reviewPending(10, true, 20);
            $this->fail('Legacy queued completion must be rejected at approval time.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Select Ready for the final follow-up', $error->getMessage());
        }
        $this->assertSame('QC', $this->orderStatus(1));
        $this->assertSame('pending', $this->workflowDb->table('order_followups')->where('id', 10)->get()->getRow('approval_status'));
        $this->assertSame(0, $this->workflowDb->table('order_status_history')->countAllResults());
        $rejected = (new OrderFollowupService($this->workflowDb))->reviewPending(10, false, 20, 'Resubmit with Ready as the final production stage.');
        $this->assertSame('rejected', $rejected['approval_status']);
        $this->assertSame('QC', $this->orderStatus(1));
    }

    public function testRecordedReceiptBlocksNewFollowupsEvenWhenLegacyStatusIsStillQc(): void
    {
        $this->workflowDb->table('order_receive_summaries')->insert(['order_id' => 1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Follow-up is not allowed for this order status.');
        (new OrderFollowupService($this->workflowDb))->create(1, [
            'stage' => 'Ready', 'description' => 'Final quality inspection is complete.',
        ], 10);
    }

    public function testOlderApprovalPreservesTheStageOfANewerApprovedFollowup(): void
    {
        $this->pendingFollowup('In Production');
        $this->workflowDb->table('order_followups')->insert([
            'id' => 11, 'order_id' => 1, 'stage' => 'QC', 'approval_status' => 'approved',
            'followup_taken_on' => '2099-01-02 10:00:00',
        ]);
        $result = (new OrderFollowupService($this->workflowDb))->reviewPending(10, true, 20);

        $this->assertSame('QC', $this->orderStatus(1));
        $this->assertSame('QC', $this->workflowDb->table('order_items')->where('order_id', 1)->get()->getRow('item_status'));
        $this->assertSame('QC', $result['from_status']);
        $this->assertSame('QC', $result['to_status']);
        $this->assertSame('approved', $result['approval_status']);
        $this->assertSame(0, $this->workflowDb->table('order_status_history')->countAllResults());
    }

    public function testReadyApprovalUpdatesOpenFamilyMembersAndPreservesReceivedOrClosedOrders(): void
    {
        $this->pendingFollowup('Ready', 'family');
        $result = (new OrderFollowupService($this->workflowDb))->reviewPending(10, true, 20);

        $this->assertSame('Ready', $result['to_status']);
        $this->assertSame('Ready', $this->orderStatus(1));
        $this->assertSame('Ready', $this->orderStatus(2));
        $this->assertSame('Completed', $this->orderStatus(3));
        $this->assertSame('Cancelled', $this->orderStatus(4));
        $this->assertSame('Ready', $this->orderStatus(5));
        $this->assertSame('QC', $this->orderStatus(6));
        $this->assertSame('QC', $this->orderStatus(7));
        $this->assertSame('Ready', $this->workflowDb->table('order_items')->where('order_id', 2)->get()->getRow('item_status'));
        $this->assertSame([1, 2], array_map('intval', array_column($this->workflowDb->table('order_status_history')->get()->getResultArray(), 'order_id')));
        $this->assertSame(1, $this->workflowDb->table('order_receive_summaries')->countAllResults());
    }

    public function testQueuedProgressApprovalAfterReceivingCannotDowngradeCompletedOrder(): void
    {
        $this->workflowDb->table('orders')->where('id', 1)->update(['status' => 'Completed']);
        $this->workflowDb->table('order_receive_summaries')->insert(['order_id' => 1]);
        $this->pendingFollowup('Ready');
        $result = (new OrderFollowupService($this->workflowDb))->reviewPending(10, true, 20);

        $this->assertSame('Completed', $this->orderStatus(1));
        $this->assertSame('Completed', $result['from_status']);
        $this->assertSame('Completed', $result['to_status']);
        $this->assertSame(0, $this->workflowDb->table('order_status_history')->countAllResults());
    }

    public function testStageApplicationReloadsCurrentTerminalStatusInsteadOfAnEarlierOrderSnapshot(): void
    {
        $earlierOrder = $this->workflowDb->table('orders')->where('id', 1)->get()->getRowArray();
        $applyStage = new ReflectionMethod(OrderFollowupService::class, 'applyApprovedStage');
        foreach (['Cancelled', 'Completed'] as $currentStatus) {
            $this->workflowDb->table('orders')->where('id', 1)->update(['status' => $currentStatus]);
            $this->workflowDb->table('order_items')->where('order_id', 1)->update(['item_status' => $currentStatus]);
            $this->workflowDb->transStart();
            $applyStage->invoke(new OrderFollowupService($this->workflowDb), $earlierOrder, [
                'order_id' => 1, 'followup_scope' => 'order', 'stage' => 'Ready',
                'description' => 'Inspection completed before the order lifecycle changed.',
            ], 10);
            $this->workflowDb->transComplete();

            $this->assertSame($currentStatus, $this->orderStatus(1));
            $this->assertSame($currentStatus, $this->workflowDb->table('order_items')->where('order_id', 1)->get()->getRow('item_status'));
        }
        $this->assertSame(0, $this->workflowDb->table('order_status_history')->countAllResults());
    }

    public function testFamilyRemindersChangeOnlyForOpenUnreceivedChildren(): void
    {
        (new OrderFollowupService($this->workflowDb))->create(1, [
            'stage' => 'QC', 'description' => 'The stone setting is secure; polishing verification is scheduled next.',
            'followup_scope' => 'family', 'next_followup_date' => '2100-01-01 10:00:00',
        ], 10);
        $this->assertSame('2100-01-01 10:00:00', $this->workflowDb->table('orders')->where('id', 2)->get()->getRow('followup_due_at'));
        foreach ([3, 4, 5, 6, 7] as $protectedId) {
            $this->assertSame('2099-01-01 10:00:00', $this->workflowDb->table('orders')->where('id', $protectedId)->get()->getRow('followup_due_at'));
        }
    }

    public function testReadyClearsRemindersForNewlyReadyChildrenWhilePreservingClosedMembers(): void
    {
        (new OrderFollowupService($this->workflowDb))->create(1, [
            'stage' => 'Ready', 'description' => 'The final quality inspection passed and the family order is ready for collection.',
            'followup_scope' => 'family',
        ], 10);
        $this->assertSame('Ready', $this->orderStatus(2));
        $this->assertNull($this->workflowDb->table('orders')->where('id', 2)->get()->getRow('followup_due_at'));
        foreach ([3, 4, 5, 6, 7] as $protectedId) {
            $this->assertSame('2099-01-01 10:00:00', $this->workflowDb->table('orders')->where('id', $protectedId)->get()->getRow('followup_due_at'));
        }
    }

    public function testDirectApprovalRollsBackStageAndItemsWhenAuditCannotBeWritten(): void
    {
        $this->pendingFollowup('Ready');
        $this->workflowDb->query("CREATE TRIGGER reject_followup_audit BEFORE INSERT ON order_status_history BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
        $failure = null;
        try {
            (new OrderFollowupService($this->workflowDb))->reviewPending(10, true, 20);
        } catch (Throwable $error) {
            $failure = $error;
        }
        $this->assertNotNull($failure);
        $this->assertSame('QC', $this->orderStatus(1));
        $this->assertSame('QC', $this->workflowDb->table('order_items')->where('order_id', 1)->get()->getRow('item_status'));
        $this->assertSame('pending', $this->workflowDb->table('order_followups')->where('id', 10)->get()->getRow('approval_status'));
    }

    public function testReadyFollowupNeedsNoNextReminderButProgressFollowupDoes(): void
    {
        $service = new OrderFollowupService($this->workflowDb);
        try {
            $service->create(1, ['stage' => 'QC', 'description' => 'Quality inspection is underway.'], 10);
            $this->fail('A continuing production follow-up requires its next reminder.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Next follow-up date is required', $error->getMessage());
        }
        $created = $service->create(1, [
            'stage' => 'Ready', 'description' => 'Final quality inspection is complete and the ornament is ready for collection.',
        ], 10);
        $this->assertSame('Ready', $created['stage']);
        $this->assertSame('Ready', $this->orderStatus(1));
        $this->assertNull($this->workflowDb->table('order_followups')->where('id', $created['id'])->get()->getRow('next_followup_date'));
        $this->assertSame(1, $this->workflowDb->table('order_receive_summaries')->countAllResults());
    }

    public function testManualCompletionRequiresARecordedReceiptAndNewOrdersCannotSkipReceiving(): void
    {
        $workflow = new OrderWorkflowService($this->workflowDb);
        foreach (['Packed', 'Dispatched', 'Delivered', 'Completed', 'Complete', 'completed'] as $status) {
            $this->assertNotNull($workflow->statusError($status));
            $this->assertNotNull($workflow->statusError($status, 1));
        }
        foreach (['Packed', 'Dispatched', 'Delivered', 'Completed', 'Complete'] as $status) {
            $this->assertNull($workflow->statusError($status, 3));
            $this->assertNull($workflow->statusError($status, 6));
        }
        $this->assertNull($workflow->statusError('Draft'));
        $this->assertNull($workflow->statusError('Ready'));
        $this->assertNotNull($workflow->statusError('Unknown status'));
    }

    public function testProductionImportCompletesOnlyReceivedNonCancelledNonDeletedOrders(): void
    {
        $this->workflowDb->query('CREATE TABLE job_cards (id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT, qc_status TEXT, updated_at TEXT)');
        foreach (range(1, 7) as $id) {
            $this->workflowDb->table('job_cards')->insert(['id' => $id, 'order_id' => $id, 'status' => 'Pending', 'qc_status' => 'Pending']);
        }
        $this->workflowDb->table('order_receive_summaries')->insertBatch([['order_id' => 4], ['order_id' => 7]]);
        (new ReflectionMethod(ProductionDataImportService::class, 'completeAllProductionOrders'))
            ->invoke(new ProductionDataImportService($this->workflowDb));

        $this->assertSame('QC', $this->orderStatus(1));
        $this->assertSame('QC', $this->orderStatus(2));
        $this->assertSame('Completed', $this->orderStatus(3));
        $this->assertSame('Cancelled', $this->orderStatus(4));
        $this->assertSame('Ready', $this->orderStatus(5));
        $this->assertSame('Completed', $this->orderStatus(6));
        $this->assertSame('QC', $this->orderStatus(7));
        $this->assertSame('Completed', $this->workflowDb->table('order_items')->where('order_id', 6)->get()->getRow('item_status'));
        $this->assertSame('Pending', $this->workflowDb->table('job_cards')->where('order_id', 4)->get()->getRow('status'));
        $this->assertSame([6], array_map('intval', array_column($this->workflowDb->table('order_status_history')->get()->getResultArray(), 'order_id')));
    }

    private function pendingFollowup(string $stage, string $scope = 'order'): void
    {
        $this->workflowDb->table('order_followups')->insert([
            'id' => 10, 'order_id' => 1, 'followup_scope' => $scope, 'stage' => $stage,
            'description' => 'Final quality inspection is complete.', 'approval_status' => 'pending',
            'followup_taken_by' => 10, 'followup_taken_on' => '2099-01-01 10:00:00',
        ]);
    }

    private function orderStatus(int $orderId): string
    {
        return (string) $this->workflowDb->table('orders')->where('id', $orderId)->get()->getRow('status');
    }
}
