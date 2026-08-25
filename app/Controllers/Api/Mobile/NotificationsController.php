<?php

namespace App\Controllers\Api\Mobile;

use App\Models\MobilePushNotificationModel;
use App\Services\MobilePushService;

class NotificationsController extends MobileBaseController
{
    private MobilePushNotificationModel $notificationModel;
    private MobilePushService $pushService;

    public function __construct()
    {
        $this->notificationModel = new MobilePushNotificationModel();
        $this->pushService = new MobilePushService();
    }

    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = $this->notificationModel
            ->where('admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->where('done_flag', 0)
            ->whereNotIn('status', ['cancelled', 'done'])
            ->groupStart()
                ->where('scheduled_at', null)
                ->orWhere('scheduled_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy("CASE WHEN type IN ('task', 'followup', 'followup_due', 'followup_delay') THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('COALESCE(scheduled_at, created_at)', 'DESC', false)
            ->orderBy('id', 'DESC')
            ->findAll(200);

        return $this->ok($rows);
    }

    public function status()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $adminId = (int) ($this->mobileAdmin['id'] ?? 0);
        $rows = $this->notificationModel
            ->select('status, COUNT(*) AS total')
            ->where('admin_user_id', $adminId)
            ->groupBy('status')
            ->findAll();

        $queue = [];
        foreach ($rows as $row) {
            $queue[(string) ($row['status'] ?? 'unknown')] = (int) ($row['total'] ?? 0);
        }

        $heartbeat = $this->schedulerHeartbeat();

        return $this->ok([
            'provider' => $this->pushService->configurationStatus(),
            'scheduler' => $heartbeat,
            'queue' => $queue,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
        ]);
    }

    public function done(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $row = $this->notificationModel
            ->where('id', $id)
            ->where('admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->first();

        if (! is_array($row)) {
            return $this->fail('Notification not found.', 404);
        }

        $result = $this->pushService->markDone($id, (int) ($this->mobileAdmin['id'] ?? 0));
        if (! ($result['ok'] ?? false)) {
            return $this->fail((string) ($result['message'] ?? 'Could not mark notification as done.'), 422);
        }

        return $this->ok(['id' => $id], 'Notification marked as done.');
    }

    public function localFallback(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        if (! array_key_exists('scheduled', $payload)) {
            return $this->fail('scheduled confirmation is required.', 422);
        }
        $scheduled = filter_var($payload['scheduled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($scheduled === null) {
            return $this->fail('scheduled must be true or false.', 422);
        }

        $result = $this->pushService->confirmLocalFallback(
            $id,
            (int) ($this->mobileAdmin['id'] ?? 0),
            $scheduled
        );
        if (! ($result['ok'] ?? false)) {
            return $this->fail((string) ($result['message'] ?? 'Could not confirm local fallback.'), 422);
        }

        return $this->ok([
            'id' => $id,
            'status' => (string) ($result['status'] ?? ''),
        ], 'Local reminder result recorded.');
    }

    private function schedulerHeartbeat(): array
    {
        $path = WRITEPATH . 'mobile-push-cycle-heartbeat.json';
        if (! is_file($path)) {
            return [
                'healthy' => false,
                'last_run_at' => null,
                'message' => 'Notification scheduler has not run yet.',
            ];
        }

        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $lastRunAt = is_array($decoded) ? trim((string) ($decoded['completed_at'] ?? '')) : '';
        $lastRunTs = $lastRunAt !== '' ? strtotime($lastRunAt) : false;
        $lastRunSucceeded = is_array($decoded) && ($decoded['success'] ?? false) === true;
        $healthy = $lastRunSucceeded && $lastRunTs !== false && $lastRunTs >= time() - 180;

        return [
            'healthy' => $healthy,
            'last_run_at' => $lastRunAt !== '' ? $lastRunAt : null,
            'message' => $healthy
                ? 'Notification scheduler is running.'
                : 'Notification scheduler has not completed in the last 3 minutes.',
        ];
    }
}
