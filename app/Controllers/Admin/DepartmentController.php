<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;

class DepartmentController extends BaseController
{
    private DepartmentModel $departmentModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->departmentModel = new DepartmentModel();
    }

    public function index(): string
    {
        return view('admin/departments/index', [
            'title' => 'Department Master',
            'rows' => $this->departmentModel->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function create(): string
    {
        return view('admin/departments/form', [
            'title' => 'Add Department',
            'row' => null,
            'formAction' => site_url('admin/departments'),
        ]);
    }

    public function store()
    {
        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->departmentModel->where('department_code', $data['department_code'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Department code already exists.');
        }
        if ($this->departmentModel->where('name', $data['name'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Department name already exists.');
        }

        $this->departmentModel->insert($data);

        return redirect()->to(site_url('admin/departments'))->with('success', 'Department created.');
    }

    public function edit(int $id): string
    {
        $row = $this->departmentModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Department not found.');
        }

        return view('admin/departments/form', [
            'title' => 'Edit Department',
            'row' => $row,
            'formAction' => site_url('admin/departments/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->departmentModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/departments'))->with('error', 'Department not found.');
        }

        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->departmentModel->where('department_code', $data['department_code'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Department code already exists.');
        }
        if ($this->departmentModel->where('name', $data['name'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Department name already exists.');
        }

        $this->departmentModel->update($id, $data);

        return redirect()->to(site_url('admin/departments'))->with('success', 'Department updated.');
    }

    public function toggleStatus(int $id)
    {
        $row = $this->departmentModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/departments'))->with('error', 'Department not found.');
        }

        $this->departmentModel->update($id, [
            'is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1,
        ]);

        return redirect()->to(site_url('admin/departments'))->with('success', 'Department status updated.');
    }

    private function validatedPayload()
    {
        $rules = [
            'department_code' => 'required|max_length[30]',
            'name' => 'required|max_length[120]',
            'sort_order' => 'permit_empty|integer',
            'notes' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        return [
            'department_code' => strtoupper(trim((string) $this->request->getPost('department_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
