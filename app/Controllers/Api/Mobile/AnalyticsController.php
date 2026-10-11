<?php

namespace App\Controllers\Api\Mobile;

use App\Services\BusinessAnalyticsService;
use App\Services\RbacService;
use Throwable;

class AnalyticsController extends MobileBaseController
{
    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        $rbac = new RbacService();
        if (! $rbac->userCan($userId, 'analytics.business.read') && ! $rbac->userCan($userId, 'orders.assign')) {
            return $this->fail('Business analytics permission is required.', 403);
        }
        try {
            return $this->ok((new BusinessAnalyticsService())->dashboard((int) ($this->request->getGet('days') ?: 90)));
        } catch (Throwable $e) {
            log_message('critical', 'Business analytics request failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail('Analytics data is temporarily unavailable. Please retry.', 503);
        }
    }
}
