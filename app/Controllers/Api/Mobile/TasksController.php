<?php

namespace App\Controllers\Api\Mobile;

use App\Models\MobileTaskModel;
use App\Services\MobilePushService;
use Throwable;

class TasksController extends MobileBaseController
{
    private MobileTaskModel $taskModel;
    private MobilePushService $pushService;

    public function __construct()
    {
        $this->taskModel = new MobileTaskModel();
        $this->pushService = new MobilePushService();
    }

    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $query = trim((string) $this->request->getGet('q'));
        $builder = $this->taskModel
            ->where('admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->where('is_done', 0)
            ->where('status !=', 'cancelled');

        if ($query !== '') {
            $builder = $builder->groupStart()
                ->like('title', $query)
                ->orLike('note', $query)
                ->groupEnd();
        }

        $rows = $builder->orderBy('scheduled_at', 'ASC')->findAll();

        return $this->ok($rows);
    }

    public function create()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $title = trim((string) ($payload['title'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));
        $scheduledAtRaw = trim((string) ($payload['scheduled_at'] ?? ''));

        if ($title === '') {
            return $this->fail('Task title is required.', 422);
        }
        if ($scheduledAtRaw === '') {
            return $this->fail('scheduled_at is required.', 422);
        }

        $scheduledTs = strtotime($scheduledAtRaw);
        if ($scheduledTs === false) {
            return $this->fail('Invalid scheduled_at format.', 422);
        }

        $scheduledAt = date('Y-m-d H:i:s', $scheduledTs);
        $taskId = 0;
        $push = ['queued' => false, 'message' => 'Notification was not queued.'];

        try {
            db_connect()->transException(true)->transStart();

            $taskId = (int) $this->taskModel->insert([
                'admin_user_id' => (int) ($this->mobileAdmin['id'] ?? 0),
                'title' => $title,
                'note' => $note !== '' ? $note : null,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
                'is_done' => 0,
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $push = $this->pushService->queueForAdminRow($this->mobileAdmin ?? [], [
                'type' => 'task',
                'reference_table' => 'mobile_tasks',
                'reference_id' => $taskId,
                'title' => 'Task Reminder',
                'message' => $note !== '' ? $note : $title,
                'scheduled_at' => $scheduledAt,
                'payload' => [
                    'type' => 'task',
                    'task_id' => $taskId,
                    'title' => $title,
                ],
            ]);

            db_connect()->transComplete();
        } catch (Throwable $e) {
            db_connect()->transRollback();
            return $this->fail('Could not save task: ' . $e->getMessage(), 500);
        }

        $row = $this->taskModel->find($taskId);

        return $this->ok([
            'task' => $row,
            'notification' => $push,
        ], 'Task saved.');
    }

    public function delete(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $task = $this->taskModel
            ->where('id', $id)
            ->where('admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->first();

        if (! is_array($task)) {
            return $this->fail('Task not found.', 404);
        }

        try {
            db_connect()->transException(true)->transStart();
            $this->taskModel->update($id, [
                'is_done' => 1,
                'status' => 'cancelled',
            ]);
            $this->pushService->cancelByReference('mobile_tasks', $id);
            db_connect()->transComplete();
        } catch (Throwable $e) {
            db_connect()->transRollback();
            return $this->fail('Could not delete task: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $id], 'Task removed.');
    }
}
