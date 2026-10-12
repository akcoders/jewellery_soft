<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\OrderWorkRequestService;
use App\Services\StaffPerformanceService;
use Throwable;

class OrderWorkRequestController extends BaseController
{
    public function index(): string
    {
        $adminId = (int) session('admin_id');
        $items = (new OrderWorkRequestService())->listForUser($adminId);
        return view('admin/order_work_requests/index', [
            'title' => 'Order Requests',
            'items' => $items,
            'staff' => (new StaffPerformanceService())->staffOptions(),
            'pendingCount' => count(array_filter(
                $items,
                static fn(array $row): bool => (string) ($row['status'] ?? '') === 'pending'
            )),
        ]);
    }

    public function review(int $id)
    {
        try {
            $result = (new OrderWorkRequestService())->review(
                $id,
                (int) session('admin_id'),
                [
                    'decision' => $this->request->getPost('decision'),
                    'review_note' => $this->request->getPost('review_note'),
                    'assigned_to' => $this->request->getPost('assigned_to'),
                    'approved_due_at' => $this->request->getPost('approved_due_at'),
                    'task_due_at' => $this->request->getPost('task_due_at'),
                    'followup_due_at' => $this->request->getPost('followup_due_at'),
                ]
            );
            return redirect()->back()->with(
                'success',
                'Order request ' . ((string) $result['decision'] === 'approve' ? 'approved.' : 'rejected.')
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
