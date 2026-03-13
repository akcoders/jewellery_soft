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
            ->orderBy('scheduled_at', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll(200);

        return $this->ok($rows);
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
}
