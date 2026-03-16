<?php

namespace App\Services;

use App\Models\StaffKpiAchievementModel;
use App\Models\StaffKpiTargetModel;

class StaffPerformanceService
{
    private StaffKpiTargetModel $targetModel;
    private StaffKpiAchievementModel $achievementModel;

    public function __construct()
    {
        $this->targetModel = new StaffKpiTargetModel();
        $this->achievementModel = new StaffKpiAchievementModel();
    }

    public function dashboardData(int $year, int $month, ?int $employeeId = null): array
    {
        $builder = db_connect()->table('staff_kpi_targets t')
            ->select('t.*, e.full_name, e.designation_id, d.name as designation_name, k.name as kpi_name, k.metric_key, k.unit')
            ->join('employees e', 'e.id = t.employee_id', 'left')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->join('staff_kpis k', 'k.id = t.kpi_id', 'left')
            ->where('t.target_year', $year)
            ->where('t.target_month', $month)
            ->where('t.is_active', 1);

        if ($employeeId !== null && $employeeId > 0) {
            $builder->where('t.employee_id', $employeeId);
        }

        $rows = $builder->orderBy('e.full_name', 'ASC')->orderBy('k.name', 'ASC')->get()->getResultArray();

        $items = [];
        $totals = [
            'target_count' => 0,
            'employee_count' => 0,
            'target_value' => 0.0,
            'achieved_value' => 0.0,
            'incentive_amount' => 0.0,
        ];
        $employeeTracker = [];

        foreach ($rows as $row) {
            $achieved = $this->metricValue((string) ($row['metric_key'] ?? ''), (int) ($row['employee_id'] ?? 0), $year, $month);
            $targetValue = (float) ($row['target_value'] ?? 0);
            $achievementPercent = $targetValue > 0 ? round(($achieved / $targetValue) * 100, 2) : 0.0;
            $incentiveAmount = $this->calculateIncentive((int) ($row['designation_id'] ?? 0), (int) ($row['kpi_id'] ?? 0), $achievementPercent, $achieved);

            $this->upsertAchievement([
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'kpi_id' => (int) ($row['kpi_id'] ?? 0),
                'target_id' => (int) ($row['id'] ?? 0),
                'target_year' => $year,
                'target_month' => $month,
                'achieved_value' => $achieved,
                'achievement_percent' => $achievementPercent,
                'incentive_amount' => $incentiveAmount,
                'source_key' => (string) ($row['metric_key'] ?? ''),
                'calculated_at' => date('Y-m-d H:i:s'),
            ]);

            $items[] = $row + [
                'achieved_value' => $achieved,
                'achievement_percent' => $achievementPercent,
                'incentive_amount' => $incentiveAmount,
            ];

            $totals['target_count']++;
            $totals['target_value'] += $targetValue;
            $totals['achieved_value'] += $achieved;
            $totals['incentive_amount'] += $incentiveAmount;
            $employeeTracker[(int) ($row['employee_id'] ?? 0)] = true;
        }

        $totals['employee_count'] = count($employeeTracker);

        return [
            'rows' => $items,
            'totals' => [
                'target_count' => $totals['target_count'],
                'employee_count' => $totals['employee_count'],
                'target_value' => round($totals['target_value'], 2),
                'achieved_value' => round($totals['achieved_value'], 2),
                'incentive_amount' => round($totals['incentive_amount'], 2),
            ],
        ];
    }

    private function metricValue(string $metricKey, int $employeeId, int $year, int $month): float
    {
        if ($employeeId <= 0) {
            return 0.0;
        }

        $db = db_connect();
        if ($metricKey === 'showroom_sales_amount' && $db->tableExists('showroom_sales')) {
            $row = $db->table('showroom_sales')
                ->select('COALESCE(SUM(total_amount),0) as metric_value', false)
                ->where('salesperson_employee_id', $employeeId)
                ->where("YEAR(sale_date) = {$year}", null, false)
                ->where("MONTH(sale_date) = {$month}", null, false)
                ->where('sale_status !=', 'Cancelled')
                ->get()
                ->getRowArray();
            return round((float) ($row['metric_value'] ?? 0), 2);
        }

        if ($metricKey === 'showroom_sales_count' && $db->tableExists('showroom_sales')) {
            return (float) $db->table('showroom_sales')
                ->where('salesperson_employee_id', $employeeId)
                ->where("YEAR(sale_date) = {$year}", null, false)
                ->where("MONTH(sale_date) = {$month}", null, false)
                ->where('sale_status !=', 'Cancelled')
                ->countAllResults();
        }

        if ($metricKey === 'showroom_items_sold' && $db->tableExists('showroom_sale_items') && $db->tableExists('showroom_sales')) {
            $row = $db->table('showroom_sale_items si')
                ->select('COALESCE(SUM(si.qty),0) as metric_value', false)
                ->join('showroom_sales s', 's.id = si.showroom_sale_id', 'inner')
                ->where('s.salesperson_employee_id', $employeeId)
                ->where("YEAR(s.sale_date) = {$year}", null, false)
                ->where("MONTH(s.sale_date) = {$month}", null, false)
                ->where('s.sale_status !=', 'Cancelled')
                ->get()
                ->getRowArray();
            return round((float) ($row['metric_value'] ?? 0), 2);
        }

        return 0.0;
    }

    private function calculateIncentive(int $designationId, int $kpiId, float $achievementPercent, float $achievedValue): float
    {
        $rule = db_connect()->table('staff_incentive_rules')
            ->where('is_active', 1)
            ->groupStart()->where('designation_id', $designationId)->orWhere('designation_id IS NULL', null, false)->groupEnd()
            ->groupStart()->where('kpi_id', $kpiId)->orWhere('kpi_id IS NULL', null, false)->groupEnd()
            ->where('min_percent <=', $achievementPercent)
            ->groupStart()->where('max_percent IS NULL', null, false)->orWhere('max_percent >=', $achievementPercent)->groupEnd()
            ->orderBy('designation_id', 'DESC')
            ->orderBy('kpi_id', 'DESC')
            ->orderBy('min_percent', 'DESC')
            ->get(1)
            ->getRowArray();

        if (! $rule) {
            return 0.0;
        }

        $type = strtolower(trim((string) ($rule['incentive_type'] ?? 'flat')));
        $value = (float) ($rule['incentive_value'] ?? 0);
        if ($type === 'per_unit') {
            return round($achievedValue * $value, 2);
        }
        if ($type === 'percent_of_value') {
            return round($achievedValue * $value / 100, 2);
        }
        return round($value, 2);
    }

    private function upsertAchievement(array $data): void
    {
        $existing = db_connect()->table('staff_kpi_achievements')
            ->where('employee_id', (int) ($data['employee_id'] ?? 0))
            ->where('kpi_id', (int) ($data['kpi_id'] ?? 0))
            ->where('target_year', (int) ($data['target_year'] ?? 0))
            ->where('target_month', $data['target_month'] ?? null)
            ->get()
            ->getRowArray();

        if ($existing) {
            $this->achievementModel->update((int) $existing['id'], $data);
            return;
        }

        $this->achievementModel->insert($data);
    }
}
