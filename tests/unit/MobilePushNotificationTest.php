<?php

namespace Tests\Unit;

use App\Services\MobileNotificationEventService;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use DateTimeZone;

final class MobilePushNotificationTest extends CIUnitTestCase
{
    public function testDelayedFollowupWorkingWindowIncludesElevenAmThroughEightPm(): void
    {
        $timezone = new DateTimeZone('Asia/Kolkata');

        $this->assertFalse(MobileNotificationEventService::isWorkingHour(new DateTimeImmutable('2026-08-25 10:59:59', $timezone)));
        $this->assertTrue(MobileNotificationEventService::isWorkingHour(new DateTimeImmutable('2026-08-25 11:00:00', $timezone)));
        $this->assertTrue(MobileNotificationEventService::isWorkingHour(new DateTimeImmutable('2026-08-25 20:00:00', $timezone)));
        $this->assertFalse(MobileNotificationEventService::isWorkingHour(new DateTimeImmutable('2026-08-25 21:00:00', $timezone)));
    }

    public function testDelayedReminderStartsInNextClockHourAndNotAtDueTime(): void
    {
        $timezone = new DateTimeZone('Asia/Kolkata');
        $due = new DateTimeImmutable('2026-08-25 14:37:00', $timezone);

        $this->assertFalse(MobileNotificationEventService::isDelayedSlotEligible(
            $due,
            new DateTimeImmutable('2026-08-25 14:37:00', $timezone)
        ));
        $this->assertFalse(MobileNotificationEventService::isDelayedSlotEligible(
            $due,
            new DateTimeImmutable('2026-08-25 14:59:59', $timezone)
        ));
        $this->assertTrue(MobileNotificationEventService::isDelayedSlotEligible(
            $due,
            new DateTimeImmutable('2026-08-25 15:00:00', $timezone)
        ));
    }

    public function testQueueUsesServerSideDueDeliveryForFreeOneSignalPlan(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/MobilePushService.php');

        $this->assertStringNotContainsString("\$payload['send_after']", $service);
        $this->assertStringContainsString("->orWhere('scheduled_at <=', \$now)", $service);
        $this->assertStringContainsString("'idempotency_key'", $service);
        $this->assertStringContainsString('claimForDispatch', $service);
        $this->assertStringContainsString('isStillRelevant', $service);
        $this->assertStringContainsString('next_attempt_at', $service);
    }

    public function testDelayedFollowupsAreLatestOnlyTerminalSafeAndHourlyDeduplicated(): void
    {
        $events = (string) file_get_contents(APPPATH . 'Services/MobileNotificationEventService.php');
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000070_EnhanceMobilePushReliability.php'
        );

        $this->assertStringContainsString("select('MAX(id) AS id')", $events);
        $this->assertStringContainsString("'followup-delay:' . \$followupId . ':' . \$slot", $events);
        $this->assertStringContainsString("->where('ofu.next_followup_date <', \$hourStart", $events);
        $this->assertStringContainsString("'defer_dispatch'", $events);
        $this->assertStringContainsString("whereNotIn('o.status', self::TERMINAL_ORDER_STATUSES)", $events);
        $this->assertStringContainsString('uq_mobile_push_dedupe_key', $migration);
        $this->assertStringContainsString("'dedupe_key'", $migration);
    }

    public function testAllInteractiveOrderAndFollowupFlowsTriggerNotifications(): void
    {
        $admin = (string) file_get_contents(APPPATH . 'Controllers/Admin/OrderController.php');
        $customer = (string) file_get_contents(APPPATH . 'Controllers/Customer/OrdersController.php');
        $mobile = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/OrdersController.php');
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("notifyOrderCreated((int) \$orderId, 'admin')", $admin);
        $this->assertStringContainsString('notifyFollowupAdded($id, $followupId)', $admin);
        $this->assertStringContainsString("notifyOrderCreated(\$orderId, 'customer_portal')", $customer);
        $this->assertStringContainsString('notifyFollowupAdded($id, $followupId)', $mobile);
        $this->assertStringNotContainsString("'Api\\OrdersController::create'", $routes);
    }

    public function testNotificationCycleHasOverlapLockAndRunsBothGenerationAndDispatch(): void
    {
        $command = (string) file_get_contents(APPPATH . 'Commands/RunMobileNotificationCycle.php');

        $this->assertStringContainsString('LOCK_EX | LOCK_NB', $command);
        $this->assertStringContainsString('queueHourlyDelayedFollowups()', $command);
        $this->assertStringContainsString('dispatchPendingNotifications($limit)', $command);
    }

    public function testDeviceTaskFallbackRequiresAuthenticatedConfirmation(): void
    {
        $service = (string) file_get_contents(APPPATH . 'Services/MobilePushService.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/NotificationsController.php');
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("'awaiting_local'", $service);
        $this->assertStringContainsString('confirmLocalFallback', $service);
        $this->assertStringContainsString('requireMobileAuth()', $controller);
        $this->assertStringContainsString("notifications/(:num)/local-fallback", $routes);
    }
}
