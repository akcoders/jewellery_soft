<?php

namespace App\Controllers\Admin\Access;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\RbacService;
use RuntimeException;
use Throwable;

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

    public function create(): string
    {
        return view('admin/access/users/create', [
            'title' => 'Create User',
            'roles' => $this->rbacService->activeRoles(),
        ]);
    }

    public function store()
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[150]',
            'email' => 'required|valid_email|is_unique[admin_users.email]',
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $roleIds = $this->validRoleIds($this->request->getPost('role_ids'));
        if ($roleIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one active role for the new user.');
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $userId = (int) $this->adminUserModel->insert([
                'name' => trim((string) $this->request->getPost('name')),
                'email' => strtolower(trim((string) $this->request->getPost('email'))),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            ], true);
            if ($userId <= 0) {
                throw new RuntimeException('User account could not be created.');
            }
            $this->rbacService->syncUserRoles($userId, $roleIds);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Admin user creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'User could not be created. Please verify the details and try again.');
        }

        return redirect()->to(site_url('admin/access/users/' . $userId))->with('success', 'User created successfully.');
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

    public function updatePassword(int $id)
    {
        $user = $this->adminUserModel->find($id);
        if (! $user) {
            return redirect()->to(site_url('admin/access/users'))->with('error', 'Admin user not found.');
        }

        if (! $this->validate([
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ])) {
            return redirect()->to(site_url('admin/access/users/' . $id))->with('error', $this->firstValidationError());
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $this->adminUserModel->update($id, [
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            ]);
            if ($db->tableExists('admin_remember_tokens')) {
                $db->table('admin_remember_tokens')->where('admin_user_id', $id)->delete();
            }
            if ($db->tableExists('mobile_api_tokens')) {
                $db->table('mobile_api_tokens')->where('admin_user_id', $id)->delete();
            }
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Admin password update failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('admin/access/users/' . $id))->with('error', 'Password could not be updated.');
        }

        return redirect()->to(site_url('admin/access/users/' . $id))->with('success', 'Password updated. Remember-me and mobile sessions were revoked.');
    }

    private function validRoleIds(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $requested = array_values(array_unique(array_filter(array_map('intval', $values))));
        if ($requested === []) {
            return [];
        }
        $active = array_map('intval', array_column($this->rbacService->activeRoles(), 'id'));
        return array_values(array_intersect($requested, $active));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
