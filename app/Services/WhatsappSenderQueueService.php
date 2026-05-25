<?php

namespace App\Services;

use App\Models\CompanySettingModel;
use App\Models\WhatsappSenderQueueModel;
use CodeIgniter\HTTP\CURLRequest;
use Throwable;

class WhatsappSenderQueueService
{
    private CompanySettingModel $companySettingModel;
    private WhatsappSenderQueueModel $queueModel;

    public function __construct()
    {
        $this->companySettingModel = new CompanySettingModel();
        $this->queueModel = new WhatsappSenderQueueModel();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function enqueue(array $data): int
    {
        $settings = $this->settings();
        $now = date('Y-m-d H:i:s');
        $recipient = $this->normalizePhone((string) ($data['recipient_number'] ?? $data['recipient_phone'] ?? $data['to'] ?? ''));
        if ($recipient === '') {
            throw new \InvalidArgumentException('WhatsApp recipient number is required.');
        }

        $messageNo = trim((string) ($data['message_no'] ?? ''));
        if ($messageNo === '') {
            $messageNo = $this->generateMessageNo();
        }

        $messageText = trim((string) ($data['message_text'] ?? $data['message'] ?? ''));
        $payloadJson = $data['payload_json'] ?? null;
        if (is_array($payloadJson)) {
            $payloadJson = json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (int) $this->queueModel->insert([
            'message_no' => $messageNo,
            'event_key' => $this->nullableString($data['event_key'] ?? null),
            'source_type' => $this->nullableString($data['source_type'] ?? null),
            'source_id' => $this->nullableInt($data['source_id'] ?? null),
            'order_id' => $this->nullableInt($data['order_id'] ?? null),
            'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
            'sender_number' => $this->nullableString($data['sender_number'] ?? $settings['whatsapp_sender_id'] ?? null),
            'recipient_number' => $recipient,
            'recipient_name' => $this->nullableString($data['recipient_name'] ?? null),
            'message_type' => $this->nullableString($data['message_type'] ?? null) ?: 'text',
            'template_name' => $this->nullableString($data['template_name'] ?? null),
            'message_text' => $messageText,
            'media_url' => $this->nullableString($data['media_url'] ?? null),
            'payload_json' => $this->nullableString($payloadJson),
            'request_payload' => $this->nullableString($data['request_payload'] ?? null),
            'status' => $this->nullableString($data['status'] ?? null) ?: 'pending',
            'attempts' => (int) ($data['attempts'] ?? 0),
            'max_attempts' => max(1, (int) ($data['max_attempts'] ?? 3)),
            'scheduled_at' => $this->nullableString($data['scheduled_at'] ?? null) ?: $now,
            'created_by' => $this->nullableInt($data['created_by'] ?? null),
        ], true);
    }

    /**
     * @return array<string,int>
     */
    public function dispatchPending(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $result = ['scanned' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        $rows = $this->queueModel
            ->groupStart()
                ->where('status', 'pending')
                ->orGroupStart()
                    ->where('status', 'failed')
                    ->where('attempts < max_attempts', null, false)
                ->groupEnd()
            ->groupEnd()
            ->groupStart()
                ->where('scheduled_at IS NULL', null, false)
                ->orWhere('scheduled_at <= NOW()', null, false)
            ->groupEnd()
            ->orderBy('scheduled_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll($limit);

        $settings = $this->settings();
        foreach ($rows as $row) {
            $result['scanned']++;
            $dispatch = $this->dispatchRow($row, $settings);
            if ($dispatch === 'sent') {
                $result['sent']++;
            } elseif ($dispatch === 'skipped') {
                $result['skipped']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $settings
     */
    private function dispatchRow(array $row, array $settings): string
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return 'skipped';
        }

        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $now = date('Y-m-d H:i:s');
        $this->queueModel->update($id, [
            'status' => 'processing',
            'attempts' => $attempts,
            'locked_at' => $now,
            'last_attempt_at' => $now,
            'error_message' => null,
        ]);

        if (! $this->isConfigured($settings)) {
            $this->markFailed($id, $attempts, (int) ($row['max_attempts'] ?? 3), 'WhatsApp API is disabled or not configured.', null, null, null);
            return 'failed';
        }

        $payload = $this->buildRequestPayload($settings, $row);
        $response = $this->sendHttpRequest($settings, $payload);
        $providerMessageId = $this->extractProviderMessageId($response['response_payload']);

        if ($response['ok']) {
            $this->queueModel->update($id, [
                'status' => 'sent',
                'request_payload' => $payload['log_payload'],
                'response_payload' => $response['response_payload'],
                'http_status_code' => $response['http_status_code'],
                'provider_message_id' => $providerMessageId,
                'sent_at' => date('Y-m-d H:i:s'),
                'failed_at' => null,
                'locked_at' => null,
                'error_message' => null,
            ]);
            return 'sent';
        }

        $this->markFailed(
            $id,
            $attempts,
            (int) ($row['max_attempts'] ?? 3),
            (string) ($response['error_message'] ?? 'WhatsApp send failed.'),
            $payload['log_payload'],
            $response['response_payload'],
            $response['http_status_code']
        );
        return 'failed';
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $row
     * @return array{request_options:array<string,mixed>,log_payload:string}
     */
    private function buildRequestPayload(array $settings, array $row): array
    {
        $body = trim((string) ($row['request_payload'] ?? ''));
        if ($body === '') {
            $variables = $this->queueVariables($settings, $row);
            $bodyTemplate = trim((string) ($settings['whatsapp_body_template'] ?? ''));
            if ($bodyTemplate !== '') {
                $body = $this->replacePlaceholders($bodyTemplate, $variables);
            } elseif (trim((string) ($row['payload_json'] ?? '')) !== '') {
                $body = (string) $row['payload_json'];
            } else {
                $body = json_encode([
                    'to' => $variables['to'],
                    'message' => $variables['message'],
                    'sender' => $variables['sender_id'] !== '' ? $variables['sender_id'] : null,
                    'message_type' => $variables['message_type'],
                    'media_url' => $variables['media_url'] !== '' ? $variables['media_url'] : null,
                    'event' => $variables['event_key'] !== '' ? $variables['event_key'] : null,
                    'reference' => $variables['message_no'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            }
        }

        return [
            'request_options' => [
                'headers' => $this->headers($settings),
                'body' => $body,
            ],
            'log_payload' => $body,
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,string>
     */
    private function headers(array $settings): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $authType = strtolower(trim((string) ($settings['whatsapp_auth_type'] ?? 'none')));
        $authHeader = trim((string) ($settings['whatsapp_auth_header'] ?? 'Authorization')) ?: 'Authorization';
        $authToken = trim((string) ($settings['whatsapp_auth_token'] ?? ''));
        if ($authToken !== '') {
            if ($authType === 'bearer') {
                $headers[$authHeader] = 'Bearer ' . $authToken;
            } elseif ($authType === 'basic') {
                $headers[$authHeader] = 'Basic ' . $authToken;
            } elseif ($authType === 'custom') {
                $headers[$authHeader] = $authToken;
            }
        }

        $extraHeaders = json_decode((string) ($settings['whatsapp_extra_headers_json'] ?? ''), true);
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $headers[$key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $settings
     * @param array{request_options:array<string,mixed>,log_payload:string} $payload
     * @return array{ok:bool,response_payload:?string,error_message:?string,http_status_code:?int}
     */
    private function sendHttpRequest(array $settings, array $payload): array
    {
        $apiUrl = trim((string) ($settings['whatsapp_api_url'] ?? ''));
        $method = strtoupper(trim((string) ($settings['whatsapp_http_method'] ?? 'POST'))) ?: 'POST';

        try {
            $http = service('curlrequest', [
                'timeout' => max(5, min(120, (int) ($settings['whatsapp_timeout_sec'] ?? 20))),
                'http_errors' => false,
            ]);
            if (! $http instanceof CURLRequest) {
                return ['ok' => false, 'response_payload' => null, 'error_message' => 'HTTP client not available.', 'http_status_code' => null];
            }

            $response = $http->request($method, $apiUrl, $payload['request_options']);
            $body = (string) $response->getBody();
            $statusCode = (int) $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return ['ok' => true, 'response_payload' => $body !== '' ? $body : null, 'error_message' => null, 'http_status_code' => $statusCode];
            }

            return [
                'ok' => false,
                'response_payload' => $body !== '' ? $body : null,
                'error_message' => $body !== '' ? $body : ('HTTP ' . $statusCode),
                'http_status_code' => $statusCode,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'response_payload' => null, 'error_message' => $e->getMessage(), 'http_status_code' => null];
        }
    }

    private function markFailed(int $id, int $attempts, int $maxAttempts, string $error, ?string $requestPayload, ?string $responsePayload, ?int $httpStatusCode): void
    {
        $final = $attempts >= max(1, $maxAttempts);
        $this->queueModel->update($id, [
            'status' => $final ? 'failed' : 'pending',
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'http_status_code' => $httpStatusCode,
            'failed_at' => $final ? date('Y-m-d H:i:s') : null,
            'locked_at' => null,
            'error_message' => $error,
        ]);
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private function queueVariables(array $settings, array $row): array
    {
        $variables = [];
        foreach ($row as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $variables[(string) $key] = trim((string) ($value ?? ''));
            }
        }

        $variables['to'] = $this->normalizePhone((string) ($row['recipient_number'] ?? ''));
        $variables['sender_id'] = trim((string) (($row['sender_number'] ?? '') ?: ($settings['whatsapp_sender_id'] ?? '')));
        $variables['message'] = trim((string) ($row['message_text'] ?? ''));
        $variables['company_name'] = trim((string) ($settings['company_name'] ?? ''));
        $variables['media_url'] = trim((string) ($row['media_url'] ?? ''));
        $variables['message_type'] = trim((string) ($row['message_type'] ?? 'text')) ?: 'text';

        foreach ($variables as $key => $value) {
            $variables[$key . '_json'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
        }

        return $variables;
    }

    /**
     * @param array<string,string> $variables
     */
    private function replacePlaceholders(string $template, array $variables): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($variables): string {
            return array_key_exists($matches[1], $variables) ? (string) $variables[$matches[1]] : '';
        }, $template) ?? $template;
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        return $this->companySettingModel->orderBy('id', 'ASC')->first() ?? [];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function isConfigured(array $settings): bool
    {
        return ! empty($settings['whatsapp_enabled']) && trim((string) ($settings['whatsapp_api_url'] ?? '')) !== '';
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }

    private function generateMessageNo(): string
    {
        return 'WAQ-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) ($value ?? 0);
        return $int > 0 ? $int : null;
    }

    private function extractProviderMessageId(?string $responsePayload): ?string
    {
        if ($responsePayload === null || trim($responsePayload) === '') {
            return null;
        }
        $decoded = json_decode($responsePayload, true);
        if (! is_array($decoded)) {
            return null;
        }
        foreach (['message_id', 'messageId', 'id', 'wamid', 'sid'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                return (string) $decoded[$key];
            }
        }
        return null;
    }
}
