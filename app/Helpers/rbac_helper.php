<?php

use App\Services\RbacService;

if (! function_exists('rbac_service')) {
    function rbac_service(): RbacService
    {
        static $service;
        if ($service === null) {
            $service = new RbacService();
        }

        return $service;
    }
}

if (! function_exists('admin_can')) {
    function admin_can(string $permissionCode): bool
    {
        $userId = (int) (session('admin_id') ?? 0);
        return rbac_service()->userCan($userId, $permissionCode);
    }
}

if (! function_exists('admin_can_any')) {
    function admin_can_any(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if (admin_can((string) $permissionCode)) {
                return true;
            }
        }

        return false;
    }
}
