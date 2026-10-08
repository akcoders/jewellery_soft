<?php

namespace App\Services;

use App\Models\MobileTaskModel;
use CodeIgniter\Database\BaseConnection;

/** Keeps scored tasks tied to the work that actually completes them. */
class WorkflowTaskService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function recordCompletedInventoryWork(string $referenceTable, int $referenceId, int $workerId, string $title): void
    {
        if ($workerId <= 0 || ! (new StaffPerformanceService())->isStaffUser($workerId)) {
            return;
        }
        $type = 'inventory_' . $referenceTable;
        $this->assign($type, $referenceId, $workerId, $title,
            date('Y-m-d 23:59:59'), $workerId);
        $this->complete($type, $referenceId, $workerId);
    }

    public function assign(string $type, int $referenceId, int $userId, string $title, ?string $dueAt, ?int $assignedBy, string $note = ''): int
    {
        $tasks = new MobileTaskModel($this->db);
        $existing = $tasks->where('reference_type', $type)->where('reference_id', $referenceId)->first();
        $data = [
            'admin_user_id' => $userId,
            'title' => $title,
            'note' => $note ?: null,
            'scheduled_at' => $dueAt ?: date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'pending',
            'is_done' => 0,
            'completed_at' => null,
            'completed_by' => null,
            'score_delta' => 0,
            'counts_for_performance' => 1,
            'created_by' => $assignedBy,
            'reference_type' => $type,
            'reference_id' => $referenceId,
        ];
        if ($existing) {
            if ((int) $existing['is_done'] === 1) {
                return (int) $existing['id'];
            }
            $tasks->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }
        return (int) $tasks->insert($data, true);
    }

    public function complete(string $type, int $referenceId, int $completedBy, ?string $completedAt = null): void
    {
        $tasks = new MobileTaskModel($this->db);
        $task = $tasks->where('reference_type', $type)->where('reference_id', $referenceId)->first();
        if (! $task || (int) $task['is_done'] === 1 || (string) $task['status'] === 'cancelled') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $eventAt = $completedAt !== null && strtotime($completedAt) !== false
            ? date('Y-m-d H:i:s', strtotime($completedAt))
            : $now;
        $onTime = $eventAt <= (string) $task['scheduled_at'];
        $this->db->table('mobile_tasks')->where('id', (int) $task['id'])
            ->where('is_done', 0)->where('status', 'pending')->update([
            'is_done' => 1,
            'status' => $onTime ? 'completed_on_time' : 'completed_late',
            'completed_at' => $eventAt,
            'completed_by' => $completedBy,
            'score_delta' => $completedBy === (int) $task['admin_user_id']
                ? ($onTime ? StaffPerformanceService::TASK_ON_TIME_POINTS : StaffPerformanceService::TASK_LATE_POINTS)
                : 0,
            'updated_at' => $now,
        ]);
    }

    public function cancel(string $type, int $referenceId): void
    {
        (new MobileTaskModel($this->db))->where('reference_type', $type)
            ->where('reference_id', $referenceId)->where('is_done', 0)
            ->set(['status' => 'cancelled', 'score_delta' => 0])->update();
    }
}
