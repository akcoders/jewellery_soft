<?php

namespace App\Filters;

use App\Services\AdminRememberMeService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('admin_logged_in') && ! (new AdminRememberMeService())->restore($request)) {
            return redirect()->to(site_url('admin/login'))->with('error', 'Please login first.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return;
    }
}
