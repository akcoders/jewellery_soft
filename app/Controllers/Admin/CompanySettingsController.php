<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;

class CompanySettingsController extends BaseController
{
    private CompanySettingModel $model;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->model = new CompanySettingModel();
    }

    public function index(): string
    {
        return view('admin/company_settings/index', [
            'title' => 'Company Settings',
            'setting' => $this->getSettingRow(),
        ]);
    }

    public function update()
    {
        if (! $this->validate([
            'company_name' => 'permit_empty|max_length[180]',
            'address_line' => 'permit_empty|max_length[255]',
            'city' => 'permit_empty|max_length[80]',
            'state' => 'permit_empty|max_length[80]',
            'pincode' => 'permit_empty|max_length[20]',
            'phone' => 'permit_empty|max_length[40]',
            'email' => 'permit_empty|valid_email|max_length[120]',
            'gstin' => 'permit_empty|max_length[30]',
            'issuement_suffix' => 'permit_empty|max_length[20]',
            'delivery_challan_suffix' => 'permit_empty|max_length[20]',
            'sale_bill_suffix' => 'permit_empty|max_length[20]',
            'onesignal_app_id' => 'permit_empty|max_length[120]',
            'onesignal_rest_api_key' => 'permit_empty|max_length[255]',
            'onesignal_sender_id' => 'permit_empty|max_length[80]',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            $message = $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
            return redirect()->back()->withInput()->with('error', $message);
        }

        $row = $this->getSettingRow();
        $id = (int) ($row['id'] ?? 0);
        $data = [
            'company_name' => trim((string) $this->request->getPost('company_name')) ?: null,
            'address_line' => trim((string) $this->request->getPost('address_line')) ?: null,
            'city' => trim((string) $this->request->getPost('city')) ?: null,
            'state' => trim((string) $this->request->getPost('state')) ?: null,
            'pincode' => trim((string) $this->request->getPost('pincode')) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'gstin' => trim((string) $this->request->getPost('gstin')) ?: null,
            'issuement_suffix' => strtoupper(trim((string) $this->request->getPost('issuement_suffix'))) ?: null,
            'delivery_challan_suffix' => strtoupper(trim((string) $this->request->getPost('delivery_challan_suffix'))) ?: null,
            'sale_bill_suffix' => strtoupper(trim((string) $this->request->getPost('sale_bill_suffix'))) ?: null,
            'onesignal_enabled' => $this->request->getPost('onesignal_enabled') ? 1 : 0,
            'onesignal_app_id' => trim((string) $this->request->getPost('onesignal_app_id')) ?: null,
            'onesignal_rest_api_key' => trim((string) $this->request->getPost('onesignal_rest_api_key')) ?: null,
            'onesignal_sender_id' => trim((string) $this->request->getPost('onesignal_sender_id')) ?: null,
        ];

        $logo = $this->request->getFile('logo');
        if ($logo && $logo->isValid() && $logo->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! in_array(strtolower((string) $logo->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return redirect()->back()->withInput()->with('error', 'Logo must be jpg, jpeg, png, or webp.');
            }
            if ($logo->getSizeByUnit('kb') > 4096) {
                return redirect()->back()->withInput()->with('error', 'Logo size must be 4MB or less.');
            }
            $uploadDir = FCPATH . 'uploads/company';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . strtolower((string) $logo->getExtension());
            $logo->move($uploadDir, $newName);
            $data['logo_path'] = 'uploads/company/' . $newName;
        }

        if ($id > 0) {
            $this->model->update($id, $data);
        } else {
            $this->model->insert($data);
        }

        return redirect()->to(site_url('admin/company-settings'))->with('success', 'Company settings saved.');
    }

    /**
     * @return array<string,mixed>
     */
    private function getSettingRow(): array
    {
        $row = $this->model->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }
}
