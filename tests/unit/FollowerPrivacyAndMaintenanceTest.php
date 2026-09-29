<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class FollowerPrivacyAndMaintenanceTest extends CIUnitTestCase
{
    public function testFollowupNotificationsAreQueuedOnlyForCurrentFollower(): void
    {
        $events = (string) file_get_contents(APPPATH . 'Services/MobileNotificationEventService.php');
        $push = (string) file_get_contents(APPPATH . 'Services/MobilePushService.php');
        $notifications = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/NotificationsController.php');

        $this->assertStringContainsString('private function queueForFollower', $events);
        $this->assertStringContainsString('$this->queueForFollower($followerId', $events);
        $this->assertStringContainsString('notifyFollowerAssigned', $events);
        $this->assertStringContainsString('cancelFollowupNotificationsForOtherFollowers', $events);
        $this->assertStringContainsString('notificationBelongsToAdmin', $push);
        $this->assertStringContainsString('notificationBelongsToAdmin($row, $adminId)', $notifications);
        $this->assertStringNotContainsString("queueForPermission('orders.followup'", $events);
    }

    public function testFollowupListsAndHistoryAreFollowerScopedWithAdminOverride(): void
    {
        $web = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $mobile = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/OrdersController.php');
        $screen = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/followups_screen.dart'
        );

        $this->assertStringContainsString("userCan((int) session('admin_id'), 'orders.assign')", $web);
        $this->assertStringContainsString("->where('orders.followup_assigned_to', (int) session('admin_id'))", $web);
        $this->assertStringContainsString("if (\$scope === 'followups')", $mobile);
        $this->assertStringContainsString("->where('o.followup_assigned_to', \$mobileUserId)", $mobile);
        $this->assertStringContainsString('can_view_followups', $mobile);
        $this->assertStringContainsString('followupsOnly: true', $screen);
    }

    public function testPwaUpdateReleasePromptsAndClearsVersionedCache(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/SystemMaintenanceController.php');
        $shell = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/app_shell.dart'
        );
        $index = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/web/index.html');

        $this->assertStringContainsString("system/maintenance/pwa-update", $routes);
        $this->assertStringContainsString("get('app-update'", $routes);
        $this->assertStringContainsString('notifyPwaUpdateReleased', $controller);
        $this->assertStringContainsString('App updated — please relaunch', $shell);
        $this->assertStringContainsString('Clear Cache & Relaunch', $shell);
        $this->assertStringContainsString('clear-pwa-cache', $index);
        $this->assertStringContainsString('aabhushan_pwa_applied_update', $index);
    }

    public function testMonthlyCleanupIsPreviewedAuditedConfirmedAndTransactional(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/SystemMaintenanceController.php');
        $service = (string) file_get_contents(APPPATH . 'Services/MonthlyTestDataCleanupService.php');
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-29-000092_CreatePwaUpdatesAndCleanupAudits.php'
        );

        $this->assertStringContainsString("'CLEAR ' . \$month", $controller);
        $this->assertStringContainsString('password_verify', $controller);
        $this->assertStringContainsString('later_operational_rows', $service);
        $this->assertStringContainsString('transException(true)->transStart()', $service);
        $this->assertStringContainsString('transRollback()', $service);
        $this->assertStringContainsString('reverseInventoryEvent', $service);
        $this->assertStringContainsString('DiamondChalniStockService', $service);
        $this->assertStringContainsString('rebuildAccountBalances', $service);
        $this->assertStringContainsString('rebuildInventoryBalances', $service);
        $this->assertStringNotContainsString("->emptyTable()", $service);
        $this->assertStringContainsString('monthly_data_cleanup_audits', $migration);
        $this->assertStringContainsString('pwa_update_releases', $migration);
    }
}
