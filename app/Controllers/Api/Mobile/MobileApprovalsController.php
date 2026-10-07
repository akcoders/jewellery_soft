<?php

namespace App\Controllers\Api\Mobile;

use App\Services\MobileApprovalService;
use Throwable;

class MobileApprovalsController extends MobileBaseController
{
    public function index()
    {
        if ($response = $this->requireMobileAuth()) return $response;
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        $isAdmin = $this->isAdminReviewer($userId);
        return $this->ok([
            'items' => (new MobileApprovalService())->list($isAdmin ? null : $userId),
            'can_review' => $isAdmin,
        ]);
    }

    public function createCustomer()
    {
        if ($response = $this->requireMobileAuth()) return $response;
        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            return $this->fail('Customer name must be 2 to 150 characters.', 422);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail('Enter a valid customer email.', 422);
        }
        $clean = [
            'name' => $name,
            'phone' => mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 20),
            'email' => mb_substr($email, 0, 191),
            'gstin' => mb_substr(strtoupper(trim((string) ($payload['gstin'] ?? ''))), 0, 25),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'address' => [
                'line1' => mb_substr(trim((string) ($payload['line1'] ?? '')), 0, 255),
                'line2' => mb_substr(trim((string) ($payload['line2'] ?? '')), 0, 255),
                'city' => mb_substr(trim((string) ($payload['city'] ?? '')), 0, 120),
                'state' => mb_substr(trim((string) ($payload['state'] ?? '')), 0, 120),
                'country' => mb_substr(trim((string) ($payload['country'] ?? 'India')), 0, 120),
                'pincode' => mb_substr(trim((string) ($payload['pincode'] ?? '')), 0, 20),
            ],
        ];
        return $this->submit('customer_create', $clean, 'New customer: ' . $name);
    }

    public function createKarigar()
    {
        if ($response = $this->requireMobileAuth()) return $response;
        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            return $this->fail('Karigar name must be 2 to 150 characters.', 422);
        }
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail('Enter a valid karigar email.', 422);
        }
        $clean = [
            'name' => $name,
            'phone' => mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 20),
            'email' => mb_substr($email, 0, 191),
            'address' => mb_substr(trim((string) ($payload['address'] ?? '')), 0, 500),
            'city' => mb_substr(trim((string) ($payload['city'] ?? '')), 0, 120),
            'state' => mb_substr(trim((string) ($payload['state'] ?? '')), 0, 120),
            'pincode' => mb_substr(trim((string) ($payload['pincode'] ?? '')), 0, 20),
            'department' => mb_substr(trim((string) ($payload['department'] ?? '')), 0, 120),
            'skills_text' => trim((string) ($payload['skills_text'] ?? '')),
            'rate_per_gm' => max(0, (float) ($payload['rate_per_gm'] ?? 0)),
            'wastage_percentage' => max(0, (float) ($payload['wastage_percentage'] ?? 0)),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];
        return $this->submit('karigar_create', $clean, 'New karigar: ' . $name);
    }

    public function review(int $id)
    {
        if ($response = $this->requireMobileAuth()) return $response;
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        if (! $this->isAdminReviewer($userId)) {
            return $this->fail('Only admin can review approval requests.', 403);
        }
        $payload = $this->payload();
        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return $this->fail('Decision must be approve or reject.', 422);
        }
        try {
            $service = new MobileApprovalService();
            $request = $decision === 'approve'
                ? $service->approve($id, $userId, (string) ($payload['note'] ?? ''))
                : $service->reject($id, $userId, (string) ($payload['note'] ?? ''));
            return $this->ok(['request' => $request], 'Request ' . ($decision === 'approve' ? 'approved.' : 'rejected.'));
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    /** @param array<string,mixed> $payload */
    private function submit(string $type, array $payload, string $summary)
    {
        try {
            $request = (new MobileApprovalService())->submit(
                $type, $payload, (int) ($this->mobileAdmin['id'] ?? 0), $summary
            );
            return $this->ok(['request' => $request], 'Request sent for admin approval.', 201);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function isAdminReviewer(int $userId): bool
    {
        if ($userId <= 0) return false;
        if (! db_connect()->tableExists('user_roles')) return true;
        return db_connect()->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('ur.user_id', $userId)
            ->whereIn('r.role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
            ->countAllResults() > 0;
    }
}
