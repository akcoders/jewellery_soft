<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    private AdminUserModel $adminUsers;

    public function __construct()
    {
        $this->adminUsers = new AdminUserModel();
        helper(['form', 'url']);
    }

    public function login(): string
    {
        return view('admin/auth/login', [
            'assetBase' => $this->assetBase(),
            'error'     => session('error'),
            'success'   => session('success'),
        ]);
    }

    public function attemptLogin(): RedirectResponse
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        $admin = $this->adminUsers->where('email', $email)->first();

        if (! $admin || ! password_verify($password, $admin['password_hash']) || (int) $admin['is_active'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'Invalid email or password.');
        }

        session()->regenerate();
        session()->set([
            'admin_logged_in' => true,
            'admin_id'        => (int) $admin['id'],
            'admin_name'      => $admin['name'],
            'admin_email'     => $admin['email'],
        ]);

        return redirect()->to(site_url('admin/dashboard'));
    }

    public function register(): string
    {
        return view('admin/auth/register', [
            'assetBase' => $this->assetBase(),
            'error'     => session('error'),
            'success'   => session('success'),
        ]);
    }

    public function storeUser(): RedirectResponse
    {
        $rules = [
            'name'             => 'required|min_length[3]|max_length[150]',
            'email'            => 'required|valid_email|is_unique[admin_users.email]',
            'password'         => 'required|min_length[6]|max_length[64]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $this->adminUsers->insert([
            'name'          => trim((string) $this->request->getPost('name')),
            'email'         => strtolower(trim((string) $this->request->getPost('email'))),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'is_active'     => 1,
        ]);

        return redirect()->to(site_url('admin/login'))->with('success', 'Account created. Please login.');
    }

    public function dashboard(): string
    {
        return view('admin/dashboard', [
            'assetBase'  => $this->assetBase(),
            'adminName'  => (string) session('admin_name'),
            'adminEmail' => (string) session('admin_email'),
            'success'    => session('success'),
        ]);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('admin/login'))->with('success', 'You have been logged out.');
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];

        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function assetBase(): string
    {
        $segments = service('uri')->getSegments();
        $first = (string) ($segments[0] ?? '');
        $basePath = $first === 'public' ? 'public/template/assets' : 'template/assets';
        return base_url($basePath);
    }
}
