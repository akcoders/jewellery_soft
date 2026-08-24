<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\CustomerUserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session('customer_user_logged_in')) {
            return redirect()->to(site_url('customer/orders'));
        }
        return view('customer/login', ['title' => 'Customer Login']);
    }

    public function attemptLogin()
    {
        $rules = ['email' => 'required|valid_email', 'password' => 'required'];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid email and password.');
        }
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $user = (new CustomerUserModel())
            ->select('customer_users.*, customers.name AS customer_name, customers.is_active AS customer_active')
            ->join('customers', 'customers.id = customer_users.customer_id')
            ->where('customer_users.email', $email)
            ->where('customer_users.is_active', 1)
            ->first();
        if (! $user || (int) ($user['customer_active'] ?? 0) !== 1
            || ! password_verify((string) $this->request->getPost('password'), (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid email or password.');
        }
        session()->regenerate(true);
        session()->set([
            'customer_user_logged_in' => true,
            'customer_user_id' => (int) $user['id'],
            'customer_id' => (int) $user['customer_id'],
            'customer_user_name' => (string) $user['name'],
            'customer_name' => (string) $user['customer_name'],
            'customer_user_role' => (string) $user['role'],
        ]);
        (new CustomerUserModel())->update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        return redirect()->to(site_url('customer/orders'));
    }

    public function logout()
    {
        session()->remove([
            'customer_user_logged_in', 'customer_user_id', 'customer_id',
            'customer_user_name', 'customer_name', 'customer_user_role',
        ]);
        return redirect()->to(site_url('customer/login'))->with('success', 'You have been logged out.');
    }
}
