<?php

namespace App\Controllers\Admin\Access;

use App\Controllers\BaseController;
use App\Models\PermissionModel;
use App\Services\RbacService;

class PermissionsController extends BaseController
{
    private PermissionModel $permissionModel;
    private RbacService $rbacService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->permissionModel = new PermissionModel();
        $this->rbacService = new RbacService();
    }

    public function index(): string
    {
        $rows = $this->permissionModel
            ->orderBy('module_group', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        return view('admin/access/permissions/index', [
            'title' => 'Permission Master',
            'rows' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/access/permissions/form', [
            'title' => 'Add Permission',
            'row' => null,
            'moduleOptions' => $this->moduleOptions(),
            'formAction' => site_url('admin/access/permissions'),
        ]);
    }

    public function store()
    {
        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->permissionModel->where('code', $data['code'])->first()) {
            return redirect()->back()->withInput()->with('error', 'Permission code already exists.');
        }

        $this->permissionModel->insert($data);
        $this->rbacService->flushCache();

        return redirect()->to(site_url('admin/access/permissions'))->with('success', 'Permission created.');
    }

    public function edit(int $id): string
    {
        $row = $this->permissionModel->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Permission not found.');
        }

        return view('admin/access/permissions/form', [
            'title' => 'Edit Permission',
            'row' => $row,
            'moduleOptions' => $this->moduleOptions(),
            'formAction' => site_url('admin/access/permissions/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->permissionModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/access/permissions'))->with('error', 'Permission not found.');
        }

        $data = $this->validatedPayload();
        if (! is_array($data)) {
            return $data;
        }

        if ($this->permissionModel->where('code', $data['code'])->where('id !=', $id)->first()) {
            return redirect()->back()->withInput()->with('error', 'Permission code already exists.');
        }

        $this->permissionModel->update($id, $data);
        $this->rbacService->flushCache();

        return redirect()->to(site_url('admin/access/permissions'))->with('success', 'Permission updated.');
    }

    public function toggleStatus(int $id)
    {
        $row = $this->permissionModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/access/permissions'))->with('error', 'Permission not found.');
        }

        $this->permissionModel->update($id, ['is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1]);
        $this->rbacService->flushCache();

        return redirect()->to(site_url('admin/access/permissions'))->with('success', 'Permission status updated.');
    }

    private function validatedPayload()
    {
        $rules = [
            'code' => 'required|max_length[100]',
            'name' => 'required|max_length[120]',
            'module_group' => 'required|max_length[80]',
            'action_key' => 'permit_empty|max_length[40]',
            'sort_order' => 'permit_empty|integer',
            'description' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        return [
            'code' => strtolower(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'module_group' => trim((string) $this->request->getPost('module_group')),
            'action_key' => trim((string) $this->request->getPost('action_key')) ?: null,
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function moduleOptions(): array
    {
        $modules = $this->permissionModel->select('module_group')->distinct()->orderBy('module_group', 'ASC')->findColumn('module_group') ?: [];
        return array_values(array_filter(array_map('strval', $modules)));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
