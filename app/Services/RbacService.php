<?php

namespace App\Services;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserPermissionModel;
use App\Models\UserRoleModel;

class RbacService
{
    private static array $permissionCache = [];

    public function userCan(?int $userId, string $permissionCode): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }

        $permissions = $this->userPermissionCodes($userId);
        if ($permissions === ['__bootstrap_allow_all__']) {
            return true;
        }

        return in_array($permissionCode, $permissions, true);
    }

    public function userPermissionCodes(int $userId): array
    {
        if (isset(self::$permissionCache[$userId])) {
            return self::$permissionCache[$userId];
        }

        $db = db_connect();
        foreach (['roles', 'permissions', 'role_permissions', 'user_roles'] as $table) {
            if (! $db->tableExists($table)) {
                return self::$permissionCache[$userId] = ['__bootstrap_allow_all__'];
            }
        }
        foreach ([
            ['roles', 'is_active'],
            ['permissions', 'is_active'],
        ] as [$table, $field]) {
            if (! $db->fieldExists($field, $table)) {
                return self::$permissionCache[$userId] = ['__bootstrap_allow_all__'];
            }
        }

        $hasAssignments = (int) $db->table('user_roles')->where('user_id', $userId)->countAllResults() > 0;
        if ($db->tableExists('user_permissions')) {
            $hasAssignments = $hasAssignments || (int) $db->table('user_permissions')->where('user_id', $userId)->countAllResults() > 0;
        }

        if (! $hasAssignments) {
            return self::$permissionCache[$userId] = ['__bootstrap_allow_all__'];
        }

        $rolePermissions = $db->table('role_permissions rp')
            ->select('p.code')
            ->join('permissions p', 'p.id = rp.permission_id', 'inner')
            ->join('user_roles ur', 'ur.role_id = rp.role_id', 'inner')
            ->join('roles r', 'r.id = ur.role_id', 'left')
            ->where('ur.user_id', $userId)
            ->groupStart()
                ->where('p.is_active', 1)
                ->orWhere('p.is_active IS NULL', null, false)
            ->groupEnd()
            ->groupStart()
                ->where('r.is_active', 1)
                ->orWhere('r.is_active IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $codes = [];
        foreach ($rolePermissions as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        if ($db->tableExists('user_permissions')) {
            $overrides = $db->table('user_permissions up')
                ->select('p.code, up.access_type')
                ->join('permissions p', 'p.id = up.permission_id', 'inner')
                ->where('up.user_id', $userId)
                ->groupStart()
                    ->where('p.is_active', 1)
                    ->orWhere('p.is_active IS NULL', null, false)
                ->groupEnd()
                ->get()
                ->getResultArray();

            foreach ($overrides as $override) {
                $code = trim((string) ($override['code'] ?? ''));
                $type = strtolower(trim((string) ($override['access_type'] ?? '')));
                if ($code === '') {
                    continue;
                }
                if ($type === 'deny') {
                    unset($codes[$code]);
                } elseif ($type === 'allow') {
                    $codes[$code] = true;
                }
            }
        }

        return self::$permissionCache[$userId] = array_keys($codes);
    }

    public function groupedPermissions(): array
    {
        $rows = (new PermissionModel())
            ->orderBy('module_group', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $group = trim((string) ($row['module_group'] ?? 'General')) ?: 'General';
            $grouped[$group][] = $row;
        }

        return $grouped;
    }

    public function rolePermissionIds(int $roleId): array
    {
        return array_map('intval', (new RolePermissionModel())
            ->where('role_id', $roleId)
            ->findColumn('permission_id') ?: []);
    }

    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $model = new RolePermissionModel();
        $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds))));
        $model->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            $model->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        self::$permissionCache = [];
    }

    public function userRoleIds(int $userId): array
    {
        return array_map('intval', (new UserRoleModel())
            ->where('user_id', $userId)
            ->findColumn('role_id') ?: []);
    }

    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $model = new UserRoleModel();
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        $model->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            $model->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        self::$permissionCache = [];
    }

    public function userPermissionOverrides(int $userId): array
    {
        $rows = (new UserPermissionModel())->where('user_id', $userId)->findAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['permission_id']] = (string) $row['access_type'];
        }
        return $map;
    }

    public function syncUserPermissionOverrides(int $userId, array $overrides): void
    {
        $model = new UserPermissionModel();
        $model->where('user_id', $userId)->delete();

        foreach ($overrides as $permissionId => $accessType) {
            $permissionId = (int) $permissionId;
            $accessType = strtolower(trim((string) $accessType));
            if ($permissionId <= 0 || ! in_array($accessType, ['allow', 'deny'], true)) {
                continue;
            }

            $model->insert([
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'access_type' => $accessType,
            ]);
        }

        self::$permissionCache = [];
    }

    public function activeRoles(): array
    {
        return (new RoleModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    public function flushCache(): void
    {
        self::$permissionCache = [];
    }
}
