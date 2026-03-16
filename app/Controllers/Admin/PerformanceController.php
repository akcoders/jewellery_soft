<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\StaffIncentiveRuleModel;
use App\Models\StaffKpiModel;
use App\Models\StaffKpiTargetModel;
use App\Services\StaffPerformanceService;

class PerformanceController extends BaseController
{
    private StaffKpiModel $kpiModel;
    private StaffKpiTargetModel $targetModel;
    private StaffIncentiveRuleModel $incentiveRuleModel;
    private EmployeeModel $employeeModel;
    private StaffPerformanceService $performanceService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->kpiModel = new StaffKpiModel();
        $this->targetModel = new StaffKpiTargetModel();
        $this->incentiveRuleModel = new StaffIncentiveRuleModel();
        $this->employeeModel = new EmployeeModel();
        $this->performanceService = new StaffPerformanceService();
    }

    public function dashboard(): string
    {
        $year = max(2025, (int) ($this->request->getGet('year') ?: date('Y')));
        $month = min(12, max(1, (int) ($this->request->getGet('month') ?: date('n'))));
        $employeeId = (int) ($this->request->getGet('employee_id') ?: 0);
        $data = $this->performanceService->dashboardData($year, $month, $employeeId > 0 ? $employeeId : null);

        return view('admin/performance/dashboard', [
            'title' => 'KPI Dashboard',
            'year' => $year,
            'month' => $month,
            'employeeId' => $employeeId,
            'employees' => $this->activeEmployees(),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    public function kpis(): string
    {
        return view('admin/performance/kpis/index', [
            'title' => 'KPI Master',
            'rows' => $this->kpiModel->orderBy('module_group', 'ASC')->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function createKpi(): string
    {
        return view('admin/performance/kpis/form', [
            'title' => 'Create KPI',
            'formAction' => site_url('admin/performance/kpis'),
            'kpi' => null,
        ]);
    }

    public function storeKpi()
    {
        $rules = [
            'kpi_code' => 'required|max_length[40]|is_unique[staff_kpis.kpi_code]',
            'name' => 'required|max_length[150]',
            'metric_key' => 'required|max_length[80]',
            'period_type' => 'required|max_length[20]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->kpiModel->insert($this->kpiPayload());
        return redirect()->to(site_url('admin/performance/kpis'))->with('success', 'KPI created successfully.');
    }

    public function editKpi(int $id): string
    {
        $kpi = $this->kpiModel->find($id);
        if (! $kpi) {
            return redirect()->to(site_url('admin/performance/kpis'))->with('error', 'KPI not found.');
        }

        return view('admin/performance/kpis/form', [
            'title' => 'Edit KPI',
            'formAction' => site_url('admin/performance/kpis/' . $id . '/update'),
            'kpi' => $kpi,
        ]);
    }

    public function updateKpi(int $id)
    {
        $kpi = $this->kpiModel->find($id);
        if (! $kpi) {
            return redirect()->to(site_url('admin/performance/kpis'))->with('error', 'KPI not found.');
        }

        $rules = [
            'kpi_code' => 'required|max_length[40]|is_unique[staff_kpis.kpi_code,id,' . $id . ']',
            'name' => 'required|max_length[150]',
            'metric_key' => 'required|max_length[80]',
            'period_type' => 'required|max_length[20]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->kpiModel->update($id, $this->kpiPayload());
        return redirect()->to(site_url('admin/performance/kpis'))->with('success', 'KPI updated successfully.');
    }

    public function targets(): string
    {
        $rows = db_connect()->table('staff_kpi_targets t')
            ->select('t.*, e.full_name, k.name as kpi_name, d.name as designation_name')
            ->join('employees e', 'e.id = t.employee_id', 'left')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->join('staff_kpis k', 'k.id = t.kpi_id', 'left')
            ->orderBy('t.target_year', 'DESC')
            ->orderBy('t.target_month', 'DESC')
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/performance/targets/index', [
            'title' => 'KPI Targets',
            'rows' => $rows,
        ]);
    }

    public function createTarget(): string
    {
        return view('admin/performance/targets/form', [
            'title' => 'Assign KPI Target',
            'formAction' => site_url('admin/performance/targets'),
            'target' => null,
            'employees' => $this->activeEmployees(),
            'kpis' => $this->activeKpis(),
        ]);
    }

    public function storeTarget()
    {
        $rules = [
            'employee_id' => 'required|integer|greater_than[0]',
            'kpi_id' => 'required|integer|greater_than[0]',
            'target_year' => 'required|integer',
            'target_month' => 'required|integer|greater_than[0]|less_than_equal_to[12]',
            'target_value' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->targetModel->insert($this->targetPayload());
        return redirect()->to(site_url('admin/performance/targets'))->with('success', 'KPI target saved successfully.');
    }

    public function editTarget(int $id): string
    {
        $target = $this->targetModel->find($id);
        if (! $target) {
            return redirect()->to(site_url('admin/performance/targets'))->with('error', 'KPI target not found.');
        }

        return view('admin/performance/targets/form', [
            'title' => 'Edit KPI Target',
            'formAction' => site_url('admin/performance/targets/' . $id . '/update'),
            'target' => $target,
            'employees' => $this->activeEmployees(),
            'kpis' => $this->activeKpis(),
        ]);
    }

    public function updateTarget(int $id)
    {
        $target = $this->targetModel->find($id);
        if (! $target) {
            return redirect()->to(site_url('admin/performance/targets'))->with('error', 'KPI target not found.');
        }

        $rules = [
            'employee_id' => 'required|integer|greater_than[0]',
            'kpi_id' => 'required|integer|greater_than[0]',
            'target_year' => 'required|integer',
            'target_month' => 'required|integer|greater_than[0]|less_than_equal_to[12]',
            'target_value' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->targetModel->update($id, $this->targetPayload());
        return redirect()->to(site_url('admin/performance/targets'))->with('success', 'KPI target updated successfully.');
    }

    public function incentives(): string
    {
        $rows = db_connect()->table('staff_incentive_rules r')
            ->select('r.*, d.name as designation_name, k.name as kpi_name')
            ->join('designations d', 'd.id = r.designation_id', 'left')
            ->join('staff_kpis k', 'k.id = r.kpi_id', 'left')
            ->orderBy('r.rule_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/performance/incentives/index', [
            'title' => 'Incentive Rules',
            'rows' => $rows,
        ]);
    }

    public function createIncentive(): string
    {
        return view('admin/performance/incentives/form', [
            'title' => 'Create Incentive Rule',
            'formAction' => site_url('admin/performance/incentives'),
            'rule' => null,
            'designations' => $this->activeDesignations(),
            'kpis' => $this->activeKpis(),
        ]);
    }

    public function storeIncentive()
    {
        $rules = [
            'rule_code' => 'required|max_length[40]|is_unique[staff_incentive_rules.rule_code]',
            'rule_name' => 'required|max_length[150]',
            'min_percent' => 'required|decimal|greater_than_equal_to[0]',
            'incentive_type' => 'required|in_list[flat,per_unit,percent_of_value]',
            'incentive_value' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->incentiveRuleModel->insert($this->incentivePayload());
        return redirect()->to(site_url('admin/performance/incentives'))->with('success', 'Incentive rule created successfully.');
    }

    public function editIncentive(int $id): string
    {
        $rule = $this->incentiveRuleModel->find($id);
        if (! $rule) {
            return redirect()->to(site_url('admin/performance/incentives'))->with('error', 'Incentive rule not found.');
        }

        return view('admin/performance/incentives/form', [
            'title' => 'Edit Incentive Rule',
            'formAction' => site_url('admin/performance/incentives/' . $id . '/update'),
            'rule' => $rule,
            'designations' => $this->activeDesignations(),
            'kpis' => $this->activeKpis(),
        ]);
    }

    public function updateIncentive(int $id)
    {
        $rule = $this->incentiveRuleModel->find($id);
        if (! $rule) {
            return redirect()->to(site_url('admin/performance/incentives'))->with('error', 'Incentive rule not found.');
        }

        $rules = [
            'rule_code' => 'required|max_length[40]|is_unique[staff_incentive_rules.rule_code,id,' . $id . ']',
            'rule_name' => 'required|max_length[150]',
            'min_percent' => 'required|decimal|greater_than_equal_to[0]',
            'incentive_type' => 'required|in_list[flat,per_unit,percent_of_value]',
            'incentive_value' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->incentiveRuleModel->update($id, $this->incentivePayload());
        return redirect()->to(site_url('admin/performance/incentives'))->with('success', 'Incentive rule updated successfully.');
    }

    private function activeEmployees(): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.full_name, e.employee_code, d.name as designation_name')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->where('e.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function activeKpis(): array
    {
        return $this->kpiModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function activeDesignations(): array
    {
        return db_connect()->table('designations')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
    }

    private function kpiPayload(): array
    {
        return [
            'kpi_code' => strtoupper(trim((string) $this->request->getPost('kpi_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'module_group' => trim((string) $this->request->getPost('module_group')) ?: null,
            'metric_key' => trim((string) $this->request->getPost('metric_key')),
            'unit' => trim((string) $this->request->getPost('unit')) ?: null,
            'period_type' => trim((string) $this->request->getPost('period_type')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function targetPayload(): array
    {
        return [
            'employee_id' => (int) $this->request->getPost('employee_id'),
            'kpi_id' => (int) $this->request->getPost('kpi_id'),
            'target_year' => (int) $this->request->getPost('target_year'),
            'target_month' => (int) $this->request->getPost('target_month'),
            'period_label' => trim((string) $this->request->getPost('period_label')) ?: null,
            'target_value' => round((float) $this->request->getPost('target_value'), 2),
            'weightage' => round((float) ($this->request->getPost('weightage') ?: 100), 2),
            'assigned_by' => (int) (session('admin_id') ?? 0),
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function incentivePayload(): array
    {
        return [
            'rule_code' => strtoupper(trim((string) $this->request->getPost('rule_code'))),
            'rule_name' => trim((string) $this->request->getPost('rule_name')),
            'designation_id' => $this->nullableInt($this->request->getPost('designation_id')),
            'kpi_id' => $this->nullableInt($this->request->getPost('kpi_id')),
            'min_percent' => round((float) $this->request->getPost('min_percent'), 2),
            'max_percent' => $this->request->getPost('max_percent') === '' ? null : round((float) $this->request->getPost('max_percent'), 2),
            'incentive_type' => trim((string) $this->request->getPost('incentive_type')),
            'incentive_value' => round((float) $this->request->getPost('incentive_value'), 2),
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
