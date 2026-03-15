<?php

namespace App\Controllers\Admin\Access;

use App\Controllers\BaseController;
use App\Models\RoleModel;
use App\Services\RbacService;

class RolesController extends BaseController
{
    private RoleModel $roleModel;
    private RbacService $rbacService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->roleModel = new RoleModel();
        $this->rbacService = new RbacService();
    }

    public function index(): string
    {
        $rows = db_connect()->table('roles r')
            ->select('r.*, COUNT(DISTINCT rp.permission_id) AS permission_count, COUNT(DISTINCT ur.user_id) AS user_count')
            ->join('role_permissions rp', 'rp.role_id = r.id', 'left')
            ->join('user_roles ur', 'ur.role_id = r.id', 'left')
            ->groupBy('r.id')
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/access/roles/index', [
            'title' => 'Role Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/access/roles/form', [
            'title' => 'Add Role',
            'row' => null,
            'groupedPermissions' => $this->rbacService->groupedPermissions(),
            'selectedPermissionIds' => [],
            'formAction' => site_url('admin/access/roles'),
        ]);
    }

    public function store()
    {
        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->roleModel->where('role_code', $data['role_code'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Role code already exists.');
        }
        if ($this->roleModel->where('name', $data['name'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Role name already exists.');
        }

        $this->roleModel->insert($data);
        $roleId = (int) $this->roleModel->getInsertID();
        $this->rbacService->syncRolePermissions($roleId, $this->request->getPost('permission_ids') ?? []);

        return redirect()->to(site_url('admin/access/roles'))->with('success', 'Role created.');
    }

    public function edit(int $id): string
    {
        $row = $this->roleModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Role not found.');
        }

        return view('admin/access/roles/form', [
            'title' => 'Edit Role',
            'row' => $row,
            'groupedPermissions' => $this->rbacService->groupedPermissions(),
            'selectedPermissionIds' => $this->rbacService->rolePermissionIds($id),
            'formAction' => site_url('admin/access/roles/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->roleModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/access/roles'))->with('error', 'Role not found.');
        }

        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->roleModel->where('role_code', $data['role_code'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Role code already exists.');
        }
        if ($this->roleModel->where('name', $data['name'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Role name already exists.');
        }

        $this->roleModel->update($id, $data);
        $this->rbacService->syncRolePermissions($id, $this->request->getPost('permission_ids') ?? []);

        return redirect()->to(site_url('admin/access/roles'))->with('success', 'Role updated.');
    }

    public function toggleStatus(int $id)
    {
        $row = $this->roleModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/access/roles'))->with('error', 'Role not found.');
        }

        $this->roleModel->update($id, ['is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1]);
        $this->rbacService->flushCache();

        return redirect()->to(site_url('admin/access/roles'))->with('success', 'Role status updated.');
    }

    private function validatedPayload()
    {
        $rules = [
            'role_code' => 'required|max_length[40]',
            'name' => 'required|max_length[80]',
            'description' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        return [
            'role_code' => strtoupper(trim((string) $this->request->getPost('role_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
