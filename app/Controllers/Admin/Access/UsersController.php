<?php

namespace App\Controllers\Admin\Access;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\RbacService;

class UsersController extends BaseController
{
    private AdminUserModel $adminUserModel;
    private RbacService $rbacService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->adminUserModel = new AdminUserModel();
        $this->rbacService = new RbacService();
    }

    public function index(): string
    {
        $rows = db_connect()->table('admin_users au')
            ->select('au.*, e.employee_code, e.full_name as employee_name, d.name as designation_name, COUNT(DISTINCT ur.role_id) as role_count, COUNT(DISTINCT up.permission_id) as override_count')
            ->join('employees e', 'e.admin_user_id = au.id', 'left')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->join('user_roles ur', 'ur.user_id = au.id', 'left')
            ->join('user_permissions up', 'up.user_id = au.id', 'left')
            ->groupBy('au.id')
            ->orderBy('au.name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/access/users/index', [
            'title' => 'User Access Control',
            'rows' => $rows,
        ]);
    }

    public function edit(int $id): string
    {
        $user = db_connect()->table('admin_users au')
            ->select('au.*, e.employee_code, e.full_name as employee_name, dep.name as department_name, d.name as designation_name')
            ->join('employees e', 'e.admin_user_id = au.id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->where('au.id', $id)
            ->get()
            ->getRowArray();
        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Admin user not found.');
        }

        return view('admin/access/users/form', [
            'title' => 'Manage User Access',
            'user' => $user,
            'roles' => $this->rbacService->activeRoles(),
            'groupedPermissions' => $this->rbacService->groupedPermissions(),
            'selectedRoleIds' => $this->rbacService->userRoleIds($id),
            'permissionOverrides' => $this->rbacService->userPermissionOverrides($id),
            'formAction' => site_url('admin/access/users/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $user = $this->adminUserModel->find($id);
        if (! $user) {
            return redirect()->to(site_url('admin/access/users'))->with('error', 'Admin user not found.');
        }

        $roleIds = $this->request->getPost('role_ids') ?? [];
        $overrideTypes = $this->request->getPost('override_type') ?? [];
        $overrides = [];
        if (is_array($overrideTypes)) {
            foreach ($overrideTypes as $permissionId => $type) {
                $type = strtolower(trim((string) $type));
                if (in_array($type, ['allow', 'deny'], true)) {
                    $overrides[$permissionId] = $type;
                }
            }
        }

        $this->rbacService->syncUserRoles($id, is_array($roleIds) ? $roleIds : []);
        $this->rbacService->syncUserPermissionOverrides($id, $overrides);

        return redirect()->to(site_url('admin/access/users/' . $id))->with('success', 'User access updated.');
    }
}
