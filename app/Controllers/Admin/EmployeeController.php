<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Models\DepartmentModel;
use App\Models\DesignationModel;
use App\Models\EmployeeModel;

class EmployeeController extends BaseController
{
    private EmployeeModel $employeeModel;
    private DepartmentModel $departmentModel;
    private DesignationModel $designationModel;
    private AdminUserModel $adminUserModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->employeeModel = new EmployeeModel();
        $this->departmentModel = new DepartmentModel();
        $this->designationModel = new DesignationModel();
        $this->adminUserModel = new AdminUserModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('employees e')
            ->select('e.*, dep.name as department_name, des.name as designation_name, au.name as admin_user_name')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->join('admin_users au', 'au.id = e.admin_user_id', 'left')
            ->orderBy('e.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('admin/employees/index', [
            'title' => 'Employee Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/employees/form', [
            'title' => 'Add Employee',
            'row' => null,
            'departments' => $this->departmentOptions(),
            'designations' => $this->designationOptions(),
            'adminUsers' => $this->adminUserOptions(),
            'formAction' => site_url('admin/employees'),
        ]);
    }

    public function store()
    {
        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->employeeModel->where('employee_code', $data['employee_code'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Employee code already exists.');
        }
        if ($data['admin_user_id'] !== null && $this->employeeModel->where('admin_user_id', $data['admin_user_id'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Selected admin user is already linked to another employee.');
        }

        $this->employeeModel->insert($data);

        return redirect()->to(site_url('admin/employees'))->with('success', 'Employee created.');
    }

    public function edit(int $id): string
    {
        $row = $this->employeeModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Employee not found.');
        }

        return view('admin/employees/form', [
            'title' => 'Edit Employee',
            'row' => $row,
            'departments' => $this->departmentOptions(),
            'designations' => $this->designationOptions(),
            'adminUsers' => $this->adminUserOptions($id),
            'formAction' => site_url('admin/employees/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->employeeModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/employees'))->with('error', 'Employee not found.');
        }

        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->employeeModel->where('employee_code', $data['employee_code'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Employee code already exists.');
        }
        if ($data['admin_user_id'] !== null && $this->employeeModel->where('admin_user_id', $data['admin_user_id'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Selected admin user is already linked to another employee.');
        }

        $this->employeeModel->update($id, $data);

        return redirect()->to(site_url('admin/employees'))->with('success', 'Employee updated.');
    }

    public function toggleStatus(int $id)
    {
        $row = $this->employeeModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/employees'))->with('error', 'Employee not found.');
        }

        $this->employeeModel->update($id, [
            'is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1,
        ]);

        return redirect()->to(site_url('admin/employees'))->with('success', 'Employee status updated.');
    }

    private function validatedPayload()
    {
        $rules = [
            'employee_code' => 'required|max_length[30]',
            'full_name' => 'required|max_length[150]',
            'department_id' => 'required|integer|greater_than[0]',
            'designation_id' => 'required|integer|greater_than[0]',
            'mobile' => 'permit_empty|max_length[30]',
            'email' => 'permit_empty|valid_email|max_length[120]',
            'work_location' => 'permit_empty|max_length[120]',
            'joining_date' => 'permit_empty|valid_date',
            'pan_no' => 'permit_empty|max_length[20]',
            'aadhaar_no' => 'permit_empty|max_length[20]',
            'bank_name' => 'permit_empty|max_length[120]',
            'bank_account_no' => 'permit_empty|max_length[40]',
            'ifsc_code' => 'permit_empty|max_length[20]',
            'notes' => 'permit_empty',
            'admin_user_id' => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $departmentId = (int) $this->request->getPost('department_id');
        $designationId = (int) $this->request->getPost('designation_id');
        if (! $this->departmentModel->find($departmentId)) {
            return redirect()->back()->withInput()->with('error', 'Selected department not found.');
        }
        if (! $this->designationModel->find($designationId)) {
            return redirect()->back()->withInput()->with('error', 'Selected designation not found.');
        }

        $photoPath = null;
        $currentPhoto = trim((string) $this->request->getPost('current_photo'));
        if ($currentPhoto !== '') {
            $photoPath = $currentPhoto;
        }

        $photo = $this->request->getFile('photo');
        if ($photo && $photo->isValid() && $photo->getError() !== UPLOAD_ERR_NO_FILE) {
            $ext = strtolower((string) $photo->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return redirect()->back()->withInput()->with('error', 'Photo must be jpg, jpeg, png, or webp.');
            }
            $uploadDir = FCPATH . 'uploads/employees';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
            $photo->move($uploadDir, $fileName);
            $photoPath = 'uploads/employees/' . $fileName;
        }

        $adminUserId = (int) ($this->request->getPost('admin_user_id') ?: 0);

        return [
            'employee_code' => strtoupper(trim((string) $this->request->getPost('employee_code'))),
            'admin_user_id' => $adminUserId > 0 ? $adminUserId : null,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'mobile' => trim((string) $this->request->getPost('mobile')) ?: null,
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'work_location' => trim((string) $this->request->getPost('work_location')) ?: null,
            'joining_date' => trim((string) $this->request->getPost('joining_date')) ?: null,
            'pan_no' => trim((string) $this->request->getPost('pan_no')) ?: null,
            'aadhaar_no' => trim((string) $this->request->getPost('aadhaar_no')) ?: null,
            'bank_name' => trim((string) $this->request->getPost('bank_name')) ?: null,
            'bank_account_no' => trim((string) $this->request->getPost('bank_account_no')) ?: null,
            'ifsc_code' => trim((string) $this->request->getPost('ifsc_code')) ?: null,
            'photo_path' => $photoPath,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function departmentOptions(): array
    {
        return $this->departmentModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function designationOptions(): array
    {
        return $this->designationModel->where('is_active', 1)->orderBy('level_no', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    private function adminUserOptions(?int $employeeId = null): array
    {
        $linkedIds = $this->employeeModel->select('admin_user_id')->where('admin_user_id IS NOT NULL', null, false)->findColumn('admin_user_id') ?: [];
        $current = null;
        if ($employeeId !== null) {
            $currentEmployee = $this->employeeModel->find($employeeId);
            $current = (int) ($currentEmployee['admin_user_id'] ?? 0);
        }

        $builder = $this->adminUserModel->orderBy('name', 'ASC');
        $linkedIds = array_values(array_filter(array_map('intval', $linkedIds)));

        if ($linkedIds !== []) {
            $builder->groupStart()
                ->whereNotIn('id', $linkedIds);

            if ($current !== null && $current > 0) {
                $builder->orWhere('id', $current);
            }

            $builder->groupEnd();
        }

        return $builder->findAll();
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
