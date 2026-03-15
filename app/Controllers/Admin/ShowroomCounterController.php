<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ShowroomCounterModel;

class ShowroomCounterController extends BaseController
{
    private ShowroomCounterModel $counterModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->counterModel = new ShowroomCounterModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('showroom_counters c')
            ->select('c.*, s.name as showroom_name, e.full_name as incharge_name')
            ->join('showrooms s', 's.id = c.showroom_id', 'left')
            ->join('employees e', 'e.id = c.incharge_employee_id', 'left')
            ->orderBy('s.name', 'ASC')
            ->orderBy('c.counter_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/showroom_counters/index', [
            'title' => 'Showroom Counter Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/showroom_counters/form', [
            'title' => 'Create Counter',
            'row' => null,
            'showrooms' => $this->showroomOptions(),
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showroom-counters'),
        ]);
    }

    public function store()
    {
        return $this->save();
    }

    public function edit(int $id): string
    {
        $row = $this->counterModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Counter not found.');
        }

        return view('admin/showroom_counters/form', [
            'title' => 'Edit Counter',
            'row' => $row,
            'showrooms' => $this->showroomOptions(),
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showroom-counters/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        return $this->save($id);
    }

    public function toggleStatus(int $id)
    {
        $row = $this->counterModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/showroom-counters'))->with('error', 'Counter not found.');
        }

        $this->counterModel->update($id, ['is_active' => (int) ! ((int) ($row['is_active'] ?? 0) === 1)]);
        return redirect()->to(site_url('admin/showroom-counters'))->with('success', 'Counter status updated.');
    }

    private function save(?int $id = null)
    {
        $rules = [
            'showroom_id' => 'required|integer|greater_than[0]',
            'counter_code' => 'required|max_length[30]',
            'counter_name' => 'required|max_length[120]',
            'counter_type' => 'required|max_length[50]',
            'incharge_employee_id' => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $data = [
            'showroom_id' => (int) $this->request->getPost('showroom_id'),
            'counter_code' => strtoupper(trim((string) $this->request->getPost('counter_code'))),
            'counter_name' => trim((string) $this->request->getPost('counter_name')),
            'counter_type' => trim((string) $this->request->getPost('counter_type')),
            'incharge_employee_id' => $this->nullableInt($this->request->getPost('incharge_employee_id')),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
        ];

        $duplicate = $this->counterModel
            ->where('showroom_id', $data['showroom_id'])
            ->where('counter_code', $data['counter_code']);
        if ($id !== null) {
            $duplicate->where('id !=', $id);
        }
        if ($duplicate->first()) {
            return redirect()->back()->withInput()->with('error', 'Counter code already exists for this showroom.');
        }

        if ($id === null) {
            $this->counterModel->insert($data);
            $message = 'Counter created.';
        } else {
            $this->counterModel->update($id, $data);
            $message = 'Counter updated.';
        }

        return redirect()->to(site_url('admin/showroom-counters'))->with('success', $message);
    }

    private function showroomOptions(): array
    {
        return db_connect()->table('showrooms')
            ->select('id, showroom_code, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function employeeOptions(): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.full_name, e.employee_code, des.name as designation_name')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->where('e.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
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
