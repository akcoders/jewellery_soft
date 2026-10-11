<?php

namespace App\Controllers\Api\Mobile;

use App\Services\BusinessAnalyticsService;
use App\Services\RbacService;

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
        return $this->ok((new BusinessAnalyticsService())->dashboard((int) ($this->request->getGet('days') ?: 90)));
    }
}
