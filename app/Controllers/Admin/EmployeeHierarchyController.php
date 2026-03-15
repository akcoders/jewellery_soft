<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeHierarchyModel;
use App\Models\EmployeeModel;

class EmployeeHierarchyController extends BaseController
{
    private EmployeeModel $employeeModel;
    private EmployeeHierarchyModel $hierarchyModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->employeeModel = new EmployeeModel();
        $this->hierarchyModel = new EmployeeHierarchyModel();
    }

    public function index(): string
    {
        $employees = $this->employeeList();
        $selectedId = (int) ($this->request->getGet('employee_id') ?: 0);
        if ($selectedId <= 0 && $employees !== []) {
            $selectedId = (int) ($employees[0]['id'] ?? 0);
        }

        $selectedEmployee = $selectedId > 0 ? $this->employeeDetail($selectedId) : null;
        $currentHierarchy = $selectedId > 0 ? $this->currentHierarchy($selectedId) : null;
        $history = $selectedId > 0 ? $this->hierarchyHistory($selectedId) : [];
        $team = $selectedId > 0 ? $this->directTeam($selectedId) : [];

        return view('admin/employee_hierarchy/index', [
            'title' => 'Employee Hierarchy',
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'currentHierarchy' => $currentHierarchy,
            'history' => $history,
            'team' => $team,
            'managerOptions' => $this->managerOptions($selectedId),
            'selectedId' => $selectedId,
            'formAction' => site_url('admin/employee-hierarchy'),
        ]);
    }

    public function store()
    {
        $employeeId = (int) ($this->request->getPost('employee_id') ?: 0);
        if ($employeeId <= 0) {
            return redirect()->back()->with('error', 'Employee is required.');
        }

        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return redirect()->back()->with('error', 'Employee not found.');
        }

        $rules = [
            'employee_id' => 'required|integer|greater_than[0]',
            'reporting_manager_id' => 'permit_empty|integer',
            'observing_manager_id' => 'permit_empty|integer',
            'reviewing_manager_id' => 'permit_empty|integer',
            'approving_manager_id' => 'permit_empty|integer',
            'department_head_id' => 'permit_empty|integer',
            'effective_from' => 'permit_empty|valid_date',
            'remarks' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
                ->withInput()
                ->with('error', $this->firstValidationError());
        }

        $reportingId = $this->nullableInt($this->request->getPost('reporting_manager_id'));
        $observingId = $this->nullableInt($this->request->getPost('observing_manager_id'));
        $reviewingId = $this->nullableInt($this->request->getPost('reviewing_manager_id'));
        $approvingId = $this->nullableInt($this->request->getPost('approving_manager_id'));
        $departmentHeadId = $this->nullableInt($this->request->getPost('department_head_id'));

        foreach ([$reportingId, $observingId, $reviewingId, $approvingId, $departmentHeadId] as $managerId) {
            if ($managerId !== null && $managerId === $employeeId) {
                return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
                    ->withInput()
                    ->with('error', 'An employee cannot be assigned against themselves in hierarchy.');
            }
            if ($managerId !== null && ! $this->employeeModel->find($managerId)) {
                return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
                    ->withInput()
                    ->with('error', 'One of the selected managers does not exist.');
            }
        }

        if ($reportingId !== null && $this->createsReportingLoop($employeeId, $reportingId)) {
            return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
                ->withInput()
                ->with('error', 'Reporting manager creates a circular reporting loop.');
        }

        $effectiveFrom = trim((string) $this->request->getPost('effective_from'));
        if ($effectiveFrom === '') {
            $effectiveFrom = date('Y-m-d');
        }

        $db = db_connect();
        $db->transStart();

        $this->hierarchyModel
            ->where('employee_id', $employeeId)
            ->where('is_active', 1)
            ->set([
                'is_active' => 0,
                'effective_to' => $effectiveFrom,
            ])
            ->update();

        $this->hierarchyModel->insert([
            'employee_id' => $employeeId,
            'reporting_manager_id' => $reportingId,
            'observing_manager_id' => $observingId,
            'reviewing_manager_id' => $reviewingId,
            'approving_manager_id' => $approvingId,
            'department_head_id' => $departmentHeadId,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'is_active' => 1,
            'remarks' => trim((string) $this->request->getPost('remarks')) ?: null,
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
                ->withInput()
                ->with('error', 'Unable to save hierarchy right now.');
        }

        return redirect()->to(site_url('admin/employee-hierarchy?employee_id=' . $employeeId))
            ->with('success', 'Hierarchy updated.');
    }

    private function employeeList(): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.employee_code, e.full_name, e.is_active, des.name as designation_name, dep.name as department_name, e.work_location')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function employeeDetail(int $employeeId): ?array
    {
        $row = db_connect()->table('employees e')
            ->select('e.*, des.name as designation_name, dep.name as department_name')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->where('e.id', $employeeId)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    private function currentHierarchy(int $employeeId): ?array
    {
        $row = db_connect()->table('employee_hierarchies eh')
            ->select('eh.*, rm.full_name as reporting_manager_name, om.full_name as observing_manager_name, revm.full_name as reviewing_manager_name, am.full_name as approving_manager_name, dh.full_name as department_head_name')
            ->join('employees rm', 'rm.id = eh.reporting_manager_id', 'left')
            ->join('employees om', 'om.id = eh.observing_manager_id', 'left')
            ->join('employees revm', 'revm.id = eh.reviewing_manager_id', 'left')
            ->join('employees am', 'am.id = eh.approving_manager_id', 'left')
            ->join('employees dh', 'dh.id = eh.department_head_id', 'left')
            ->where('eh.employee_id', $employeeId)
            ->where('eh.is_active', 1)
            ->orderBy('eh.id', 'DESC')
            ->get(1)
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    private function hierarchyHistory(int $employeeId): array
    {
        return db_connect()->table('employee_hierarchies eh')
            ->select('eh.*, rm.full_name as reporting_manager_name, om.full_name as observing_manager_name, am.full_name as approving_manager_name')
            ->join('employees rm', 'rm.id = eh.reporting_manager_id', 'left')
            ->join('employees om', 'om.id = eh.observing_manager_id', 'left')
            ->join('employees am', 'am.id = eh.approving_manager_id', 'left')
            ->where('eh.employee_id', $employeeId)
            ->orderBy('eh.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function directTeam(int $employeeId): array
    {
        return db_connect()->table('employee_hierarchies eh')
            ->select('e.employee_code, e.full_name, des.name as designation_name, dep.name as department_name')
            ->join('employees e', 'e.id = eh.employee_id', 'inner')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->where('eh.reporting_manager_id', $employeeId)
            ->where('eh.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function managerOptions(int $employeeId): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.employee_code, e.full_name, des.name as designation_name')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->where('e.is_active', 1)
            ->where('e.id !=', $employeeId)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function createsReportingLoop(int $employeeId, int $managerId): bool
    {
        $visited = [$employeeId];
        $current = $managerId;

        while ($current > 0) {
            if (in_array($current, $visited, true)) {
                return true;
            }
            $visited[] = $current;
            $row = $this->hierarchyModel
                ->where('employee_id', $current)
                ->where('is_active', 1)
                ->orderBy('id', 'DESC')
                ->first();
            $current = (int) ($row['reporting_manager_id'] ?? 0);
        }

        return false;
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
