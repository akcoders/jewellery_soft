<?php

namespace App\Filters;

use App\Services\RbacService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminPermission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('admin_logged_in')) {
            return redirect()->to(site_url('admin/login'))->with('error', 'Please login first.');
        }

        $permissionCodes = is_array($arguments) ? array_filter(array_map('strval', $arguments)) : [];
        if ($permissionCodes === []) {
            return null;
        }

        $service = new RbacService();
        $userId = (int) (session('admin_id') ?? 0);
        foreach ($permissionCodes as $permissionCode) {
            if ($service->userCan($userId, $permissionCode)) {
                return null;
            }
        }

        return redirect()->to(site_url('admin/dashboard'))->with('error', 'You do not have permission to access this section.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return;
    }
}
