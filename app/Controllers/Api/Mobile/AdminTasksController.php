<?php

namespace App\Controllers\Api\Mobile;

use App\Models\MobileTaskModel;
use App\Services\MobilePushService;
use App\Services\RbacService;
use App\Services\StaffPerformanceService;
use Throwable;

class AdminTasksController extends MobileBaseController
{
    public function index()
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        if (! (new RbacService())->userCan((int) $this->mobileAdmin['id'], 'performance.tasks.read')) {
            return $this->fail('Task management permission is required.', 403);
        }
        $db = db_connect();
        $rows = $db->table('mobile_tasks t')
            ->select('t.*, assignee.name AS assignee_name, creator.name AS assigned_by_name')
            ->join('admin_users assignee', 'assignee.id = t.admin_user_id', 'left')
            ->join('admin_users creator', 'creator.id = t.created_by', 'left')
            ->where('t.counts_for_performance', 1)
            ->orderBy('t.id', 'DESC')->limit(150)->get()->getResultArray();
        return $this->ok(['tasks' => $rows, 'staff' => (new StaffPerformanceService())->staffOptions()]);
    }

    public function create()
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $adminId = (int) $this->mobileAdmin['id'];
        if (! (new RbacService())->userCan($adminId, 'performance.tasks.manage')) {
            return $this->fail('Task management permission is required.', 403);
        }
        $payload = $this->payload();
        $staffId = (int) ($payload['admin_user_id'] ?? 0);
        $title = trim((string) ($payload['title'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));
        $priority = trim((string) ($payload['priority'] ?? 'normal'));
        $dueValue = trim((string) ($payload['scheduled_at'] ?? ''));
        $dueStamp = strtotime($dueValue);
        if (! (new StaffPerformanceService())->isStaffUser($staffId)) return $this->fail('Select an active staff member.', 422);
        if ($title === '' || mb_strlen($title) > 160) return $this->fail('Enter a task title up to 160 characters.', 422);
        if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) return $this->fail('Select a valid priority.', 422);
        if ($dueValue === '' || $dueStamp === false || $dueStamp <= time()) return $this->fail('Select a future due date and time.', 422);
        $dueAt = date('Y-m-d H:i:s', $dueStamp);
        $id = (int) (new MobileTaskModel())->insert([
            'admin_user_id' => $staffId, 'title' => $title, 'note' => $note ?: null,
            'priority' => $priority, 'scheduled_at' => $dueAt,
            'status' => 'pending', 'is_done' => 0,
            'counts_for_performance' => 1, 'score_delta' => 0,
            'created_by' => $adminId,
        ], true);
        if ($id <= 0) return $this->fail('Could not create task.', 500);
        try {
            (new MobilePushService())->queueForAdmin($staffId, [
                'type' => 'task_assigned', 'reference_table' => 'mobile_tasks', 'reference_id' => $id,
                'dedupe_key' => 'task-assigned:' . $id, 'title' => 'New task assigned',
                'message' => $title . ' · Due ' . date('d M Y, h:i A', $dueStamp),
                'payload' => ['screen' => 'tasks', 'task_id' => $id],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Mobile task assignment push failed: {message}', ['message' => $e->getMessage()]);
        }
        return $this->ok(['id' => $id], 'Task assigned.', 201);
    }

    public function cancel(int $id)
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        if (! (new RbacService())->userCan((int) $this->mobileAdmin['id'], 'performance.tasks.manage')) {
            return $this->fail('Task management permission is required.', 403);
        }
        $model = new MobileTaskModel();
        $task = $model->find($id);
        if (! $task) return $this->fail('Task not found.', 404);
        if ((int) $task['is_done'] === 1 || (string) $task['status'] === 'cancelled') return $this->fail('Task is already closed.', 422);
        if (! empty($task['reference_type'])) return $this->fail('Complete or close the linked workflow instead.', 422);
        $db = db_connect();
        $db->table('mobile_tasks')->where('id', $id)->where('status', 'pending')->where('is_done', 0)
            ->update(['status' => 'cancelled', 'is_done' => 1, 'score_delta' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($db->affectedRows() !== 1) return $this->fail('Task is already closed.', 422);
        try {
            (new MobilePushService())->cancelByReference('mobile_tasks', $id);
        } catch (Throwable $e) {
            log_message('error', 'Mobile task cancellation push failed: {message}', ['message' => $e->getMessage()]);
        }
        return $this->ok(['id' => $id], 'Task cancelled.');
    }
}
