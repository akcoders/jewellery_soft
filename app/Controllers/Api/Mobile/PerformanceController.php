<?php

namespace App\Controllers\Api\Mobile;

use App\Services\StaffPerformanceService;

class PerformanceController extends MobileBaseController
{
    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $service = new StaffPerformanceService();
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        if (! $service->isStaffUser($userId)) {
            return $this->fail('Performance scoring is available only for non-admin staff.', 403);
        }

        $year = min(2100, max(2025, (int) ($this->request->getGet('year') ?: date('Y'))));
        $month = min(12, max(1, (int) ($this->request->getGet('month') ?: date('n'))));
        $data = $service->ownPerformance($userId, $year, $month);
        return $this->ok($data);
    }
}
