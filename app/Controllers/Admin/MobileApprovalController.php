<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\MobileApprovalService;
use Throwable;

class MobileApprovalController extends BaseController
{
    public function index(): string
    {
        if (! $this->isAdminReviewer()) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Admin approval access is required.');
        }
        $items = (new MobileApprovalService())->list();
        return view('admin/mobile_approvals/index', [
            'title' => 'Mobile Approvals',
            'items' => $items,
            'pendingCount' => count(array_filter($items, static fn(array $row): bool => ($row['status'] ?? '') === 'pending')),
        ]);
    }

    public function review(int $id)
    {
        if (! $this->isAdminReviewer()) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Admin approval access is required.');
        }
        $decision = strtolower(trim((string) $this->request->getPost('decision')));
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return redirect()->back()->with('error', 'Select a valid approval decision.');
        }
        try {
            $service = new MobileApprovalService();
            if ($decision === 'approve') {
                $service->approve($id, (int) session('admin_id'), (string) $this->request->getPost('note'));
            } else {
                $service->reject($id, (int) session('admin_id'), (string) $this->request->getPost('note'));
            }
            return redirect()->back()->with('success', 'Request ' . ($decision === 'approve' ? 'approved' : 'rejected') . ' successfully.');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function isAdminReviewer(): bool
    {
        $userId = (int) session('admin_id');
        if (! db_connect()->tableExists('user_roles')) return true;
        return db_connect()->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('ur.user_id', $userId)
            ->whereIn('r.role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
            ->countAllResults() > 0;
    }
}
