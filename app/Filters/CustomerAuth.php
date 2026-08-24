<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) session('customer_user_id');
        $customerId = (int) session('customer_id');
        if (! session('customer_user_logged_in') || $userId <= 0 || $customerId <= 0) {
            return redirect()->to(site_url('customer/login'))->with('error', 'Please login to continue.');
        }

        $active = db_connect()->table('customer_users cu')
            ->join('customers c', 'c.id = cu.customer_id')
            ->where('cu.id', $userId)
            ->where('cu.customer_id', $customerId)
            ->where('cu.is_active', 1)
            ->where('c.is_active', 1)
            ->countAllResults() === 1;
        if (! $active) {
            session()->remove([
                'customer_user_logged_in', 'customer_user_id', 'customer_id',
                'customer_user_name', 'customer_name', 'customer_user_role',
            ]);
            return redirect()->to(site_url('customer/login'))->with('error', 'Your customer login is no longer active.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
