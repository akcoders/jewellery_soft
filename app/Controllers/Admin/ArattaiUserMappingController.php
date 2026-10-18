<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ArattaiUserMappingModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ArattaiUserMappingController extends BaseController
{
    private const TYPES = ['admin', 'customer', 'karigar', 'follower'];

    public function index(): string
    {
        $db = db_connect();
        $rows = $db->table('arattai_user_mappings')->orderBy('entity_type', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['entity_label'] = $this->entityLabel((string) $row['entity_type'], (int) $row['entity_id']);
        }
        unset($row);

        $capturedUsers = $db->tableExists('arattai_users')
            ? $db->table('arattai_users')->orderBy('last_seen_at', 'DESC')->limit(100)->get()->getResultArray()
            : [];

        return view('admin/arattai_user_mappings/index', [
            'title' => 'Arattai User Mapping',
            'rows' => $rows,
            'capturedUsers' => $capturedUsers,
        ]);
    }

    public function entities()
    {
        $type = strtolower(trim((string) $this->request->getGet('type')));
        if (! in_array($type, self::TYPES, true)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => 'Invalid role.']);
        }
        return $this->response->setJSON(['status' => 'ok', 'entities' => $this->entityOptions($type)]);
    }

    public function store()
    {
        $type = strtolower(trim((string) $this->request->getPost('entity_type')));
        $entityId = (int) $this->request->getPost('entity_id');
        $userId = preg_replace('/\D+/', '', trim((string) $this->request->getPost('arattai_user_id'))) ?? '';
        if (! in_array($type, self::TYPES, true) || $entityId <= 0 || $userId === '') {
            return redirect()->back()->withInput()->with('error', 'Role, ERP user and valid Arattai User ID are required.');
        }
        if ($this->entityLabel($type, $entityId) === '') {
            return redirect()->back()->withInput()->with('error', 'Selected ERP user was not found.');
        }

        $model = new ArattaiUserMappingModel();
        $existing = $model->where('entity_type', $type)->where('entity_id', $entityId)->first();
        $adminId = (int) session('admin_id') ?: null;
        $data = [
            'entity_type' => $type,
            'entity_id' => $entityId,
            'arattai_user_id' => $userId,
            'is_approval_admin' => $type === 'admin' && $this->request->getPost('is_approval_admin') ? 1 : 0,
            'is_active' => 1,
            'updated_by' => $adminId,
        ];
        if (is_array($existing)) {
            $model->update((int) $existing['id'], $data);
        } else {
            $data['created_by'] = $adminId;
            $model->insert($data);
        }
        return redirect()->to(site_url('admin/arattai-users'))->with('success', 'Arattai user mapping saved.');
    }

    public function toggle(int $id)
    {
        $model = new ArattaiUserMappingModel();
        $row = $model->find($id);
        if (! is_array($row)) {
            throw PageNotFoundException::forPageNotFound('Arattai mapping not found.');
        }
        $model->update($id, [
            'is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1,
            'updated_by' => (int) session('admin_id') ?: null,
        ]);
        return redirect()->to(site_url('admin/arattai-users'))->with('success', 'Arattai mapping status updated.');
    }

    /** @return list<array{id:int,label:string}> */
    private function entityOptions(string $type): array
    {
        $db = db_connect();
        if ($type === 'customer') {
            $rows = $db->table('customers')->select('id, customer_code, name, phone')->where('deleted_at', null)
                ->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
            return array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'label' => trim((string) $r['name']) . ((string) ($r['customer_code'] ?? '') !== '' ? ' · ' . $r['customer_code'] : '')], $rows);
        }
        if ($type === 'karigar') {
            $rows = $db->table('karigars')->select('id, name, phone')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
            return array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'label' => trim((string) $r['name']) . ((string) ($r['phone'] ?? '') !== '' ? ' · ' . $r['phone'] : '')], $rows);
        }
        $rows = $db->table('admin_users au')->select('au.id, au.name, au.email, e.full_name, e.employee_code')
            ->join('employees e', 'e.admin_user_id = au.id AND e.is_active = 1', 'left')->where('au.is_active', 1)
            ->orderBy('COALESCE(e.full_name, au.name)', 'ASC', false)->get()->getResultArray();
        return array_map(static function (array $r): array {
            $name = trim((string) (($r['full_name'] ?? '') ?: ($r['name'] ?? 'User')));
            $suffix = trim((string) (($r['employee_code'] ?? '') ?: ($r['email'] ?? '')));
            return ['id' => (int) $r['id'], 'label' => $name . ($suffix !== '' ? ' · ' . $suffix : '')];
        }, $rows);
    }

    private function entityLabel(string $type, int $entityId): string
    {
        $db = db_connect();
        if ($type === 'customer') {
            $row = $db->table('customers')->select('name, customer_code')->where('id', $entityId)->where('deleted_at', null)->get()->getRowArray();
            return is_array($row) ? trim((string) $row['name']) . ((string) ($row['customer_code'] ?? '') !== '' ? ' · ' . $row['customer_code'] : '') : '';
        }
        if ($type === 'karigar') {
            $row = $db->table('karigars')->select('name')->where('id', $entityId)->get()->getRowArray();
            return is_array($row) ? trim((string) $row['name']) : '';
        }
        if (in_array($type, ['admin', 'follower'], true)) {
            $row = $db->table('admin_users au')->select('au.name, au.email, e.full_name')
                ->join('employees e', 'e.admin_user_id = au.id AND e.is_active = 1', 'left')->where('au.id', $entityId)->get()->getRowArray();
            return is_array($row) ? trim((string) (($row['full_name'] ?? '') ?: ($row['name'] ?? '') ?: ($row['email'] ?? ''))) : '';
        }
        return '';
    }
}
