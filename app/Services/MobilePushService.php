<?php

namespace App\Services;

use App\Models\AdminUserModel;
use App\Models\CompanySettingModel;
use App\Models\MobileTaskModel;
use App\Models\MobilePushNotificationModel;
use CodeIgniter\HTTP\CURLRequest;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class MobilePushService
{
    private const TIMEZONE = 'Asia/Kolkata';
    private const CLAIM_TIMEOUT_MINUTES = 10;
    private const MOBILE_ONESIGNAL_APP_ID = '47e56c4c-5cec-4de4-a247-d1c62c1154ae';

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

        $dedupeKey = trim((string) ($notification['dedupe_key'] ?? '')) ?: null;
        $deferDispatch = filter_var($notification['defer_dispatch'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $deviceFallbackOnFailure = filter_var($notification['device_fallback_on_failure'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($dedupeKey !== null) {
            $existing = $this->notificationModel->where('dedupe_key', $dedupeKey)->first();
            if (is_array($existing)) {
                return $this->existingQueueResult($existing);
            }
        }

        $idempotencyKey = $this->uuidV4();

        try {
            $insertId = (int) $this->notificationModel->insert([
                'admin_user_id' => (int) ($admin['id'] ?? 0),
                'external_user_id' => $externalId,
                'dedupe_key' => $dedupeKey,
                'type' => trim((string) ($notification['type'] ?? 'general')) ?: 'general',
                'reference_table' => trim((string) ($notification['reference_table'] ?? '')) ?: null,
                'reference_id' => (int) ($notification['reference_id'] ?? 0) > 0 ? (int) ($notification['reference_id'] ?? 0) : null,
                'title' => trim((string) ($notification['title'] ?? 'Notification')) ?: 'Notification',
                'message' => trim((string) ($notification['message'] ?? '')) ?: 'Notification',
                'payload_json' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'scheduled_at' => $scheduledAt,
                'onesignal_idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'attempt_count' => 0,
                'done_flag' => 0,
            ], true);
        } catch (Throwable $e) {
            if ($dedupeKey !== null) {
                $existing = $this->notificationModel->where('dedupe_key', $dedupeKey)->first();
                if (is_array($existing)) {
                    return $this->existingQueueResult($existing);
                }
            }

            return ['queued' => false, 'message' => 'Could not create push notification row.'];
        }

        if ($insertId <= 0) {
            return ['queued' => false, 'message' => 'Could not create push notification row.'];
        }

        if (! $this->isConfigured($this->settings())) {
            $message = $deviceFallbackOnFailure
                ? 'OneSignal is not configured; use the device reminder.'
                : 'Push saved, but OneSignal is not configured.';
            $this->notificationModel->update($insertId, [
                'status' => $deviceFallbackOnFailure ? 'awaiting_local' : 'failed',
                'done_flag' => 0,
                'done_at' => null,
                'error_message' => $message,
                'next_attempt_at' => $deviceFallbackOnFailure ? $this->minutesFromNow(1440) : $this->minutesFromNow(5),
            ]);

            return [
                'queued' => false,
                'created' => true,
                'persisted' => true,
                'status' => $deviceFallbackOnFailure ? 'awaiting_local' : 'failed',
                'notification_id' => $insertId,
                'message' => $message,
            ];
        }

        if ($deferDispatch || $this->isFuture($scheduledAt)) {
            return [
                'queued' => true,
                'created' => true,
                'status' => 'pending',
                'notification_id' => $insertId,
                'message' => 'Push reminder saved for server-side delivery.',
            ];
        }

        return ['created' => true] + $this->dispatch($insertId);
    }

    public function dispatch(int $notificationId): array
    {
        $row = $this->notificationModel->find($notificationId);
        if (! is_array($row)) {
            return ['queued' => false, 'message' => 'Push notification row not found.'];
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === 'sent') {
            return [
                'queued' => true,
                'duplicate' => true,
                'status' => 'sent',
                'notification_id' => $notificationId,
                'message' => 'Push notification was already sent.',
            ];
        }

        if ((int) ($row['done_flag'] ?? 0) === 1 || in_array($status, ['done', 'cancelled', 'local_fallback'], true)) {
            return ['queued' => false, 'message' => 'Push notification is already completed.'];
        }

        $scheduledAt = $this->normalizeDateTime($row['scheduled_at'] ?? null);
        if ($this->isFuture($scheduledAt)) {
            return [
                'queued' => true,
                'status' => 'pending',
                'notification_id' => $notificationId,
                'message' => 'Push is not due yet.',
            ];
        }

        if (! $this->isStillRelevant($row)) {
            $this->notificationModel->update($notificationId, [
                'status' => 'cancelled',
                'done_flag' => 1,
                'done_at' => $this->nowString(),
                'error_message' => 'Notification is no longer relevant.',
            ]);

            return [
                'queued' => false,
                'status' => 'cancelled',
                'notification_id' => $notificationId,
                'message' => 'Notification is no longer relevant.',
            ];
        }

        $claim = $this->claimForDispatch($row);
        if (! ($claim['claimed'] ?? false)) {
            return [
                'queued' => false,
                'status' => (string) ($claim['status'] ?? 'skipped'),
                'notification_id' => $notificationId,
                'message' => (string) ($claim['message'] ?? 'Push is already being processed.'),
            ];
        }
        $row = $claim['row'];

        $config = $this->settings();
        if (! $this->isConfigured($config)) {
            $message = 'OneSignal is not configured in company settings.';
            $this->recordFailure($row, $message, null, 60);
            return ['queued' => false, 'status' => 'failed', 'stop_dispatch' => true, 'message' => $message];
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
            'priority' => 10,
            'idempotency_key' => (string) (($row['onesignal_idempotency_key'] ?? '') ?: $this->uuidV4()),
        ];

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

            if ($statusCode >= 200 && $statusCode < 300 && $messageId !== '' && empty($errors)) {
                $attemptCount = (int) ($row['attempt_count'] ?? 0) + 1;
                $this->notificationModel->update($notificationId, [
                    'status' => 'sent',
                    'sent_at' => $this->nowString(),
                    'onesignal_message_id' => $messageId,
                    'onesignal_idempotency_key' => (string) $payload['idempotency_key'],
                    'attempt_count' => $attemptCount,
                    'last_attempt_at' => $this->nowString(),
                    'next_attempt_at' => null,
                    'error_message' => null,
                    'response_json' => $body !== '' ? $body : null,
                ]);

                return [
                    'queued' => true,
                    'status' => 'sent',
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

            $retryMinutes = $statusCode === 429 ? $this->retryMinutes($response->getHeaderLine('Retry-After')) : null;
            $this->recordFailure($row, $errorMessage, $body !== '' ? $body : null, $retryMinutes);

            return [
                'queued' => false,
                'status' => 'failed',
                'stop_dispatch' => in_array($statusCode, [401, 403, 429], true) || $statusCode >= 500,
                'notification_id' => $notificationId,
                'message' => $errorMessage,
            ];
        } catch (Throwable $e) {
            $this->recordFailure($row, $e->getMessage());

            return [
                'queued' => false,
                'status' => 'failed',
                'stop_dispatch' => true,
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
            ->whereIn('status', ['pending', 'queued', 'failed'])
            ->findAll();

        foreach ($rows as $row) {
            $this->cancelOneSignalMessage($row);
            $this->notificationModel->update((int) ($row['id'] ?? 0), [
                'status' => 'cancelled',
                'done_flag' => 1,
                'done_at' => $this->nowString(),
                'updated_at' => $this->nowString(),
            ]);
        }
    }

    public function cancelUnsentByReferenceTypes(
        string $referenceTable,
        int $referenceId,
        array $types,
        ?string $exceptDedupeKey = null
    ): void {
        if ($referenceId <= 0 || trim($referenceTable) === '' || $types === []) {
            return;
        }

        $builder = $this->notificationModel
            ->where('reference_table', $referenceTable)
            ->where('reference_id', $referenceId)
            ->whereIn('type', array_values($types))
            ->whereIn('status', ['pending', 'queued', 'failed']);
        if ($exceptDedupeKey !== null && trim($exceptDedupeKey) !== '') {
            $builder->notLike('dedupe_key', trim($exceptDedupeKey) . ':admin:', 'after');
        }

        foreach ($builder->findAll() as $row) {
            $this->notificationModel->update((int) ($row['id'] ?? 0), [
                'status' => 'cancelled',
                'done_flag' => 1,
                'done_at' => $this->nowString(),
                'next_attempt_at' => null,
                'error_message' => 'Superseded by a newer reminder.',
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
            'done_at' => $this->nowString(),
            'status' => 'done',
            'updated_at' => $this->nowString(),
        ]);

        if ((string) ($row['reference_table'] ?? '') === 'mobile_tasks' && (int) ($row['reference_id'] ?? 0) > 0) {
            $this->taskModel->update((int) $row['reference_id'], [
                'is_done' => 1,
                'status' => 'done',
            ]);
        }

        return ['ok' => true];
    }

    public function confirmLocalFallback(int $notificationId, int $adminUserId, bool $scheduled): array
    {
        $row = $this->notificationModel
            ->where('id', $notificationId)
            ->where('admin_user_id', $adminUserId)
            ->first();
        if (! is_array($row)) {
            return ['ok' => false, 'message' => 'Notification not found.'];
        }

        if ((string) ($row['reference_table'] ?? '') !== 'mobile_tasks') {
            return ['ok' => false, 'message' => 'Local fallback is only available for task reminders.'];
        }

        $currentStatus = strtolower(trim((string) ($row['status'] ?? '')));
        if ($currentStatus === 'local_fallback') {
            return ['ok' => true, 'status' => 'local_fallback'];
        }
        if (in_array($currentStatus, ['sent', 'done', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'Notification is already finalized.'];
        }

        if ($scheduled) {
            $this->notificationModel->update($notificationId, [
                'status' => 'local_fallback',
                'done_flag' => 1,
                'done_at' => $this->nowString(),
                'next_attempt_at' => null,
                'error_message' => 'Device reminder scheduled and confirmed.',
            ]);

            return ['ok' => true, 'status' => 'local_fallback'];
        }

        $this->notificationModel->update($notificationId, [
            'status' => 'failed',
            'done_flag' => 0,
            'done_at' => null,
            'next_attempt_at' => $this->nowString(),
            'error_message' => 'Device reminder could not be scheduled; remote retry remains active.',
        ]);

        return ['ok' => true, 'status' => 'failed'];
    }

    public function dispatchPendingNotifications(int $limit = 200): array
    {
        $limit = max(1, $limit);
        if (! $this->isConfigured($this->settings())) {
            return [
                'scanned' => 0,
                'sent' => 0,
                'queued' => 0,
                'failed' => 0,
                'skipped' => 0,
                'provider_unconfigured' => true,
            ];
        }

        $now = $this->nowString();
        $staleClaim = $this->minutesFromNow(-self::CLAIM_TIMEOUT_MINUTES);
        $rows = $this->notificationModel
            ->where('done_flag', 0)
            ->groupStart()
                ->whereIn('status', ['pending', 'failed', 'awaiting_local'])
                ->orGroupStart()
                    ->where('status', 'processing')
                    ->where('last_attempt_at <=', $staleClaim)
                ->groupEnd()
            ->groupEnd()
            ->groupStart()
                ->where('scheduled_at', null)
                ->orWhere('scheduled_at <=', $now)
            ->groupEnd()
            ->groupStart()
                ->where('next_attempt_at', null)
                ->orWhere('next_attempt_at <=', $now)
            ->groupEnd()
            ->orderBy("CASE WHEN type IN ('task', 'followup', 'followup_due', 'followup_delay') THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('COALESCE(scheduled_at, created_at)', 'ASC', false)
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
                if (in_array((string) ($dispatch['status'] ?? ''), ['cancelled', 'processing', 'sent', 'skipped'], true)) {
                    $result['skipped']++;
                } else {
                    $result['failed']++;
                }
                if ($dispatch['stop_dispatch'] ?? false) {
                    break;
                }
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
        $config = is_array($row) ? $row : [];

        $envEnabled = env('onesignal.enabled');
        if ($envEnabled !== null && trim((string) $envEnabled) !== '') {
            $config['onesignal_enabled'] = filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $envAppId = trim((string) env('onesignal.appId', ''));
        if ($envAppId !== '') {
            $config['onesignal_app_id'] = $envAppId;
        }

        $envApiKey = trim((string) env('onesignal.restApiKey', ''));
        if ($envApiKey !== '') {
            $config['onesignal_rest_api_key'] = $envApiKey;
        }

        return $config;
    }

    public function configurationStatus(): array
    {
        $config = $this->settings();

        return [
            'enabled' => (int) ($config['onesignal_enabled'] ?? 0) === 1,
            'app_id_configured' => trim((string) ($config['onesignal_app_id'] ?? '')) !== '',
            'app_id_matches_mobile' => trim((string) ($config['onesignal_app_id'] ?? '')) === self::MOBILE_ONESIGNAL_APP_ID,
            'rest_api_key_configured' => trim((string) ($config['onesignal_rest_api_key'] ?? '')) !== '',
            'configured' => $this->isConfigured($config),
        ];
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
            && trim((string) ($config['onesignal_app_id'] ?? '')) === self::MOBILE_ONESIGNAL_APP_ID
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
        return $this->dateTime($value)?->format('Y-m-d H:i:s');
    }

    private function existingQueueResult(array $row): array
    {
        $status = (string) ($row['status'] ?? 'pending');
        $queued = in_array($status, ['pending', 'queued', 'sent'], true);

        return [
            'queued' => $queued,
            'duplicate' => true,
            'created' => false,
            'status' => $status,
            'notification_id' => (int) ($row['id'] ?? 0),
            'message' => 'Notification already exists for this event.',
        ];
    }

    private function isStillRelevant(array $row): bool
    {
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        if ($type === 'followup') {
            $type = 'followup_due';
        }
        $referenceId = (int) ($row['reference_id'] ?? 0);

        if ($type === 'task') {
            if ($referenceId <= 0) {
                return false;
            }

            $task = $this->taskModel->find($referenceId);
            return is_array($task)
                && (int) ($task['is_done'] ?? 0) === 0
                && ! in_array(strtolower((string) ($task['status'] ?? '')), ['done', 'cancelled'], true);
        }

        if (in_array($type, ['order_created', 'followup_added'], true)) {
            $createdAt = $this->dateTime($row['created_at'] ?? null);
            return $createdAt !== null && $createdAt >= $this->now()->modify('-24 hours');
        }

        if (! in_array($type, ['followup_due', 'followup_delay'], true)) {
            return true;
        }

        if ($referenceId <= 0) {
            return false;
        }

        $db = db_connect();
        $followup = $db->table('order_followups')->where('id', $referenceId)->get()->getRowArray();
        if (! is_array($followup)) {
            return false;
        }

        $orderId = (int) ($followup['order_id'] ?? 0);
        $order = $db->table('orders')->select('status')->where('id', $orderId)->get()->getRowArray();
        if (! is_array($order) || in_array(strtolower(trim((string) ($order['status'] ?? ''))), $this->terminalOrderStatuses(), true)) {
            return false;
        }

        $latest = $db->table('order_followups')->selectMax('id')->where('order_id', $orderId)->get()->getRowArray();
        if ((int) ($latest['id'] ?? 0) !== $referenceId) {
            return false;
        }

        $dueAt = $this->dateTime($followup['next_followup_date'] ?? null);
        if ($dueAt === null || $dueAt > $this->now()) {
            return false;
        }

        $now = $this->now();
        if ($type === 'followup_due') {
            return $dueAt->format('YmdH') === $now->format('YmdH');
        }

        $payload = $this->decodedPayload($row['payload_json'] ?? null);
        return trim((string) ($payload['delay_slot'] ?? '')) === $now->format('YmdH');
    }

    private function claimForDispatch(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($id <= 0 || ! in_array($status, ['pending', 'failed', 'awaiting_local', 'processing'], true)) {
            return [
                'claimed' => false,
                'status' => $status ?: 'skipped',
                'message' => 'Push is not dispatchable.',
            ];
        }

        $now = $this->nowString();
        $db = db_connect();
        $builder = $db->table('mobile_push_notifications')
            ->where('id', $id)
            ->where('done_flag', 0);

        if ($status === 'processing') {
            $builder->where('status', 'processing')
                ->where('last_attempt_at <=', $this->minutesFromNow(-self::CLAIM_TIMEOUT_MINUTES));
        } else {
            $builder->whereIn('status', ['pending', 'failed', 'awaiting_local']);
        }

        $idempotencyKey = trim((string) ($row['onesignal_idempotency_key'] ?? '')) ?: $this->uuidV4();
        $builder->update([
            'status' => 'processing',
            'onesignal_idempotency_key' => $idempotencyKey,
            'last_attempt_at' => $now,
            'updated_at' => $now,
        ]);

        if ($db->affectedRows() !== 1) {
            $latest = $this->notificationModel->find($id);
            $latestStatus = strtolower(trim((string) ($latest['status'] ?? 'processing')));
            return [
                'claimed' => false,
                'status' => $latestStatus,
                'message' => $latestStatus === 'sent'
                    ? 'Push notification was already sent.'
                    : 'Push is already being processed by another worker.',
            ];
        }

        $row['status'] = 'processing';
        $row['onesignal_idempotency_key'] = $idempotencyKey;
        $row['last_attempt_at'] = $now;

        return ['claimed' => true, 'row' => $row];
    }

    private function dateTime($value): ?DateTimeImmutable
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, new DateTimeZone(self::TIMEZONE));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    private function nowString(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    private function minutesFromNow(int $minutes): string
    {
        return $this->now()->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes')->format('Y-m-d H:i:s');
    }

    private function isFuture(?string $dateTime): bool
    {
        $parsed = $this->dateTime($dateTime);
        return $parsed !== null && $parsed > $this->now();
    }

    private function retryMinutes(string $retryAfter): ?int
    {
        $retryAfter = trim($retryAfter);
        if ($retryAfter === '') {
            return null;
        }

        if (ctype_digit($retryAfter)) {
            return max(1, min(1440, (int) ceil(((int) $retryAfter) / 60)));
        }

        try {
            $retryAt = new DateTimeImmutable($retryAfter);
            $seconds = $retryAt->getTimestamp() - time();
            return max(1, min(1440, (int) ceil($seconds / 60)));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function recordFailure(array $row, string $message, ?string $responseJson = null, ?int $retryMinutes = null): void
    {
        $attemptCount = (int) ($row['attempt_count'] ?? 0) + 1;
        if ($retryMinutes === null) {
            $retryMinutes = min(60, max(2, 2 ** min(5, $attemptCount)));
        }

        $this->notificationModel->update((int) ($row['id'] ?? 0), [
            'status' => 'failed',
            'attempt_count' => $attemptCount,
            'last_attempt_at' => $this->nowString(),
            'next_attempt_at' => $this->minutesFromNow($retryMinutes),
            'error_message' => $message,
            'response_json' => $responseJson,
        ]);
    }

    private function terminalOrderStatuses(): array
    {
        return ['ready', 'complete', 'completed', 'packed', 'delivered', 'dispatched', 'cancelled'];
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
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
