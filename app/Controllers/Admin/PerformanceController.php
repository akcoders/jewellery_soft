<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\MobileTaskModel;
use App\Services\MobilePushService;
use App\Services\StaffPerformanceService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class PerformanceController extends BaseController
{
    private StaffPerformanceService $performanceService;
    private MobileTaskModel $taskModel;
    private MobilePushService $pushService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->performanceService = new StaffPerformanceService();
        $this->taskModel = new MobileTaskModel();
        $this->pushService = new MobilePushService();
    }

    public function dashboard(): string
    {
        $year = min(2100, max(2025, (int) ($this->request->getGet('year') ?: date('Y'))));
        $month = min(12, max(1, (int) ($this->request->getGet('month') ?: date('n'))));
        $staffId = max(0, (int) ($this->request->getGet('staff_id') ?: 0));
        $data = $this->performanceService->dashboardData($year, $month, $staffId > 0 ? $staffId : null);

        return view('admin/performance/dashboard', [
            'title' => 'Staff Performance',
            'year' => $year,
            'month' => $month,
            'staffId' => $staffId,
            'staff' => $this->performanceService->staffOptions(),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'eventsByUser' => $data['events_by_user'],
            'scoreRules' => $this->performanceService->scoreRules(),
        ]);
    }

    public function tasks(): string
    {
        $rows = db_connect()->table('mobile_tasks t')
            ->select('t.*, assignee.name as assignee_name, assignee.email as assignee_email, creator.name as created_by_name')
            ->join('admin_users assignee', 'assignee.id = t.admin_user_id', 'left')
            ->join('admin_users creator', 'creator.id = t.created_by', 'left')
            ->where('t.counts_for_performance', 1)
            ->orderBy('t.scheduled_at', 'DESC')
            ->orderBy('t.id', 'DESC')
            ->get()->getResultArray();

        return view('admin/performance/tasks', [
            'title' => 'Staff Tasks',
            'rows' => $rows,
            'staff' => $this->performanceService->staffOptions(),
        ]);
    }

    public function storeTask()
    {
        $rules = [
            'admin_user_id' => 'required|integer|greater_than[0]',
            'title' => 'required|max_length[160]',
            'note' => 'permit_empty',
            'priority' => 'required|in_list[low,normal,high,urgent]',
            'scheduled_at' => 'required|valid_date',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $staffId = (int) $this->request->getPost('admin_user_id');
        if (! $this->performanceService->isStaffUser($staffId)) {
            return redirect()->back()->withInput()->with('error', 'Task can only be assigned to active non-admin staff.');
        }

        try {
            $dueAt = (new DateTimeImmutable((string) $this->request->getPost('scheduled_at'), new DateTimeZone('Asia/Kolkata')))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid due date and time.');
        }
        if ($dueAt <= date('Y-m-d H:i:s')) {
            return redirect()->back()->withInput()->with('error', 'Task due time must be in the future.');
        }

        $title = trim((string) $this->request->getPost('title'));
        $note = trim((string) $this->request->getPost('note'));
        $taskId = (int) $this->taskModel->insert([
            'admin_user_id' => $staffId,
            'title' => $title,
            'note' => $note !== '' ? $note : null,
            'priority' => (string) $this->request->getPost('priority'),
            'scheduled_at' => $dueAt,
            'status' => 'pending',
            'is_done' => 0,
            'counts_for_performance' => 1,
            'score_delta' => 0,
            'created_by' => (int) session('admin_id'),
        ], true);

        $payload = ['type' => 'task', 'task_id' => $taskId, 'screen' => 'tasks'];
        $this->pushService->queueForAdmin($staffId, [
            'type' => 'task_assigned',
            'reference_table' => 'mobile_tasks',
            'reference_id' => $taskId,
            'dedupe_key' => 'task-assigned:' . $taskId,
            'title' => 'New task assigned',
            'message' => $title . ' · Due ' . date('d M Y, h:i A', strtotime($dueAt)),
            'payload' => $payload,
        ]);
        $this->pushService->queueForAdmin($staffId, [
            'type' => 'task',
            'reference_table' => 'mobile_tasks',
            'reference_id' => $taskId,
            'dedupe_key' => 'task-due:' . $taskId,
            'title' => 'Task due now',
            'message' => $title,
            'scheduled_at' => $dueAt,
            'payload' => $payload,
        ]);

        return redirect()->to(site_url('admin/performance/tasks'))->with('success', 'Task assigned and mobile notification queued.');
    }

    public function cancelTask(int $id)
    {
        $task = $this->taskModel->find($id);
        if (! $task || (int) ($task['is_done'] ?? 0) === 1) {
            return redirect()->back()->with('error', 'Pending task not found.');
        }
        $this->taskModel->update($id, ['status' => 'cancelled', 'is_done' => 1, 'score_delta' => 0]);
        $this->pushService->cancelByReference('mobile_tasks', $id);
        return redirect()->back()->with('success', 'Task cancelled. It will not affect performance.');
    }
}
