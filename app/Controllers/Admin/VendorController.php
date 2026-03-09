<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\VendorModel;

class VendorController extends BaseController
{
    private VendorModel $vendorModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->vendorModel = new VendorModel();
    }

    public function index(): string
    {
        return view('admin/vendors/index', [
            'title'   => 'Vendors',
            'vendors' => $this->vendorModel->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function store()
    {
        $rules = [
            'name'           => 'required|max_length[150]',
            'contact_person' => 'permit_empty|max_length[100]',
            'phone'          => 'permit_empty|max_length[30]',
            'email'          => 'permit_empty|valid_email|max_length[120]',
            'gstin'          => 'permit_empty|max_length[25]',
            'address'        => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($this->vendorModel->where('name', $name)->first()) {
            return redirect()->back()->withInput()->with('error', 'Vendor name already exists.');
        }

        $this->vendorModel->insert([
            'name'           => $name,
            'contact_person' => trim((string) $this->request->getPost('contact_person')),
            'phone'          => trim((string) $this->request->getPost('phone')),
            'email'          => trim((string) $this->request->getPost('email')),
            'gstin'          => trim((string) $this->request->getPost('gstin')),
            'address'        => trim((string) $this->request->getPost('address')),
            'is_active'      => 1,
        ]);

        return redirect()->to(site_url('admin/vendors'))->with('success', 'Vendor added.');
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}

