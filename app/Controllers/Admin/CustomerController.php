<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;

class CustomerController extends BaseController
{
    private CustomerModel $customerModel;
    private CustomerAddressModel $addressModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->customerModel = new CustomerModel();
        $this->addressModel  = new CustomerAddressModel();
    }

    public function index(): string
    {
        $customers = $this->customerModel->orderBy('id', 'DESC')->findAll();

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
            'email'      => 'permit_empty|valid_email',
            'gstin'      => 'permit_empty|max_length[25]',
            'terms_text' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $customerCode = 'CU' . date('ymdHis') . random_int(10, 99);

        $customerId = $this->customerModel->insert([
            'customer_code' => $customerCode,
            'name'          => trim((string) $this->request->getPost('name')),
            'phone'         => trim((string) $this->request->getPost('phone')),
            'email'         => trim((string) $this->request->getPost('email')),
            'gstin'         => trim((string) $this->request->getPost('gstin')),
            'terms_text'    => trim((string) $this->request->getPost('terms_text')),
            'is_active'     => 1,
        ], true);

        $this->storeAddress((int) $customerId, 'Billing', 'billing_');
        $this->storeAddress((int) $customerId, 'Shipping', 'shipping_');

        return redirect()->to(site_url('admin/customers'))->with('success', 'Customer created successfully.');
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

