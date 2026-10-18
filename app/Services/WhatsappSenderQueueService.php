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
        $requestPayload = $data['request_payload'] ?? null;
        if (is_array($requestPayload)) {
            $requestPayload = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return (int) $this->queueModel->insert([
            'message_no' => $messageNo,
            'event_key' => $this->nullableString($data['event_key'] ?? null),
            'provider_key' => $this->nullableString($data['provider_key'] ?? null) ?: 'whatsapp',
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
            'request_payload' => $this->nullableString($requestPayload),
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
     * Dispatch one known queue row immediately. This is used by admin previews
     * and webhook replies without accidentally sending another pending item.
     *
     * @return array{status:string,queue_id:int,error_message?:?string,http_status_code?:?int,response_payload?:?string}
     */
    public function dispatchById(int $id): array
    {
        $row = $id > 0 ? $this->queueModel->find($id) : null;
        if (! is_array($row)) {
            throw new \RuntimeException('WhatsApp queue message not found.');
        }
        if ((string) ($row['status'] ?? '') === 'sent') {
            return ['status' => 'sent', 'queue_id' => $id];
        }

        $status = $this->dispatchRow($row, $this->settings());
        $fresh = $this->queueModel->find($id) ?? [];
        return [
            'status' => $status,
            'queue_id' => $id,
            'error_message' => $fresh['error_message'] ?? null,
            'http_status_code' => isset($fresh['http_status_code']) ? (int) $fresh['http_status_code'] : null,
            'response_payload' => $fresh['response_payload'] ?? null,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function applyArattaiDeliveryEvent(string $event, array $payload): array
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $ackKey = trim((string) ($entity['ack_key'] ?? $payload['ack_key'] ?? ''));
        $providerMessageId = trim((string) ($entity['id'] ?? $payload['id'] ?? ''));
        if ($ackKey === '' && $providerMessageId === '') {
            return ['status' => 'ignored', 'message' => 'Delivery event has no ack_key or message ID.'];
        }

        $builder = $this->queueModel->where('provider_key', 'attrai');
        if ($ackKey !== '') {
            $builder->where('message_no', $ackKey);
        } else {
            $builder->where('provider_message_id', $providerMessageId);
        }
        $row = $builder->orderBy('id', 'DESC')->first();
        if (! is_array($row)) {
            return ['status' => 'unmatched', 'message' => 'No queued Arattai message matches this delivery event.'];
        }

        $deliveryStatus = match ($event) {
            'chat.message.sent' => 'sent',
            'chat.message.delivered' => 'delivered',
            'chat.message.failed' => 'failed',
            'chat.message.deleted' => 'deleted',
            default => '',
        };
        if ($deliveryStatus === '') {
            return ['status' => 'ignored', 'message' => 'Webhook is not an outbound delivery event.'];
        }

        $reason = null;
        if ($deliveryStatus === 'failed') {
            foreach (['message', 'reason', 'error', 'code'] as $key) {
                if (isset($entity[$key]) && is_scalar($entity[$key]) && trim((string) $entity[$key]) !== '') {
                    $reason = trim((string) $entity[$key]);
                    break;
                }
            }
            $reason ??= 'Arattai reported that the message could not be delivered.';
        }
        $updates = [
            'delivery_status' => $deliveryStatus,
            'delivery_updated_at' => date('Y-m-d H:i:s'),
            'provider_failure_reason' => $reason,
        ];
        if ($providerMessageId !== '') {
            $updates['provider_message_id'] = $providerMessageId;
        }
        $this->queueModel->update((int) $row['id'], $updates);

        return [
            'status' => 'updated',
            'message' => 'Arattai delivery status updated.',
            'queue_id' => (int) $row['id'],
            'delivery_status' => $deliveryStatus,
        ];
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

        $provider = strtolower(trim((string) ($row['provider_key'] ?? 'whatsapp')));
        try {
            $settings = $this->providerSettings($settings, $provider, $row);
        } catch (Throwable $e) {
            $this->markFailed($id, $attempts, (int) ($row['max_attempts'] ?? 3), $e->getMessage(), null, null, null);
            return 'failed';
        }
        if (! $this->isConfigured($settings)) {
            $this->markFailed($id, $attempts, (int) ($row['max_attempts'] ?? 3), ucfirst($provider) . ' API is disabled or not configured.', null, null, null);
            return 'failed';
        }

        try {
            $payload = $this->buildRequestPayload($settings, $row);
            $response = $this->sendHttpRequest($settings, $payload);
        } catch (Throwable $e) {
            $this->markFailed($id, $attempts, (int) ($row['max_attempts'] ?? 3), $e->getMessage(), null, null, null);
            return 'failed';
        }
        $providerMessageId = $this->extractProviderMessageId($response['response_payload']);

        if ($response['ok']) {
            $this->queueModel->update($id, [
                'status' => 'sent',
                'request_payload' => $payload['log_payload'],
                'response_payload' => $response['response_payload'],
                'http_status_code' => $response['http_status_code'],
                'provider_message_id' => $providerMessageId,
                'delivery_status' => 'accepted',
                'delivery_updated_at' => date('Y-m-d H:i:s'),
                'provider_failure_reason' => null,
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
        if ($body !== '' && ! empty($settings['attrai_official'])) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && in_array((string) ($decoded['category'] ?? ''), ['display', 'input'], true)) {
                $decoded['ack_key'] = $this->arattaiAckKey((string) ($row['message_no'] ?? ''), (int) ($row['id'] ?? 0));
                $body = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }
        if ($body === '') {
            if (! empty($settings['attrai_official'])) {
                $body = $this->buildArattaiTransactionBody($settings, $row);
            }
            $variables = $this->queueVariables($settings, $row);
            $bodyTemplate = trim((string) ($settings['whatsapp_body_template'] ?? ''));
            if ($body !== '') {
                // Official Arattai payload was built above.
            } elseif ($bodyTemplate !== '') {
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
            'delivery_status' => 'failed',
            'delivery_updated_at' => date('Y-m-d H:i:s'),
            'provider_failure_reason' => $this->providerErrorReason($error, $responsePayload),
        ]);
    }

    private function arattaiAckKey(string $messageNo, int $id): string
    {
        $ackKey = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($messageNo)) ?: ('ARA_' . $id);
        return substr($ackKey, 0, 64);
    }

    private function providerErrorReason(string $error, ?string $responsePayload): string
    {
        $decoded = json_decode((string) $responsePayload, true);
        if (is_array($decoded)) {
            $code = trim((string) ($decoded['code'] ?? ''));
            $message = trim((string) ($decoded['message'] ?? ''));
            if ($code !== '' || $message !== '') {
                return trim(($code !== '' ? $code . ': ' : '') . $message);
            }
        }
        return mb_substr(trim($error) ?: 'Arattai request failed.', 0, 1000);
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

    /** @param array<string,mixed> $settings @param array<string,mixed> $row @return array<string,mixed> */
    private function providerSettings(array $settings, string $provider, array $row = []): array
    {
        if (strtolower(trim($provider)) !== 'attrai') {
            return $settings;
        }

        $companyId = trim((string) ($settings['attrai_company_id'] ?? ''));
        $businessNumberId = trim((string) ($settings['attrai_business_number_id'] ?? ''));
        if ($companyId === '' || $businessNumberId === '') {
            throw new \RuntimeException('Connect Arattai first; Company ID or Business Number ID is missing.');
        }
        $settings['attrai_official'] = 1;
        $settings['whatsapp_enabled'] = (int) ($settings['attrai_enabled'] ?? 0);
        $requestPayload = json_decode(trim((string) ($row['request_payload'] ?? '')), true);
        $isConversationPayload = is_array($requestPayload)
            && in_array((string) ($requestPayload['category'] ?? ''), ['display', 'input'], true);
        $settings['whatsapp_api_url'] = ArattaiOAuthService::API_ROOT
            . ($isConversationPayload || (string) ($row['event_key'] ?? '') === 'approval_requested'
                ? '/messages'
                : '/transactions');
        $settings['whatsapp_http_method'] = 'POST';
        $settings['whatsapp_auth_type'] = 'bearer';
        $settings['whatsapp_auth_header'] = 'Authorization';
        $settings['whatsapp_auth_token'] = (new ArattaiOAuthService())->accessToken();
        $settings['whatsapp_sender_id'] = $businessNumberId;
        $settings['whatsapp_extra_headers_json'] = json_encode([
            'Arattai-CompanyId' => $companyId,
            'Arattai-BusinessNumberId' => $businessNumberId,
        ], JSON_UNESCAPED_SLASHES);
        $settings['whatsapp_body_template'] = null;
        return $settings;
    }

    /** @param array<string,mixed> $settings @param array<string,mixed> $row */
    private function buildArattaiTransactionBody(array $settings, array $row): string
    {
        $templateId = trim((string) ($settings['attrai_update_template_id'] ?? ''));
        if ($templateId === '') {
            throw new \RuntimeException('Create the Arattai ERP order-update template first.');
        }
        $recipient = $this->arattaiPhoneNumber((string) ($row['recipient_number'] ?? ''));
        if ($recipient === '') {
            throw new \RuntimeException('Arattai recipient phone number is invalid.');
        }
        $orderNo = '';
        $orderId = (int) ($row['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = db_connect()->table('orders')->select('order_no')->where('id', $orderId)->get()->getRowArray();
            $orderNo = trim((string) ($order['order_no'] ?? ''));
        }
        if ($orderNo === '') {
            $orderNo = $orderId > 0 ? ('#' . $orderId) : trim((string) ($row['source_id'] ?? 'ERP'));
        }
        $ackKey = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($row['message_no'] ?? '')) ?: ('ERP_' . (int) ($row['id'] ?? 0));

        return json_encode([
            'id' => $templateId,
            'phone_number' => $recipient,
            'language' => 'en',
            'variables' => [
                'recipient_name' => trim((string) ($row['recipient_name'] ?? '')) ?: 'User',
                'order_no' => $orderNo,
                'update_message' => mb_substr(trim((string) ($row['message_text'] ?? 'ERP update')), 0, 1000),
            ],
            'ack_key' => substr($ackKey, 0, 64),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function arattaiPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '91-' . substr($digits, 2);
        }
        return '';
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
        foreach (['message_id', 'messageId', 'id', 'wamid', 'sid', 'ack_key'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                return (string) $decoded[$key];
            }
        }
        if (is_array($decoded['data'] ?? null)) {
            foreach (['id', 'ack_key', 'message_id'] as $key) {
                if (isset($decoded['data'][$key]) && is_scalar($decoded['data'][$key])) {
                    return (string) $decoded['data'][$key];
                }
            }
        }
        return null;
    }
}
