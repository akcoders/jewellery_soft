<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ShowroomStaffAssignmentModel;

class ShowroomStaffController extends BaseController
{
    private ShowroomStaffAssignmentModel $assignmentModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->assignmentModel = new ShowroomStaffAssignmentModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('showroom_staff_assignments a')
            ->select('a.*, s.name as showroom_name, e.employee_code, e.full_name, des.name as designation_name')
            ->join('showrooms s', 's.id = a.showroom_id', 'left')
            ->join('employees e', 'e.id = a.employee_id', 'left')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->orderBy('s.name', 'ASC')
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/showroom_staff/index', [
            'title' => 'Showroom Staff Assignment',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/showroom_staff/form', [
            'title' => 'Assign Showroom Staff',
            'row' => null,
            'showrooms' => $this->showroomOptions(),
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showroom-staff'),
        ]);
    }

    public function store()
    {
        return $this->save();
    }

    public function edit(int $id): string
    {
        $row = $this->assignmentModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Showroom staff assignment not found.');
        }

        return view('admin/showroom_staff/form', [
            'title' => 'Edit Showroom Staff Assignment',
            'row' => $row,
            'showrooms' => $this->showroomOptions(),
            'employees' => $this->employeeOptions(),
            'formAction' => site_url('admin/showroom-staff/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        return $this->save($id);
    }

    public function toggleStatus(int $id)
    {
        $row = $this->assignmentModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/showroom-staff'))->with('error', 'Assignment not found.');
        }

        $this->assignmentModel->update($id, ['is_active' => (int) ! ((int) ($row['is_active'] ?? 0) === 1)]);
        return redirect()->to(site_url('admin/showroom-staff'))->with('success', 'Assignment status updated.');
    }

    private function save(?int $id = null)
    {
        $rules = [
            'showroom_id' => 'required|integer|greater_than[0]',
            'employee_id' => 'required|integer|greater_than[0]',
            'role_label' => 'required|max_length[80]',
            'effective_from' => 'permit_empty|valid_date',
            'effective_to' => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $data = [
            'showroom_id' => (int) $this->request->getPost('showroom_id'),
            'employee_id' => (int) $this->request->getPost('employee_id'),
            'role_label' => trim((string) $this->request->getPost('role_label')),
            'is_primary' => $this->request->getPost('is_primary') ? 1 : 0,
            'effective_from' => trim((string) $this->request->getPost('effective_from')) ?: null,
            'effective_to' => trim((string) $this->request->getPost('effective_to')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
        ];

        if ($data['effective_from'] !== null && $data['effective_to'] !== null && $data['effective_to'] < $data['effective_from']) {
            return redirect()->back()->withInput()->with('error', 'Effective to date cannot be before effective from date.');
        }

        if ($id === null) {
            $this->assignmentModel->insert($data);
            $message = 'Showroom staff assigned.';
        } else {
            $this->assignmentModel->update($id, $data);
            $message = 'Showroom staff assignment updated.';
        }

        return redirect()->to(site_url('admin/showroom-staff'))->with('success', $message);
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

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
