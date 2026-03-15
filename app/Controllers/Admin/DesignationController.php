<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\DesignationModel;

class DesignationController extends BaseController
{
    private DesignationModel $designationModel;
    private DepartmentModel $departmentModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->designationModel = new DesignationModel();
        $this->departmentModel = new DepartmentModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('designations d')
            ->select('d.*, dep.name as department_name, parent.name as parent_designation_name')
            ->join('departments dep', 'dep.id = d.department_id', 'left')
            ->join('designations parent', 'parent.id = d.reports_to_designation_id', 'left')
            ->orderBy('d.level_no', 'ASC')
            ->orderBy('d.name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/designations/index', [
            'title' => 'Designation Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/designations/form', [
            'title' => 'Add Designation',
            'row' => null,
            'departments' => $this->departmentOptions(),
            'designations' => $this->designationOptions(),
            'formAction' => site_url('admin/designations'),
        ]);
    }

    public function store()
    {
        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->designationModel->where('designation_code', $data['designation_code'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Designation code already exists.');
        }

        $this->designationModel->insert($data);

        return redirect()->to(site_url('admin/designations'))->with('success', 'Designation created.');
    }

    public function edit(int $id): string
    {
        $row = $this->designationModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Designation not found.');
        }

        return view('admin/designations/form', [
            'title' => 'Edit Designation',
            'row' => $row,
            'departments' => $this->departmentOptions(),
            'designations' => $this->designationOptions($id),
            'formAction' => site_url('admin/designations/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->designationModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/designations'))->with('error', 'Designation not found.');
        }

        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->designationModel->where('designation_code', $data['designation_code'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Designation code already exists.');
        }

        $this->designationModel->update($id, $data);

        return redirect()->to(site_url('admin/designations'))->with('success', 'Designation updated.');
    }

    public function toggleStatus(int $id)
    {
        $row = $this->designationModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/designations'))->with('error', 'Designation not found.');
        }

        $this->designationModel->update($id, [
            'is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1,
        ]);

        return redirect()->to(site_url('admin/designations'))->with('success', 'Designation status updated.');
    }

    private function validatedPayload()
    {
        $rules = [
            'designation_code' => 'required|max_length[30]',
            'name' => 'required|max_length[120]',
            'department_id' => 'required|integer|greater_than[0]',
            'level_no' => 'required|integer|greater_than[0]',
            'reports_to_designation_id' => 'permit_empty|integer',
            'description' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $reportsTo = (int) ($this->request->getPost('reports_to_designation_id') ?: 0);
        $departmentId = (int) $this->request->getPost('department_id');
        if (! $this->departmentModel->find($departmentId)) {
            return redirect()->back()->withInput()->with('error', 'Selected department not found.');
        }

        return [
            'designation_code' => strtoupper(trim((string) $this->request->getPost('designation_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'department_id' => $departmentId,
            'level_no' => (int) $this->request->getPost('level_no'),
            'reports_to_designation_id' => $reportsTo > 0 ? $reportsTo : null,
            'can_manage_team' => $this->request->getPost('can_manage_team') ? 1 : 0,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function departmentOptions(): array
    {
        return $this->departmentModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function designationOptions(?int $excludeId = null): array
    {
        $builder = $this->designationModel->where('is_active', 1);
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->orderBy('level_no', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
