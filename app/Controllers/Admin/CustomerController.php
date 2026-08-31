<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\CustomerUserModel;
use App\Services\CustomerRememberMeService;
use RuntimeException;
use Throwable;

class CustomerController extends BaseController
{
    private CustomerModel $customerModel;
    private CustomerAddressModel $addressModel;
    private CustomerUserModel $customerUserModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->customerModel = new CustomerModel();
        $this->addressModel  = new CustomerAddressModel();
        $this->customerUserModel = new CustomerUserModel();
    }

    public function index(): string
    {
        $customers = $this->customerModel
            ->select('customers.*, (SELECT COUNT(*) FROM customer_users cu WHERE cu.customer_id = customers.id AND cu.is_active = 1) AS portal_user_count, (SELECT COUNT(*) FROM customer_users cu WHERE cu.customer_id = customers.id AND cu.role = "sales_person" AND cu.is_active = 1) AS sales_person_count', false)
            ->orderBy('customers.id', 'DESC')
            ->findAll();

        return view('admin/customers/index', [
            'title'     => 'Customers',
            'customers' => $customers,
        ]);
    }

    public function create(): string
    {
        return view('admin/customers/create', [
            'title' => 'Add Customer',
        ]);
    }

    public function show(int $id): string
    {
        $customer = $this->customerModel->find($id);
        if (! $customer) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Customer not found.');
        }

        $db = db_connect();
        $orderSummary = $db->table('orders')
            ->select('COUNT(*) AS total_orders, MAX(created_at) AS last_order_at', false)
            ->where('customer_id', $id)
            ->get()
            ->getRowArray() ?? [];

        return view('admin/customers/show', [
            'title' => 'Customer Details',
            'customer' => $customer,
            'addresses' => $this->addressModel->where('customer_id', $id)->orderBy('is_default', 'DESC')->orderBy('id', 'ASC')->findAll(),
            'portalUsers' => $this->customerUserModel->where('customer_id', $id)->orderBy('role', 'ASC')->orderBy('name', 'ASC')->findAll(),
            'orderSummary' => $orderSummary,
            'recentOrders' => $db->table('orders')->select('id, order_no, status, due_date, created_at')->where('customer_id', $id)->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
        ]);
    }

    public function store()
    {
        $rules = [
            'name'       => 'required|min_length[2]|max_length[150]',
            'phone'      => 'permit_empty|max_length[20]',
            'email'      => 'required|valid_email|is_unique[customers.email]|is_unique[customer_users.email]',
            'password'   => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
            'gstin'      => 'permit_empty|max_length[25]',
            'terms_text' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $customerCode = 'CU' . date('ymdHis') . random_int(10, 99);

        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $name = trim((string) $this->request->getPost('name'));
            $phone = trim((string) $this->request->getPost('phone'));
            $email = strtolower(trim((string) $this->request->getPost('email')));
            $customerId = $this->customerModel->insert([
                'customer_code' => $customerCode,
                'name'          => $name,
                'phone'         => $phone,
                'email'         => $email,
                'gstin'         => trim((string) $this->request->getPost('gstin')),
                'terms_text'    => trim((string) $this->request->getPost('terms_text')),
                'is_active'     => 1,
            ], true);
            if (! $customerId) {
                throw new RuntimeException('Customer profile could not be saved.');
            }

            $portalUserId = $this->customerUserModel->insert([
                'customer_id' => (int) $customerId,
                'name' => $name,
                'mobile' => $phone ?: null,
                'email' => $email,
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => 'customer_admin',
                'is_active' => 1,
            ], true);
            if (! $portalUserId) {
                throw new RuntimeException('Customer portal login could not be saved.');
            }
            $this->storeAddress((int) $customerId, 'Billing', 'billing_');
            $this->storeAddress((int) $customerId, 'Shipping', 'shipping_');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer and portal user creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Customer could not be created. Please verify the email and try again.');
        }
        $db->transComplete();

        return redirect()->to(site_url('admin/customers'))->with('success', 'Customer and portal login created successfully.');
    }

    public function updatePortalPassword(int $customerId, int $userId)
    {
        $customer = $this->customerModel->find($customerId);
        $portalUser = $this->customerUserModel
            ->where('id', $userId)
            ->where('customer_id', $customerId)
            ->first();
        if (! $customer || ! $portalUser) {
            return redirect()->to(site_url('admin/customers'))->with('error', 'Customer login account not found.');
        }

        if (! $this->validate([
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ])) {
            return redirect()->to(site_url('admin/customers/' . $customerId))->with('error', $this->firstValidationError());
        }

        try {
            $this->customerUserModel->update($userId, [
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            ]);
            (new CustomerRememberMeService())->revokeUser($userId);
        } catch (Throwable $e) {
            log_message('error', 'Customer portal password update failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('admin/customers/' . $customerId))->with('error', 'Portal password could not be updated.');
        }

        return redirect()->to(site_url('admin/customers/' . $customerId))->with('success', 'Password updated and remembered sessions revoked for ' . (string) ($portalUser['name'] ?? 'customer user') . '.');
    }

    public function storePortalUser(int $customerId)
    {
        $customer = $this->customerModel->find($customerId);
        if (! $customer) {
            return redirect()->to(site_url('admin/customers'))->with('error', 'Customer not found.');
        }

        if (! $this->validate([
            'name' => 'required|min_length[2]|max_length[150]',
            'mobile' => 'permit_empty|max_length[30]',
            'email' => 'required|valid_email|is_unique[customer_users.email]',
            'role' => 'required|in_list[customer_admin,sales_person]',
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ])) {
            return redirect()->to(site_url('admin/customers/' . $customerId))->with('error', $this->firstValidationError());
        }

        try {
            $userId = (int) $this->customerUserModel->insert([
                'customer_id' => $customerId,
                'name' => trim((string) $this->request->getPost('name')),
                'mobile' => trim((string) $this->request->getPost('mobile')) ?: null,
                'email' => strtolower(trim((string) $this->request->getPost('email'))),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => (string) $this->request->getPost('role'),
                'is_active' => 1,
            ], true);
            if ($userId <= 0) {
                throw new RuntimeException('Portal user could not be created.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Customer portal user creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('admin/customers/' . $customerId))->with('error', 'Portal user could not be created. Please verify the email and try again.');
        }

        return redirect()->to(site_url('admin/customers/' . $customerId))->with('success', 'Customer portal user created successfully.');
    }

    private function storeAddress(int $customerId, string $type, string $prefix): void
    {
        $line1 = trim((string) $this->request->getPost($prefix . 'line1'));
        $line2 = trim((string) $this->request->getPost($prefix . 'line2'));
        $city  = trim((string) $this->request->getPost($prefix . 'city'));
        $state = trim((string) $this->request->getPost($prefix . 'state'));

        if ($line1 === '' && $line2 === '' && $city === '' && $state === '') {
            return;
        }

        $this->addressModel->insert([
            'customer_id'  => $customerId,
            'address_type' => $type,
            'line1'        => $line1,
            'line2'        => $line2,
            'city'         => $city,
            'state'        => $state,
            'country'      => trim((string) $this->request->getPost($prefix . 'country')),
            'pincode'      => trim((string) $this->request->getPost($prefix . 'pincode')),
            'is_default'   => $type === 'Billing' ? 1 : 0,
        ]);
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
