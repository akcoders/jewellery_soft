<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\CustomerUserModel;
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
