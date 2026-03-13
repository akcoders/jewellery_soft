<?php

namespace App\Services;

use App\Models\AdminUserModel;
use App\Models\CompanySettingModel;
use App\Models\MobileTaskModel;
use App\Models\MobilePushNotificationModel;
use CodeIgniter\HTTP\CURLRequest;
use Throwable;

class MobilePushService
{
    private CompanySettingModel $companySettingModel;
    private MobilePushNotificationModel $notificationModel;
    private AdminUserModel $adminUserModel;
    private MobileTaskModel $taskModel;
    private CURLRequest $http;

    public function __construct()
    {
        $this->companySettingModel = new CompanySettingModel();
        $this->notificationModel = new MobilePushNotificationModel();
        $this->adminUserModel = new AdminUserModel();
        $this->taskModel = new MobileTaskModel();
        $this->http = service('curlrequest', [
            'baseURI' => 'https://api.onesignal.com',
            'timeout' => 20,
            'http_errors' => false,
        ]);
    }

    public function queueForAdmin(int $adminUserId, array $notification): array
    {
        $admin = $this->adminUserModel->find($adminUserId);
        if (! is_array($admin)) {
            return ['queued' => false, 'message' => 'Admin user not found.'];
        }

        return $this->queueForAdminRow($admin, $notification);
    }

    public function queueForAdminRow(array $admin, array $notification): array
    {
        $externalId = $this->externalIdForAdmin($admin);
        if ($externalId === null) {
            return ['queued' => false, 'message' => 'Mobile user identity is not available for push.'];
        }

        $scheduledAt = $this->normalizeDateTime($notification['scheduled_at'] ?? null);
        $payload = $notification['payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $insertId = (int) $this->notificationModel->insert([
            'admin_user_id' => (int) ($admin['id'] ?? 0),
            'external_user_id' => $externalId,
            'type' => trim((string) ($notification['type'] ?? 'general')) ?: 'general',
            'reference_table' => trim((string) ($notification['reference_table'] ?? '')) ?: null,
            'reference_id' => (int) ($notification['reference_id'] ?? 0) > 0 ? (int) ($notification['reference_id'] ?? 0) : null,
            'title' => trim((string) ($notification['title'] ?? 'Notification')) ?: 'Notification',
            'message' => trim((string) ($notification['message'] ?? '')) ?: 'Notification',
            'payload_json' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'done_flag' => 0,
        ], true);

        if ($insertId <= 0) {
            return ['queued' => false, 'message' => 'Could not create push notification row.'];
        }

        if (! $this->isConfigured($this->settings())) {
            return [
                'queued' => true,
                'status' => 'pending',
                'notification_id' => $insertId,
                'message' => 'Push queued. OneSignal is not configured yet.',
            ];
        }

        return $this->dispatch($insertId);
    }

    public function dispatch(int $notificationId): array
    {
        $row = $this->notificationModel->find($notificationId);
        if (! is_array($row)) {
            return ['queued' => false, 'message' => 'Push notification row not found.'];
        }

        if ((int) ($row['done_flag'] ?? 0) === 1 || in_array((string) ($row['status'] ?? ''), ['done', 'cancelled'], true)) {
            return ['queued' => false, 'message' => 'Push notification is already completed.'];
        }

        $config = $this->settings();
        if (! $this->isConfigured($config)) {
            $message = 'OneSignal is not configured in company settings.';
            $this->notificationModel->update($notificationId, [
                'status' => 'failed',
                'error_message' => $message,
            ]);
            return ['queued' => false, 'message' => $message];
        }

        $payload = [
            'app_id' => (string) $config['onesignal_app_id'],
            'include_aliases' => [
                'external_id' => [(string) ($row['external_user_id'] ?? '')],
            ],
            'target_channel' => 'push',
            'headings' => ['en' => (string) ($row['title'] ?? 'Notification')],
            'contents' => ['en' => (string) ($row['message'] ?? '')],
            'data' => $this->decodedPayload($row['payload_json'] ?? null),
            'small_icon' => 'ic_stat_onesignal_default',
            'android_sound' => 'aabhushan_alert',
        ];

        $scheduledAt = $this->normalizeDateTime($row['scheduled_at'] ?? null);
        if ($scheduledAt !== null && strtotime($scheduledAt) !== false && strtotime($scheduledAt) > time()) {
            $payload['send_after'] = gmdate('c', strtotime($scheduledAt));
        }

        try {
            $response = $this->http->post('/notifications?c=push', [
                'headers' => [
                    'Authorization' => 'Key ' . trim((string) $config['onesignal_rest_api_key']),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = (int) $response->getStatusCode();
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);
            $messageId = is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
            $errors = is_array($decoded) ? ($decoded['errors'] ?? null) : null;

            if ($statusCode >= 200 && $statusCode < 300 && $messageId !== '') {
                $status = isset($payload['send_after']) ? 'queued' : 'sent';
                $this->notificationModel->update($notificationId, [
                    'status' => $status,
                    'sent_at' => date('Y-m-d H:i:s'),
                    'onesignal_message_id' => $messageId,
                    'error_message' => null,
                    'response_json' => $body !== '' ? $body : null,
                ]);

                return [
                    'queued' => true,
                    'status' => $status,
                    'notification_id' => $notificationId,
                    'onesignal_message_id' => $messageId,
                ];
            }

            $errorMessage = 'OneSignal request failed.';
            if (is_string($errors) && trim($errors) !== '') {
                $errorMessage = $errors;
            } elseif (is_array($errors) && $errors !== []) {
                $errorMessage = json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $errorMessage;
            } elseif ($body !== '') {
                $errorMessage = $body;
            }

            $this->notificationModel->update($notificationId, [
                'status' => 'failed',
                'error_message' => $errorMessage,
                'response_json' => $body !== '' ? $body : null,
            ]);

            return [
                'queued' => false,
                'status' => 'failed',
                'notification_id' => $notificationId,
                'message' => $errorMessage,
            ];
        } catch (Throwable $e) {
            $this->notificationModel->update($notificationId, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'queued' => false,
                'status' => 'failed',
                'notification_id' => $notificationId,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function cancelByReference(string $referenceTable, int $referenceId): void
    {
        if ($referenceId <= 0 || trim($referenceTable) === '') {
            return;
        }

        $rows = $this->notificationModel
            ->where('reference_table', $referenceTable)
            ->where('reference_id', $referenceId)
            ->whereIn('status', ['pending', 'queued'])
            ->findAll();

        foreach ($rows as $row) {
            $this->cancelOneSignalMessage($row);
            $this->notificationModel->update((int) ($row['id'] ?? 0), [
                'status' => 'cancelled',
                'done_flag' => 1,
                'done_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function markDone(int $notificationId, ?int $adminUserId = null): array
    {
        $row = $this->notificationModel->find($notificationId);
        if (! is_array($row)) {
            return ['ok' => false, 'message' => 'Notification not found.'];
        }

        if ($adminUserId !== null && (int) ($row['admin_user_id'] ?? 0) !== $adminUserId) {
            return ['ok' => false, 'message' => 'Notification does not belong to this user.'];
        }

        if ((int) ($row['done_flag'] ?? 0) === 1) {
            return ['ok' => true];
        }

        if (in_array((string) ($row['status'] ?? ''), ['queued', 'pending', 'failed'], true)) {
            $this->cancelOneSignalMessage($row);
        }

        $this->notificationModel->update($notificationId, [
            'done_flag' => 1,
            'done_at' => date('Y-m-d H:i:s'),
            'status' => 'done',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ((string) ($row['reference_table'] ?? '') === 'mobile_tasks' && (int) ($row['reference_id'] ?? 0) > 0) {
            $this->taskModel->update((int) $row['reference_id'], [
                'is_done' => 1,
                'status' => 'done',
            ]);
        }

        return ['ok' => true];
    }

    public function dispatchPendingNotifications(int $limit = 200): array
    {
        $limit = max(1, $limit);
        $rows = $this->notificationModel
            ->where('done_flag', 0)
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('scheduled_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll($limit);

        $result = [
            'scanned' => count($rows),
            'sent' => 0,
            'queued' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $dispatch = $this->dispatch((int) ($row['id'] ?? 0));
            if (! ($dispatch['queued'] ?? false)) {
                $result['failed']++;
                continue;
            }

            if (($dispatch['status'] ?? '') === 'queued') {
                $result['queued']++;
            } elseif (($dispatch['status'] ?? '') === 'sent') {
                $result['sent']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    public function settings(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'DESC')->first();
        return is_array($row) ? $row : [];
    }

    public function externalIdForAdmin(array $admin): ?string
    {
        $email = strtolower(trim((string) ($admin['email'] ?? '')));
        if ($email !== '') {
            return $email;
        }

        $name = strtolower(trim((string) ($admin['name'] ?? '')));
        $name = preg_replace('/\s+/', '_', $name ?? '');
        if ($name === null || $name === '') {
            return null;
        }

        if (in_array($name, ['na', 'null', '0', '1', '-1', 'all', 'nan', '-', 'none', 'ok', '123abc', 'unknown', 'invalid_user', 'undefined', 'not_set', 'unqualified', '00000000-0000-0000-0000-000000000000'], true)) {
            return null;
        }

        return $name;
    }

    private function isConfigured(array $config): bool
    {
        return (int) ($config['onesignal_enabled'] ?? 0) === 1
            && trim((string) ($config['onesignal_app_id'] ?? '')) !== ''
            && trim((string) ($config['onesignal_rest_api_key'] ?? '')) !== '';
    }

    private function decodedPayload($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeDateTime($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function cancelOneSignalMessage(array $row): void
    {
        $config = $this->settings();
        $messageId = trim((string) ($row['onesignal_message_id'] ?? ''));
        if (! $this->isConfigured($config) || $messageId === '') {
            return;
        }

        try {
            $this->http->request('DELETE', '/notifications/' . rawurlencode($messageId), [
                'query' => ['app_id' => (string) $config['onesignal_app_id']],
                'headers' => [
                    'Authorization' => 'Key ' . trim((string) $config['onesignal_rest_api_key']),
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (Throwable $e) {
        }
    }
}
