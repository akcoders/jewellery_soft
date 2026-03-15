<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ShowroomModel;

class ShowroomController extends BaseController
{
    private ShowroomModel $showroomModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->showroomModel = new ShowroomModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('showrooms s')
            ->select('s.*, e.full_name as manager_name')
            ->join('employees e', 'e.id = s.manager_employee_id', 'left')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/showrooms/index', [
            'title' => 'Showroom Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/showrooms/form', [
            'title' => 'Create Showroom',
            'row' => null,
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showrooms'),
        ]);
    }

    public function store()
    {
        return $this->save();
    }

    public function edit(int $id): string
    {
        $row = $this->showroomModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Showroom not found.');
        }

        return view('admin/showrooms/form', [
            'title' => 'Edit Showroom',
            'row' => $row,
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showrooms/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        return $this->save($id);
    }

    public function toggleStatus(int $id)
    {
        $row = $this->showroomModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/showrooms'))->with('error', 'Showroom not found.');
        }

        $this->showroomModel->update($id, ['is_active' => (int) ! ((int) ($row['is_active'] ?? 0) === 1)]);
        return redirect()->to(site_url('admin/showrooms'))->with('success', 'Showroom status updated.');
    }

    private function save(?int $id = null)
    {
        $rules = [
            'showroom_code' => 'required|max_length[30]',
            'name' => 'required|max_length[150]',
            'showroom_type' => 'required|max_length[50]',
            'manager_employee_id' => 'permit_empty|integer',
            'phone' => 'permit_empty|max_length[30]',
            'email' => 'permit_empty|valid_email|max_length[120]',
            'gstin' => 'permit_empty|max_length[30]',
            'state_name' => 'permit_empty|max_length[80]',
            'city_name' => 'permit_empty|max_length[80]',
            'opening_date' => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $data = [
            'showroom_code' => strtoupper(trim((string) $this->request->getPost('showroom_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'showroom_type' => trim((string) $this->request->getPost('showroom_type')),
            'manager_employee_id' => $this->nullableInt($this->request->getPost('manager_employee_id')),
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'gstin' => trim((string) $this->request->getPost('gstin')) ?: null,
            'state_name' => trim((string) $this->request->getPost('state_name')) ?: null,
            'city_name' => trim((string) $this->request->getPost('city_name')) ?: null,
            'address_line' => trim((string) $this->request->getPost('address_line')) ?: null,
            'opening_date' => trim((string) $this->request->getPost('opening_date')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
        ];

        $duplicate = $this->showroomModel->where('showroom_code', $data['showroom_code']);
        if ($id !== null) {
            $duplicate->where('id !=', $id);
        }
        if ($duplicate->first()) {
            return redirect()->back()->withInput()->with('error', 'Showroom code already exists.');
        }

        if ($id === null) {
            $this->showroomModel->insert($data);
            $message = 'Showroom created.';
        } else {
            $this->showroomModel->update($id, $data);
            $message = 'Showroom updated.';
        }

        return redirect()->to(site_url('admin/showrooms'))->with('success', $message);
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
