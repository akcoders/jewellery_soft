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

    public function testAndroidPwaIsTemporarilyPausedWhileIosAndDesktopContinue(): void
    {
        $source = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/web/index.html');
        $deployed = (string) file_get_contents(ROOTPATH . 'public/pwa/index.html');

        foreach ([$source, $deployed] as $index) {
            $this->assertStringContainsString('/Android/i.test', $index);
            $this->assertStringContainsString('isAndroid && Date.now() < resumeAt', $index);
            $this->assertStringContainsString('2026-10-14T00:00:00+05:30', $index);
            $this->assertStringContainsString('Download Android APK', $index);
            $this->assertStringContainsString('aabhushan-erp-1.0.8-build9.apk', $index);
            $this->assertStringContainsString('if (!window.aabhushanAndroidPwaPaused)', $index);
            $this->assertStringContainsString('android-pwa-resumed', $index);
        }
    }

    public function testAndroidUpdateReleaseIsVersionedDownloadableAndPromptedInApp(): void
    {
        $release = (string) file_get_contents(APPPATH . 'Config/MobileAppRelease.php');
        $endpoint = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/AppUpdateController.php');
        $shell = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/app_shell.dart'
        );
        $native = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/services/android_update_service_native.dart'
        );
        $activity = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/android/app/src/main/kotlin/com/aabhushan/erp/MainActivity.kt'
        );
        $manifest = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/android/app/src/main/AndroidManifest.xml'
        );

        $this->assertStringContainsString("androidVersion = '1.0.8'", $release);
        $this->assertStringContainsString('androidVersionCode = 9', $release);
        $this->assertStringContainsString('releases/download/android-v1.0.8-build9/', $release);
        $this->assertStringContainsString("\$platform === 'android'", $endpoint);
        $this->assertStringContainsString("'version_code' => \$release->androidVersionCode", $endpoint);
        $this->assertStringContainsString('A new update is ready', $shell);
        $this->assertStringContainsString('LaunchMode.externalApplication', $native);
        $this->assertStringContainsString('com.aabhushan.erp/app_info', $activity);
        $this->assertStringContainsString('androidRequired = true', $release);
        $this->assertStringContainsString('downloadAndInstall', $native);
        $this->assertStringContainsString('sha256.bind(file.openRead())', $native);
        $this->assertStringContainsString('OpenFilex.open', $native);
        $this->assertStringContainsString('Install update', $shell);
        $this->assertStringContainsString('required before you can continue', $shell);
        $this->assertStringContainsString('android.permission.REQUEST_INSTALL_PACKAGES', $manifest);
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
