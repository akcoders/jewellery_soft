<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Services\ArattaiOAuthService;
use App\Services\AttraiIntegrationService;
use App\Services\ArattaiUserCaptureService;
use Throwable;

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
            'attraiReviewers' => $this->attraiReviewers(),
            'attraiRedirectUri' => (new ArattaiOAuthService())->redirectUri(),
            'recentArattaiUsers' => (new ArattaiUserCaptureService())->recent(),
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
            'issuement_start_count' => 'required|integer|greater_than[0]|less_than_equal_to[999999999]',
            'issuement_last_number' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[999999999]',
            'delivery_challan_suffix' => 'permit_empty|max_length[20]',
            'delivery_challan_last_number' => 'permit_empty|integer',
            'mumbai_branch_address' => 'permit_empty|max_length[1000]',
            'hyderabad_branch_address' => 'permit_empty|max_length[1000]',
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
            'attrai_api_url' => 'permit_empty|max_length[500]',
            'attrai_http_method' => 'permit_empty|in_list[POST,PUT,PATCH]',
            'attrai_auth_type' => 'permit_empty|in_list[none,bearer,basic,custom]',
            'attrai_auth_header' => 'permit_empty|max_length[80]',
            'attrai_auth_token' => 'permit_empty|max_length[500]',
            'attrai_sender_id' => 'permit_empty|max_length[190]',
            'attrai_extra_headers_json' => 'permit_empty',
            'attrai_body_template' => 'permit_empty',
            'attrai_approval_numbers' => 'permit_empty',
            'attrai_webhook_secret' => 'permit_empty|max_length[255]',
            'attrai_approval_reviewer_id' => 'permit_empty|integer|greater_than[0]',
            'attrai_template_approval_requested' => 'permit_empty',
            'attrai_template_karigar_assigned' => 'permit_empty',
            'attrai_template_follower_assigned' => 'permit_empty',
            'attrai_approval_body_template' => 'permit_empty',
            'attrai_client_id' => 'permit_empty|max_length[500]',
            'attrai_client_secret' => 'permit_empty|max_length[1000]',
            'attrai_company_id' => 'permit_empty|max_length[80]',
            'attrai_business_id' => 'permit_empty|max_length[80]',
            'attrai_business_number_id' => 'permit_empty|max_length[80]',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            $message = $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
            return redirect()->back()->withInput()->with('error', $message);
        }

        $row = $this->getSettingRow();
        $id = (int) ($row['id'] ?? 0);
        $attraiEnabled = $this->request->getPost('attrai_enabled') ? 1 : 0;
        $attraiSecret = trim((string) $this->request->getPost('attrai_webhook_secret'));
        if ($attraiSecret === '') {
            $attraiSecret = trim((string) ($row['attrai_webhook_secret'] ?? ''));
        }
        $attraiClientSecret = trim((string) $this->request->getPost('attrai_client_secret'));
        if ($attraiClientSecret === '') {
            $attraiClientSecret = trim((string) ($row['attrai_client_secret'] ?? ''));
        }
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
            'issuement_start_count' => max(1, (int) $this->request->getPost('issuement_start_count')),
            'issuement_last_number' => max(0, (int) $this->request->getPost('issuement_last_number')),
            'delivery_challan_suffix' => strtoupper(trim((string) $this->request->getPost('delivery_challan_suffix'))) ?: null,
            'delivery_challan_last_number' => max(0, (int) $this->request->getPost('delivery_challan_last_number')),
            'mumbai_branch_address' => trim((string) $this->request->getPost('mumbai_branch_address')) ?: null,
            'hyderabad_branch_address' => trim((string) $this->request->getPost('hyderabad_branch_address')) ?: null,
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
            'attrai_enabled' => $attraiEnabled,
            'attrai_api_url' => ArattaiOAuthService::API_ROOT . '/transactions',
            'attrai_http_method' => 'POST',
            'attrai_auth_type' => 'bearer',
            'attrai_auth_header' => 'Authorization',
            'attrai_auth_token' => $row['attrai_access_token'] ?? $row['attrai_auth_token'] ?? null,
            'attrai_sender_id' => trim((string) $this->request->getPost('attrai_business_number_id')) ?: ($row['attrai_business_number_id'] ?? null),
            'attrai_extra_headers_json' => null,
            'attrai_body_template' => null,
            'attrai_approval_numbers' => trim((string) $this->request->getPost('attrai_approval_numbers')) ?: null,
            'attrai_webhook_secret' => $attraiSecret !== '' ? $attraiSecret : null,
            'attrai_approval_reviewer_id' => (int) $this->request->getPost('attrai_approval_reviewer_id') ?: null,
            'attrai_notify_approval_requested' => $this->request->getPost('attrai_notify_approval_requested') ? 1 : 0,
            'attrai_notify_karigar_assigned' => $this->request->getPost('attrai_notify_karigar_assigned') ? 1 : 0,
            'attrai_template_approval_requested' => trim((string) $this->request->getPost('attrai_template_approval_requested')) ?: null,
            'attrai_template_karigar_assigned' => trim((string) $this->request->getPost('attrai_template_karigar_assigned')) ?: null,
            'attrai_template_follower_assigned' => trim((string) $this->request->getPost('attrai_template_follower_assigned')) ?: null,
            'attrai_approval_body_template' => null,
            'attrai_client_id' => trim((string) $this->request->getPost('attrai_client_id')) ?: null,
            'attrai_client_secret' => $attraiClientSecret !== '' ? $attraiClientSecret : null,
            'attrai_company_id' => trim((string) $this->request->getPost('attrai_company_id')) ?: ($row['attrai_company_id'] ?? null),
            'attrai_business_id' => trim((string) $this->request->getPost('attrai_business_id')) ?: ($row['attrai_business_id'] ?? null),
            'attrai_business_number_id' => trim((string) $this->request->getPost('attrai_business_number_id')) ?: ($row['attrai_business_number_id'] ?? null),
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

    public function sendAttraiTest()
    {
        $number = trim((string) $this->request->getPost('test_number'));
        try {
            (new AttraiIntegrationService())->sendTestMessage($number);
            return redirect()->to(site_url('admin/company-settings') . '#arattai-test')
                ->with('success', 'ATTRAI demo message sent to ' . $number . '.');
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings') . '#arattai-test')
                ->with('error', $e->getMessage());
        }
    }

    public function connectArattai()
    {
        try {
            $state = bin2hex(random_bytes(24));
            session()->set('arattai_oauth_state', $state);
            return redirect()->to((new ArattaiOAuthService())->authorizationUrl($state));
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings'))->with('error', $e->getMessage());
        }
    }

    public function arattaiOAuthCallback()
    {
        $expectedState = trim((string) session('arattai_oauth_state'));
        session()->remove('arattai_oauth_state');
        $state = trim((string) $this->request->getGet('state'));
        if ($expectedState === '' || $state === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->to(site_url('admin/company-settings'))->with('error', 'Arattai OAuth state validation failed. Please connect again.');
        }
        $error = trim((string) $this->request->getGet('error'));
        if ($error !== '') {
            return redirect()->to(site_url('admin/company-settings'))->with('error', 'Arattai authorization was not completed: ' . $error);
        }

        try {
            (new ArattaiOAuthService())->connectFromAuthorizationCode((string) $this->request->getGet('code'));
            return redirect()->to(site_url('admin/company-settings'))
                ->with('success', 'Arattai connected. Company, business and business-number IDs were detected automatically.');
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings'))->with('error', $e->getMessage());
        }
    }

    public function createArattaiTemplates()
    {
        try {
            (new ArattaiOAuthService())->createAndSubmitDefaultTemplates();
            return redirect()->to(site_url('admin/company-settings'))
                ->with('success', 'Arattai approval and order-update templates were created and submitted for review.');
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings'))->with('error', $e->getMessage());
        }
    }

    public function configureArattaiWebhook()
    {
        try {
            $result = (new ArattaiOAuthService())->configureConversationWebhook();
            return redirect()->to(site_url('admin/company-settings'))->with(
                'success',
                'Arattai incoming webhook configured: ' . (string) ($result['url'] ?? '')
            );
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings'))->with(
                'error',
                $e->getMessage() . ' If webhook permission is missing, reconnect Arattai once and try again.'
            );
        }
    }

    public function sendArattaiPreview()
    {
        $userId = trim((string) $this->request->getPost('arattai_user_id'));
        try {
            $result = (new AttraiIntegrationService())->sendTemplatePreview($userId);
            $flash = ($result['status'] ?? '') === 'sent' ? 'success' : 'error';
            return redirect()->to(site_url('admin/company-settings') . '#arattai-workflow-preview')->with($flash, (string) ($result['message'] ?? 'Preview completed.'));
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/company-settings') . '#arattai-workflow-preview')->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function getSettingRow(): array
    {
        $row = $this->model->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }

    /** @return list<array<string,mixed>> */
    private function attraiReviewers(): array
    {
        $db = db_connect();
        if (! $db->tableExists('admin_users')) {
            return [];
        }
        $builder = $db->table('admin_users au')
            ->select('au.id, au.name, au.email')
            ->where('au.is_active', 1);
        if ($db->tableExists('user_roles') && $db->tableExists('roles')) {
            $builder->join('user_roles ur', 'ur.user_id = au.id', 'inner')
                ->join('roles r', 'r.id = ur.role_id', 'inner')
                ->whereIn('r.role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
                ->groupBy(['au.id', 'au.name', 'au.email']);
        }
        return $builder->orderBy('au.name', 'ASC')->get()->getResultArray();
    }
}
