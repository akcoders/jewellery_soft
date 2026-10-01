<?php

namespace Tests\Unit;

use App\Services\WorkflowTaskService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class WorkflowTaskServiceTest extends CIUnitTestCase
{
    public function testAssignmentReassignmentAndCompletionScoreOnlyOnce(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
            'foreignKeys' => true,
        ], false);
        $db->query('CREATE TABLE mobile_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, admin_user_id INTEGER NOT NULL,
            title TEXT NOT NULL, note TEXT, scheduled_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\', is_done INTEGER NOT NULL DEFAULT 0,
            completed_at TEXT, completed_by INTEGER, score_delta REAL DEFAULT 0,
            counts_for_performance INTEGER DEFAULT 1, created_by INTEGER,
            reference_type TEXT, reference_id INTEGER, created_at TEXT, updated_at TEXT
        )');
        $service = new WorkflowTaskService($db);
        $dueAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $id = $service->assign('order_followup', 15, 7, 'Follow up order A', $dueAt, 1);
        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, $service->assign('order_followup', 15, 8, 'Follow up order A', $dueAt, 1));
        $this->assertSame(1, $db->table('mobile_tasks')->countAllResults());
        $this->assertSame(8, (int) $db->table('mobile_tasks')->where('id', $id)->get()->getRowArray()['admin_user_id']);

        $service->complete('order_followup', 15, 8);
        $completed = $db->table('mobile_tasks')->where('id', $id)->get()->getRowArray();
        $this->assertSame(1, (int) $completed['is_done']);
        $this->assertSame(2.0, (float) $completed['score_delta']);

        $service->complete('order_followup', 15, 8);
        $service->assign('order_followup', 15, 7, 'Different assignee', $dueAt, 1);
        $unchanged = $db->table('mobile_tasks')->where('id', $id)->get()->getRowArray();
        $this->assertSame(8, (int) $unchanged['admin_user_id']);
        $this->assertSame(2.0, (float) $unchanged['score_delta']);
        $db->close();
    }

    public function testCancelledAndOtherPersonCompletedTasksEarnNoPoints(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
            'foreignKeys' => true,
        ], false);
        $db->query('CREATE TABLE mobile_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, admin_user_id INTEGER NOT NULL,
            title TEXT NOT NULL, note TEXT, scheduled_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\', is_done INTEGER NOT NULL DEFAULT 0,
            completed_at TEXT, completed_by INTEGER, score_delta REAL DEFAULT 0,
            counts_for_performance INTEGER DEFAULT 1, created_by INTEGER,
            reference_type TEXT, reference_id INTEGER, created_at TEXT, updated_at TEXT
        )');
        $service = new WorkflowTaskService($db);
        $dueAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $cancelId = $service->assign('order_followup', 16, 7, 'Follow up B', $dueAt, 1);
        $service->cancel('order_followup', 16);
        $service->complete('order_followup', 16, 7);
        $cancelled = $db->table('mobile_tasks')->where('id', $cancelId)->get()->getRowArray();
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0.0, (float) $cancelled['score_delta']);

        $otherId = $service->assign('order_followup', 17, 7, 'Follow up C', $dueAt, 1);
        $service->complete('order_followup', 17, 9);
        $other = $db->table('mobile_tasks')->where('id', $otherId)->get()->getRowArray();
        $this->assertSame(1, (int) $other['is_done']);
        $this->assertSame(0.0, (float) $other['score_delta']);
        $db->close();
    }
}
