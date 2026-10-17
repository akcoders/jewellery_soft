<?php

namespace App\Services;

use App\Models\CompanySettingModel;
use CodeIgniter\HTTP\CURLRequest;
use RuntimeException;

class ArattaiOAuthService
{
    public const API_ROOT = 'https://business.arattai.in/api/v1';
    public const AUTH_URL = 'https://accounts.arattai.in/oauth/v2/auth';
    public const TOKEN_URL = 'https://accounts.arattai.in/oauth/v2/token';

    private const SCOPES = [
        'ArattaiBusiness.messages.CREATE',
        'ArattaiBusiness.companies.READ',
        'ArattaiBusiness.businesses.READ',
        'ArattaiBusiness.phonenumbers.READ',
        'ArattaiBusiness.messagetemplates.READ',
        'ArattaiBusiness.messagetemplates.CREATE',
        'ArattaiBusiness.messagetemplates.UPDATE',
        'ArattaiBusiness.webhooks.READ',
        'ArattaiBusiness.webhooks.CREATE',
        'ArattaiBusiness.webhooks.UPDATE',
    ];

    public function __construct(private ?CompanySettingModel $settingsModel = null)
    {
        $this->settingsModel ??= new CompanySettingModel();
    }

    public function redirectUri(): string
    {
        return site_url('admin/company-settings/arattai/oauth/callback');
    }

    public function authorizationUrl(string $state): string
    {
        $settings = $this->settings();
        $clientId = trim((string) ($settings['attrai_client_id'] ?? ''));
        $this->requireValue($clientId, 'Save the Arattai Client ID first.');

        return self::AUTH_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'scope' => implode(',', self::SCOPES),
            'redirect_uri' => $this->redirectUri(),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'tenant' => 'ArattaiBusiness',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string,mixed> */
    public function connectFromAuthorizationCode(string $code): array
    {
        $settings = $this->settings();
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $this->requireValue(trim($code), 'Arattai authorization code is missing.'),
            'client_id' => $this->requireValue(trim((string) ($settings['attrai_client_id'] ?? '')), 'Arattai Client ID is missing.'),
            'client_secret' => $this->requireValue(trim((string) ($settings['attrai_client_secret'] ?? '')), 'Arattai Client Secret is missing.'),
            'redirect_uri' => $this->redirectUri(),
        ]);

        $this->storeTokens($settings, $tokens);
        return $this->discoverAndSaveAccountIds();
    }

    public function accessToken(): string
    {
        $settings = $this->settings();
        $token = trim((string) ($settings['attrai_access_token'] ?? ''));
        $expiresAt = strtotime((string) ($settings['attrai_token_expires_at'] ?? '')) ?: 0;
        if ($token !== '' && $expiresAt > time() + 120) {
            return $token;
        }

        $refreshToken = trim((string) ($settings['attrai_refresh_token'] ?? ''));
        $this->requireValue($refreshToken, 'Connect Arattai from Company Settings first.');
        $tokens = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->requireValue(trim((string) ($settings['attrai_client_id'] ?? '')), 'Arattai Client ID is missing.'),
            'client_secret' => $this->requireValue(trim((string) ($settings['attrai_client_secret'] ?? '')), 'Arattai Client Secret is missing.'),
        ]);
        $this->storeTokens($settings, $tokens);
        return trim((string) ($tokens['access_token'] ?? ''));
    }

    /** @return array<string,mixed> */
    public function discoverAndSaveAccountIds(): array
    {
        $companies = $this->apiRequest('GET', '/companies', null, false);
        $company = $this->firstDataRow($companies, 'No Arattai company is linked to this OAuth account.');
        $companyId = trim((string) ($company['id'] ?? ''));
        $this->requireValue($companyId, 'Arattai Company ID was not returned.');

        $businesses = $this->apiRequest('GET', '/businesses', null, true, $companyId);
        $business = $this->firstDataRow($businesses, 'No Arattai business was found for this company.');
        $businessId = trim((string) ($business['id'] ?? ''));
        $this->requireValue($businessId, 'Arattai Business ID was not returned.');

        $numbers = $this->apiRequest('GET', '/businesses/' . rawurlencode($businessId) . '/businessnumbers', null, true, $companyId);
        $number = $this->firstDataRow($numbers, 'No Arattai business number was found.');
        $businessNumberId = trim((string) ($number['id'] ?? ''));
        $this->requireValue($businessNumberId, 'Arattai Business Number ID was not returned.');

        $settings = $this->settings();
        $this->settingsModel->update((int) $settings['id'], [
            'attrai_company_id' => $companyId,
            'attrai_business_id' => $businessId,
            'attrai_business_number_id' => $businessNumberId,
            'attrai_sender_id' => $businessNumberId,
            'attrai_api_url' => self::API_ROOT . '/transactions',
            'attrai_http_method' => 'POST',
            'attrai_auth_type' => 'bearer',
            'attrai_auth_header' => 'Authorization',
            'attrai_connected_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'company_id' => $companyId,
            'business_id' => $businessId,
            'business_number_id' => $businessNumberId,
        ];
    }

    /** @return array<string,array<string,string>> */
    public function createAndSubmitDefaultTemplates(): array
    {
        $settings = $this->settings();
        $companyId = $this->requireValue(trim((string) ($settings['attrai_company_id'] ?? '')), 'Connect Arattai first; Company ID is missing.');
        $businessId = $this->requireValue(trim((string) ($settings['attrai_business_id'] ?? '')), 'Connect Arattai first; Business ID is missing.');
        $existingResponse = $this->apiRequest(
            'GET',
            '/businesses/' . rawurlencode($businessId) . '/messagetemplates',
            null,
            true,
            $companyId
        );
        $existing = is_array($existingResponse['data'] ?? null) ? $existingResponse['data'] : [];

        $definitions = $this->defaultTemplateDefinitions();
        $saved = [];
        foreach ($definitions as $key => $definition) {
            $template = $this->findTemplateByName($existing, (string) $definition['name']);
            if ($template === null) {
                $created = $this->apiRequest(
                    'POST',
                    '/businesses/' . rawurlencode($businessId) . '/messagetemplates',
                    $definition,
                    true,
                    $companyId
                );
                $template = is_array($created['data'] ?? null) ? $created['data'] : [];
            }

            $templateId = $this->requireValue(trim((string) ($template['id'] ?? '')), 'Arattai did not return the created template ID.');
            $status = $this->templateStatus($template);
            if ($status === 'draft') {
                $submitted = $this->apiRequest(
                    'POST',
                    '/messagetemplates/' . rawurlencode($templateId) . '/submit',
                    ['languages' => ['en']],
                    true,
                    $companyId
                );
                $submittedTemplate = is_array($submitted['data'] ?? null) ? $submitted['data'] : $submitted;
                $status = $this->templateStatus(is_array($submittedTemplate) ? $submittedTemplate : []);
            }
            $saved[$key] = ['id' => $templateId, 'status' => $status !== '' ? $status : 'created'];
        }

        $settings = $this->settings();
        $this->settingsModel->update((int) $settings['id'], [
            'attrai_approval_template_id' => $saved['approval']['id'],
            'attrai_approval_template_status' => $saved['approval']['status'],
            'attrai_update_template_id' => $saved['update']['id'],
            'attrai_update_template_status' => $saved['update']['status'],
        ]);
        return $saved;
    }

    /** @return array{webhook_id:string,status:string,url:string,events:list<string>,secret_selected:bool} */
    public function configureConversationWebhook(): array
    {
        $settings = $this->settings();
        $companyId = $this->requireValue(trim((string) ($settings['attrai_company_id'] ?? '')), 'Connect Arattai first; Company ID is missing.');
        $numberId = $this->requireValue(trim((string) ($settings['attrai_business_number_id'] ?? '')), 'Connect Arattai first; Business Number ID is missing.');
        $endpoint = site_url('integrations/attrai/webhook');

        $this->apiRequest('POST', '/webhookverification', ['url' => $endpoint], true, $companyId);
        $listed = $this->apiRequest(
            'GET',
            '/businessnumbers/' . rawurlencode($numberId) . '/webhooks',
            null,
            true,
            $companyId
        );
        $webhook = null;
        foreach ((array) ($listed['data'] ?? []) as $candidate) {
            if (is_array($candidate) && (string) ($candidate['type'] ?? '') === 'conversation') {
                $webhook = $candidate;
                break;
            }
        }

        $definition = [
            'events' => ['chat.message.received'],
            'type' => 'conversation',
            'url' => $endpoint,
            'description' => 'Aabhushan ERP incoming messages and approval actions',
        ];
        if (! is_array($webhook)) {
            $created = $this->apiRequest(
                'POST',
                '/businessnumbers/' . rawurlencode($numberId) . '/webhooks',
                $definition,
                true,
                $companyId
            );
            $webhook = is_array($created['data'] ?? null) ? $created['data'] : [];
        } else {
            $webhookId = $this->requireValue(trim((string) ($webhook['id'] ?? '')), 'Arattai webhook ID was not returned.');
            $updated = $this->apiRequest('PATCH', '/webhooks/' . rawurlencode($webhookId), [
                'events' => ['chat.message.received'],
                'url' => $endpoint,
                'status' => 'enabled',
                'description' => $definition['description'],
            ], true, $companyId);
            $webhook = is_array($updated['data'] ?? null) ? $updated['data'] : ($updated !== [] ? $updated : $webhook);
        }

        $webhookId = $this->requireValue(trim((string) ($webhook['id'] ?? '')), 'Arattai webhook ID was not returned.');
        $keysResponse = $this->apiRequest('GET', '/webhooks/' . rawurlencode($webhookId) . '/keys', null, true, $companyId);
        $keys = is_array($keysResponse['data'] ?? null) ? $keysResponse['data'] : [];
        $selectedKey = null;
        foreach ($keys as $key) {
            if (is_array($key) && ! empty($key['selected'])) {
                $selectedKey = $key;
                break;
            }
        }
        if (! is_array($selectedKey)) {
            if ($keys === []) {
                $createdKeyResponse = $this->apiRequest('POST', '/webhooks/' . rawurlencode($webhookId) . '/keys', null, true, $companyId);
                $selectedKey = is_array($createdKeyResponse['data'] ?? null) ? $createdKeyResponse['data'] : [];
            } else {
                $selectedKey = is_array($keys[0] ?? null) ? $keys[0] : [];
            }
            $keyId = $this->requireValue(trim((string) ($selectedKey['id'] ?? '')), 'Arattai webhook key ID was not returned.');
            $this->apiRequest('PATCH', '/webhooks/' . rawurlencode($webhookId) . '/keys/' . rawurlencode($keyId), [
                'selected' => true,
            ], true, $companyId);
            $selectedKey['selected'] = true;
        }

        $secret = trim((string) ($selectedKey['key'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('Arattai selected webhook key value was not returned. Select a key in Arattai and paste it in Company Settings.');
        }
        $settings = $this->settings();
        $this->settingsModel->update((int) $settings['id'], [
            'attrai_webhook_id' => $webhookId,
            'attrai_webhook_status' => 'enabled',
            'attrai_webhook_secret' => $secret,
        ]);

        return [
            'webhook_id' => $webhookId,
            'status' => 'enabled',
            'url' => $endpoint,
            'events' => ['chat.message.received'],
            'secret_selected' => true,
        ];
    }

    /**
     * @param array<string,mixed>|null $json
     * @return array<string,mixed>
     */
    public function apiRequest(
        string $method,
        string $path,
        ?array $json = null,
        bool $companyHeader = true,
        ?string $companyId = null
    ): array {
        $settings = $this->settings();
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->accessToken(),
        ];
        if ($companyHeader) {
            $companyId ??= trim((string) ($settings['attrai_company_id'] ?? ''));
            $headers['Arattai-CompanyId'] = $this->requireValue($companyId, 'Arattai Company ID is missing.');
        }
        $options = ['headers' => $headers, 'http_errors' => false];
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $options['headers'] = $headers;
            $options['body'] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        return $this->request($method, self::API_ROOT . '/' . ltrim($path, '/'), $options);
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    private function tokenRequest(array $params): array
    {
        return $this->request('POST', self::TOKEN_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), [
            'headers' => ['Accept' => 'application/json'],
            'http_errors' => false,
        ]);
    }

    /** @param array<string,mixed> $options @return array<string,mixed> */
    private function request(string $method, string $url, array $options): array
    {
        $http = service('curlrequest', ['timeout' => 30, 'http_errors' => false]);
        if (! $http instanceof CURLRequest) {
            throw new RuntimeException('Arattai HTTP client is unavailable.');
        }
        $response = $http->request(strtoupper($method), $url, $options);
        $status = (int) $response->getStatusCode();
        $body = trim((string) $response->getBody());
        $decoded = $body !== '' ? json_decode($body, true) : [];
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? trim((string) ($decoded['message'] ?? $decoded['code'] ?? '')) : '';
            throw new RuntimeException($message !== '' ? $message : ('Arattai API returned HTTP ' . $status . '.'));
        }
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $settings @param array<string,mixed> $tokens */
    private function storeTokens(array $settings, array $tokens): void
    {
        $accessToken = $this->requireValue(trim((string) ($tokens['access_token'] ?? '')), 'Arattai access token was not returned.');
        $refreshToken = trim((string) ($tokens['refresh_token'] ?? ''));
        $expiresIn = max(300, (int) ($tokens['expires_in'] ?? 3600));
        $this->settingsModel->update((int) $settings['id'], [
            'attrai_access_token' => $accessToken,
            'attrai_auth_token' => $accessToken,
            'attrai_refresh_token' => $refreshToken !== '' ? $refreshToken : ($settings['attrai_refresh_token'] ?? null),
            'attrai_token_expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'attrai_api_domain' => trim((string) ($tokens['api_domain'] ?? '')) ?: null,
        ]);
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private function firstDataRow(array $response, string $message): array
    {
        $data = $response['data'] ?? null;
        if (! is_array($data) || ! isset($data[0]) || ! is_array($data[0])) {
            throw new RuntimeException($message);
        }
        return $data[0];
    }

    /** @param list<mixed> $templates @return array<string,mixed>|null */
    private function findTemplateByName(array $templates, string $name): ?array
    {
        foreach ($templates as $template) {
            if (is_array($template) && strcasecmp(trim((string) ($template['name'] ?? '')), $name) === 0) {
                return $template;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $template */
    private function templateStatus(array $template): string
    {
        foreach ((array) ($template['translations'] ?? []) as $translation) {
            if (is_array($translation) && (string) ($translation['language'] ?? '') === 'en') {
                return trim((string) ($translation['status'] ?? $translation['draft']['status'] ?? $translation['published']['status'] ?? ''));
            }
        }
        return trim((string) ($template['status'] ?? ''));
    }

    /** @return array<string,array<string,mixed>> */
    private function defaultTemplateDefinitions(): array
    {
        return [
            'approval' => [
                'type' => 'transaction',
                'language' => 'en',
                'name' => 'Aabhushan ERP approval alert',
                'description' => 'Notifies an administrator that an ERP transaction is waiting for approval.',
                'message' => [
                    'title' => 'Approval Required',
                    'body' => '{{request_label}} request #{{request_id}} requires approval. {{summary}} Open Arattai to review the available approval actions.',
                    'footer' => 'Aabhushan ERP',
                ],
                'place_holders' => [
                    ['name' => 'request_label', 'type' => 'string', 'sample_value' => 'Follow-up'],
                    ['name' => 'request_id', 'type' => 'string', 'sample_value' => '125'],
                    ['name' => 'summary', 'type' => 'string', 'sample_value' => 'Order follow-up submitted by staff'],
                ],
                'media' => ['enabled' => false],
            ],
            'update' => [
                'type' => 'transaction',
                'language' => 'en',
                'name' => 'Aabhushan ERP order update',
                'description' => 'Sends order status, assignment, readiness, delay and other ERP transaction updates.',
                'message' => [
                    'title' => 'Order Update',
                    'body' => 'Hello {{recipient_name}}, update for order {{order_no}}: {{update_message}}',
                    'footer' => 'Aabhushan ERP',
                ],
                'place_holders' => [
                    ['name' => 'recipient_name', 'type' => 'string', 'sample_value' => 'Customer'],
                    ['name' => 'order_no', 'type' => 'string', 'sample_value' => 'ORD-1001'],
                    ['name' => 'update_message', 'type' => 'string', 'sample_value' => 'Your order is ready'],
                ],
                'media' => ['enabled' => true],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        $settings = $this->settingsModel->orderBy('id', 'ASC')->first();
        if (! is_array($settings) || (int) ($settings['id'] ?? 0) <= 0) {
            throw new RuntimeException('Company settings are missing.');
        }
        return $settings;
    }

    private function requireValue(string $value, string $message): string
    {
        if ($value === '') {
            throw new RuntimeException($message);
        }
        return $value;
    }
}
