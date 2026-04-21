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
            'whatsapp_api_url' => 'permit_empty|max_length[255]',
            'whatsapp_http_method' => 'permit_empty|in_list[POST,PUT,PATCH]',
            'whatsapp_auth_type' => 'permit_empty|in_list[none,bearer,basic,custom]',
            'whatsapp_auth_header' => 'permit_empty|max_length[80]',
            'whatsapp_auth_token' => 'permit_empty|max_length[255]',
            'whatsapp_sender_id' => 'permit_empty|max_length[120]',
            'whatsapp_timeout_sec' => 'permit_empty|integer|greater_than[0]|less_than_equal_to[120]',
            'whatsapp_alert_numbers' => 'permit_empty',
            'whatsapp_extra_headers_json' => 'permit_empty',
            'whatsapp_body_template' => 'permit_empty',
            'whatsapp_template_order_created' => 'permit_empty',
            'whatsapp_template_order_status_changed' => 'permit_empty',
            'whatsapp_template_order_ready' => 'permit_empty',
            'whatsapp_template_order_over_budget' => 'permit_empty',
            'whatsapp_template_order_delay_daily' => 'permit_empty',
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
            'whatsapp_enabled' => $this->request->getPost('whatsapp_enabled') ? 1 : 0,
            'whatsapp_api_url' => trim((string) $this->request->getPost('whatsapp_api_url')) ?: null,
            'whatsapp_http_method' => strtoupper(trim((string) $this->request->getPost('whatsapp_http_method'))) ?: 'POST',
            'whatsapp_auth_type' => strtolower(trim((string) $this->request->getPost('whatsapp_auth_type'))) ?: 'none',
            'whatsapp_auth_header' => trim((string) $this->request->getPost('whatsapp_auth_header')) ?: null,
            'whatsapp_auth_token' => trim((string) $this->request->getPost('whatsapp_auth_token')) ?: null,
            'whatsapp_sender_id' => trim((string) $this->request->getPost('whatsapp_sender_id')) ?: null,
            'whatsapp_timeout_sec' => max(5, min(120, (int) ($this->request->getPost('whatsapp_timeout_sec') ?: 20))),
            'whatsapp_alert_numbers' => trim((string) $this->request->getPost('whatsapp_alert_numbers')) ?: null,
            'whatsapp_extra_headers_json' => trim((string) $this->request->getPost('whatsapp_extra_headers_json')) ?: null,
            'whatsapp_body_template' => trim((string) $this->request->getPost('whatsapp_body_template')) ?: null,
            'whatsapp_notify_order_created' => $this->request->getPost('whatsapp_notify_order_created') ? 1 : 0,
            'whatsapp_notify_order_status_changed' => $this->request->getPost('whatsapp_notify_order_status_changed') ? 1 : 0,
            'whatsapp_notify_order_ready' => $this->request->getPost('whatsapp_notify_order_ready') ? 1 : 0,
            'whatsapp_notify_order_over_budget' => $this->request->getPost('whatsapp_notify_order_over_budget') ? 1 : 0,
            'whatsapp_notify_order_delay_daily' => $this->request->getPost('whatsapp_notify_order_delay_daily') ? 1 : 0,
            'whatsapp_template_order_created' => trim((string) $this->request->getPost('whatsapp_template_order_created')) ?: null,
            'whatsapp_template_order_status_changed' => trim((string) $this->request->getPost('whatsapp_template_order_status_changed')) ?: null,
            'whatsapp_template_order_ready' => trim((string) $this->request->getPost('whatsapp_template_order_ready')) ?: null,
            'whatsapp_template_order_over_budget' => trim((string) $this->request->getPost('whatsapp_template_order_over_budget')) ?: null,
            'whatsapp_template_order_delay_daily' => trim((string) $this->request->getPost('whatsapp_template_order_delay_daily')) ?: null,
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
