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
        $service = new MobileApprovalService();
        $items = $service->list(null, 'pending');
        $historyItems = $service->list(null, 'history');
        $followupGroups = [];
        $otherItems = [];
        foreach ($items as $item) {
            if ((string) ($item['request_type'] ?? '') !== 'followup') {
                $otherItems[] = $item;
                continue;
            }

            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $orderId = (int) (($item['followup_order_id'] ?? 0) ?: ($payload['order_id'] ?? 0));
            $key = $orderId > 0 ? 'order-' . $orderId : 'request-' . (int) ($item['id'] ?? 0);
            if (! isset($followupGroups[$key])) {
                $followupGroups[$key] = [
                    'order_id' => $orderId,
                    'order_no' => (string) (($item['followup_order_no'] ?? '') ?: ($payload['order_no'] ?? ('Order #' . $orderId))),
                    'requests' => [],
                    'chain' => is_array($item['followup_chain'] ?? null) ? $item['followup_chain'] : [],
                    'oldest_created_at' => (string) ($item['created_at'] ?? ''),
                ];
            }
            $followupGroups[$key]['requests'][] = $item;
            $createdAt = (string) ($item['created_at'] ?? '');
            if ($createdAt !== '' && ($followupGroups[$key]['oldest_created_at'] === '' || $createdAt < $followupGroups[$key]['oldest_created_at'])) {
                $followupGroups[$key]['oldest_created_at'] = $createdAt;
            }
        }
        foreach ($followupGroups as &$group) {
            usort($group['requests'], static fn(array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));
        }
        unset($group);
        uasort($followupGroups, static fn(array $a, array $b): int => strcmp((string) ($a['oldest_created_at'] ?? ''), (string) ($b['oldest_created_at'] ?? '')));

        return view('admin/mobile_approvals/index', [
            'title' => 'Mobile Approvals',
            'items' => $items,
            'historyItems' => $historyItems,
            'pendingCount' => count($items),
            'pendingFollowupCount' => count($items) - count($otherItems),
            'followupGroups' => array_values($followupGroups),
            'otherItems' => $otherItems,
            'approvedCount' => count(array_filter($historyItems, static fn(array $row): bool => ($row['status'] ?? '') === 'approved')),
            'rejectedCount' => count(array_filter($historyItems, static fn(array $row): bool => ($row['status'] ?? '') === 'rejected')),
        ]);
    }

    public function review(int $id)
    {
        if (! $this->isAdminReviewer()) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Admin approval access is required.');
        }
        $decision = strtolower(trim((string) $this->request->getPost('decision')));
        if (! in_array($decision, ['approve', 'approve_no_point', 'reject'], true)) {
            return redirect()->back()->with('error', 'Select a valid approval decision.');
        }
        try {
            $service = new MobileApprovalService();
            if ($decision !== 'reject') {
                $request = $service->approve($id, (int) session('admin_id'), (string) $this->request->getPost('note'), $decision !== 'approve_no_point');
            } else {
                $request = $service->reject($id, (int) session('admin_id'), (string) $this->request->getPost('note'));
            }
            $message = ! empty($request['was_reapproved'])
                ? 'Issuement re-approved and processed successfully.'
                : ($decision === 'approve_no_point' ? 'Follow-up approved with no performance points.' : ($decision === 'approve'
                ? 'Request approved successfully.'
                : (($request['request_type'] ?? '') === 'followup' ? 'Followup disapproved.' : 'Request disapproved successfully.')));
            return redirect()->back()->with('success', $message);
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
