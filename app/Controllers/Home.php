<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session('admin_logged_in')) {
            return redirect()->to(site_url('admin/dashboard'));
        }

        return redirect()->to(site_url('admin/login'));
    }
}
