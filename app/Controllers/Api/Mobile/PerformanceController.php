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

        [$year, $month] = $this->period();
        $data = $service->ownPerformance($userId, $year, $month);
        return $this->ok($data);
    }

    /** Team leaderboard for administrators: every staff member's score, target and rewards. */
    public function team()
    {
        $authFail = $this->requireTeamAccess();
        if ($authFail) {
            return $authFail;
        }

        [$year, $month] = $this->period();
        $service = new StaffPerformanceService();
        $data = $service->dashboardData($year, $month);

        return $this->ok([
            'year' => $year,
            'month' => $month,
            'score_rules' => $service->scoreRules(),
            'totals' => $data['totals'],
            'rows' => array_map(static function (array $row): array {
                unset($row['email']);
                return $row;
            }, $data['rows']),
        ]);
    }

    /** One staff member's full point history, for the administrator drill-down. */
    public function member(int $id)
    {
        $authFail = $this->requireTeamAccess();
        if ($authFail) {
            return $authFail;
        }

        $service = new StaffPerformanceService();
        $member = null;
        foreach ($service->staffOptions() as $staff) {
            if ((int) ($staff['id'] ?? 0) === $id) {
                $member = $staff;
                break;
            }
        }
        if ($member === null) {
            return $this->fail('Staff member not found.', 404);
        }

        [$year, $month] = $this->period();
        $data = $service->ownPerformance($id, $year, $month);
        $data['member'] = [
            'id' => $id,
            'name' => (string) ($member['name'] ?? ''),
            'role_label' => (string) ($member['role_label'] ?? 'Staff'),
        ];

        return $this->ok($data);
    }

    private function requireTeamAccess()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        if (! (new StaffPerformanceService())->isAdminUser((int) ($this->mobileAdmin['id'] ?? 0))) {
            return $this->fail('Team performance is available to administrators only.', 403);
        }

        return null;
    }

    /** @return array{0:int,1:int} */
    private function period(): array
    {
        return [
            min(2100, max(2025, (int) ($this->request->getGet('year') ?: date('Y')))),
            min(12, max(1, (int) ($this->request->getGet('month') ?: date('n')))),
        ];
    }
}
